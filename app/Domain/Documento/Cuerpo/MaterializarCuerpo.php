<?php

declare(strict_types=1);

namespace App\Domain\Documento\Cuerpo;

use App\Domain\Documento\Contenido\ContenidoDocumento;
use App\Domain\Documento\Contenido\FilaRequisito;
use App\Domain\Documento\Enums\TipoDocumento;
use LogicException;

/**
 * Rellena los huecos calculados del cuerpo con lo que dice el registro.
 *
 * Es el puente entre las dos mitades del producto: `DocumentoCalculado`
 * sigue consultando `implantaciones` y produciendo un `ContenidoDocumento`
 * exactamente igual que antes, y esto lo convierte en nodos del documento. Lo
 * que antes hacían once parciales de Blade se hace aquí, con el mismo marcado y
 * las mismas clases de `documento.css`.
 *
 * **Recorre y sustituye; no reconstruye.** Un nodo con `fuente` se cambia
 * entero; un nodo sin `fuente` se deja tal cual, esté donde esté. Eso es lo que
 * permite volver a materializar un documento que alguien ya ha editado sin
 * pisarle el texto libre, que es la diferencia entre una herramienta que se
 * puede usar dos veces y una que obliga a elegir entre los datos y el trabajo.
 *
 * Dos bloques se materializan aquí **y se vuelven a pedir en cada generación**
 * (`EsquemaCuerpo::SIEMPRE_RECALCULADOS`): las limitaciones, porque son donde el
 * documento declara lo que no puede afirmar —incluido haberse editado a mano—, y
 * el control de versiones, porque la huella de una versión no puede ir dentro
 * del PDF que la produce.
 */
final class MaterializarCuerpo
{
    /**
     * Cómo se llama cada bloque calculado **en el documento**, no en el código.
     *
     * Es lo que se imprime en la declaración de edición manual, y va dirigido a
     * quien audita: «tabla_requisitos» no le dice nada, «la tabla de requisitos»
     * sí.
     *
     * @var array<string, string>
     */
    private const NOMBRES_DE_BLOQUE = [
        'portada_ficha' => 'la ficha de la portada',
        'portada_pie' => 'el pie de la portada',
        'resumen_cifras' => 'las cifras del resumen',
        'resumen_plan' => 'las cifras del plan',
        'resumen_grafica' => 'la gráfica del resumen',
        'tabla_requisitos' => 'la tabla de requisitos',
        'tabla_sin_trabajo' => 'la tabla de medidas sin trabajo planificado',
        'tabla_exclusiones' => 'la tabla de exclusiones',
        'tabla_derivacion' => 'la derivación de la categoría',
        'notas_anexo_ii' => 'las notas del Anexo II',
        'tabla_madurez' => 'la madurez por marco',
        'dafo_cuadrantes' => 'las cuestiones del contexto',
        'tabla_partes_interesadas' => 'la tabla de partes interesadas',
        'declaracion_climatica' => 'la declaración sobre el cambio climático',
        'alcance_sistemas' => 'el alcance declarado de cada sistema',
        'ficha_revision' => 'la ficha de la reunión',
        'entradas_revision' => 'las entradas de la revisión por la dirección',
        'tabla_decisiones' => 'la tabla de decisiones',
        'declaracion_formal' => 'la declaración formal de conformidad',
        'ficha_autoevaluacion' => 'la ficha de la autoevaluación',
        'resultado_autoevaluacion' => 'el resultado de la autoevaluación',
        'limitaciones_sistema' => 'las limitaciones del sistema',
        'control_versiones' => 'el control de versiones',
    ];

    /**
     * @param  array<string, mixed>  $cuerpo  el esqueleto, o un cuerpo ya editado
     * @param  list<string>  $tocados  bloques calculados que alguien ha editado
     * @return array<string, mixed>
     */
    public function __invoke(array $cuerpo, ContenidoDocumento $contenido, TipoDocumento $tipo, bool $editado = false, array $tocados = []): array
    {
        return $this->recorrer($cuerpo, $contenido, $tipo, $editado, null, $tocados);
    }

    /**
     * Vuelve a calcular sólo los bloques indicados, y deja el resto como está.
     *
     * @param  array<string, mixed>  $cuerpo
     * @param  list<string>  $fuentes
     * @param  list<string>  $tocados  bloques calculados que alguien ha editado
     * @return array<string, mixed>
     */
    public function soloEstos(array $cuerpo, ContenidoDocumento $contenido, TipoDocumento $tipo, array $fuentes, bool $editado = false, array $tocados = []): array
    {
        return $this->recorrer($cuerpo, $contenido, $tipo, $editado, $fuentes, $tocados);
    }

    /**
     * @param  array<string, mixed>  $nodo
     * @param  list<string>|null  $solo
     * @param  list<string>  $tocados
     * @return array<string, mixed>
     */
    private function recorrer(array $nodo, ContenidoDocumento $contenido, TipoDocumento $tipo, bool $editado, ?array $solo = null, array $tocados = []): array
    {
        $fuente = $nodo['attrs']['fuente'] ?? null;

        if (is_string($fuente) && EsquemaCuerpo::esFuente($fuente) && ($solo === null || in_array($fuente, $solo, true))) {
            return $this->bloque($fuente, $contenido, $tipo, $editado, $tocados);
        }

        $hijos = $nodo['content'] ?? [];

        if (! is_array($hijos) || $hijos === []) {
            return $nodo;
        }

        $nodo['content'] = array_values(array_map(
            fn (mixed $hijo): mixed => is_array($hijo)
                ? $this->recorrer($hijo, $contenido, $tipo, $editado, $solo, $tocados)
                : $hijo,
            $hijos,
        ));

        return $nodo;
    }

    /**
     * @param  list<string>  $tocados
     * @return array<string, mixed>
     */
    private function bloque(string $fuente, ContenidoDocumento $contenido, TipoDocumento $tipo, bool $editado, array $tocados): array
    {
        $hijos = match ($fuente) {
            'portada_ficha' => $this->portadaFicha($contenido),
            'portada_pie' => $this->portadaPie($editado, $tipo),
            'resumen_cifras' => $this->resumenCifras($contenido, $tipo),
            'resumen_plan' => $this->resumenPlan($contenido),
            'resumen_grafica' => $this->resumenGrafica($contenido),
            'tabla_requisitos' => $this->tablaRequisitos($contenido, $tipo),
            'tabla_sin_trabajo' => $this->tablaSinTrabajo($contenido),
            'tabla_exclusiones' => $this->tablaExclusiones($contenido),
            'tabla_derivacion' => $this->tablaDerivacion($contenido),
            'notas_anexo_ii' => $this->notasAnexoII($contenido),
            'tabla_madurez' => $this->tablaMadurez($contenido),
            'dafo_cuadrantes' => $this->dafoCuadrantes($contenido),
            'tabla_partes_interesadas' => $this->tablaPartesInteresadas($contenido),
            'declaracion_climatica' => $this->declaracionClimatica($contenido),
            'alcance_sistemas' => $this->alcanceSistemas($contenido),
            'ficha_revision' => $this->fichaRevision($contenido),
            'entradas_revision' => $this->entradasRevision($contenido),
            'tabla_decisiones' => $this->tablaDecisiones($contenido),
            'declaracion_formal' => $this->declaracionFormal($contenido),
            'ficha_autoevaluacion' => $this->fichaAutoevaluacion($contenido),
            'resultado_autoevaluacion' => $this->resultadoAutoevaluacion($contenido),
            'limitaciones_sistema' => $this->limitaciones($contenido, $editado, $tocados),
            'control_versiones' => $this->controlVersiones($contenido),

            /*
             * El `match` es sobre una cadena y no sobre un enum, así que PHPStan
             * no señala la rama que falta: una fuente nueva declarada en
             * `EsquemaCuerpo::FUENTES` y olvidada aquí se materializaba como un
             * grupo **vacío**, o sea un apartado que desaparece del PDF sin que
             * nada avise.
             *
             * `recorrer()` sólo entra aquí si `EsquemaCuerpo::esFuente()`, de
             * modo que esto no lo puede provocar un cuerpo editado por nadie:
             * sólo una fuente declarada y sin materializador.
             */
            default => throw new LogicException("Fuente sin materializador: {$fuente}."),
        };

        return Nodo::calculado($fuente, 'grupo', $hijos);
    }

    // --- Portada ------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    private function portadaFicha(ContenidoDocumento $contenido): array
    {
        $p = $contenido->portada;

        $nodos = [
            Nodo::encabezado(1, $contenido->titulo, 'titulo_portada'),
            Nodo::parrafo($contenido->subtitulo, 'subtitulo_portada'),
        ];

        if (($p['esBorrador'] ?? false) === true) {
            $nodos[] = Nodo::de('caja', ['estilo' => 'aviso_borrador'], [
                Nodo::parrafoRico(
                    '**Borrador — no es una entrega.** Este PDF se regenera cada vez que se pide. '.
                    'Sólo las versiones emitidas quedan registradas de forma inmutable con su huella SHA-256.'
                ),
            ]);
        }

        $nodos[] = Nodo::de('ficha', [], $this->filasDeFicha($p));

        $alcance = $this->cadena($p, 'alcance');

        if ($alcance !== null) {
            $caja = [
                Nodo::encabezado(4, 'Alcance declarado'),
                Nodo::parrafo($alcance),
            ];

            $exclusiones = $this->cadena($p, 'exclusionesAlcance');

            if ($exclusiones !== null) {
                $caja[] = Nodo::encabezado(4, 'Exclusiones del alcance');
                $caja[] = Nodo::parrafo($exclusiones);
            }

            $nodos[] = Nodo::de('caja', ['variante' => 'marca'], $caja);
        }

        return $nodos;
    }

    /**
     * Las siete u ocho filas que identifican el documento.
     *
     * El código del sistema y el del documento van con la marca `cifra`, que es
     * la monoespaciada tabular: un código alineado se compara de un vistazo con
     * el del expediente, y eso es lo que se hace con esta ficha.
     *
     * @param  array<string, mixed>  $p
     * @return list<array<string, mixed>>
     */
    private function filasDeFicha(array $p): array
    {
        $organizacion = $this->cadena($p, 'organizacion') ?? '—';
        $cif = $this->cadena($p, 'cif');

        $filas = [
            Nodo::de('fichaFila', ['clave' => 'Organización'], [
                Nodo::texto($cif === null ? $organizacion : $organizacion.' · CIF '.$cif),
            ]),
        ];

        /*
         * Sistema y marco **sólo si los hay**. Un documento redactado —una
         * política, una norma— es de la organización entera y no cuelga de
         * ningún sistema: imprimirle «Sistema: —» y «Marco: —» en portada no es
         * un hueco sin rellenar, es decirle al auditor que falta un dato que no
         * existe. Una declaración de aplicabilidad los lleva siempre, porque el
         * `CHECK` de la tabla se los exige.
         */
        $sistemaCodigo = $this->cadena($p, 'sistemaCodigo');
        $sistemaNombre = $this->cadena($p, 'sistemaNombre');

        if ($sistemaCodigo !== null || $sistemaNombre !== null) {
            $filas[] = Nodo::de('fichaFila', ['clave' => 'Sistema'], $sistemaCodigo === null
                ? [Nodo::texto((string) $sistemaNombre)]
                : [Nodo::texto($sistemaCodigo, ['cifra']), Nodo::texto(' · '.($sistemaNombre ?? '—'))]);
        }

        $marco = $this->cadena($p, 'marco');

        if ($marco !== null) {
            $version = $this->cadena($p, 'marcoVersion');

            $filas[] = Nodo::de('fichaFila', ['clave' => 'Marco'], [
                Nodo::texto($version === null ? $marco : $marco.' ('.$version.')'),
            ]);
        }

        // Sólo la DdA tiene categoría, y va en negrita porque es de donde sale
        // todo lo demás del documento.
        if (array_key_exists('categoria', $p)) {
            $filas[] = Nodo::de('fichaFila', ['clave' => 'Categoría ENS'], [
                Nodo::texto($this->cadena($p, 'categoria') ?? 'Sin valorar', ['bold']),
            ]);
        }

        $filas[] = Nodo::de('fichaFila', ['clave' => 'Documento'], [
            Nodo::texto($this->cadena($p, 'documentoCodigo') ?? '—', ['cifra']),
            Nodo::texto(' · '.($this->cadena($p, 'version') ?? '—')),
        ]);

        $filas[] = Nodo::de('fichaFila', ['clave' => 'Fecha'], [Nodo::texto($this->cadena($p, 'fecha') ?? '—')]);
        $filas[] = Nodo::de('fichaFila', ['clave' => 'Clasificación'], [Nodo::texto($this->cadena($p, 'clasificacion') ?? '—')]);
        $filas[] = Nodo::de('fichaFila', ['clave' => 'Responsable'], [Nodo::texto($this->cadena($p, 'responsable') ?? 'Sin asignar')]);

        /*
         * La firma, y **es la razón de que aprobar sea lo que emite**: la portada
         * se congela al generar, así que si la aprobación llegara después no
         * podría figurar aquí — y aquí es donde el auditor la busca.
         *
         * En negrita, como la categoría de la DdA, porque es lo que convierte un
         * fichero en un documento del sistema de gestión.
         */
        $aprobadaPor = $this->cadena($p, 'aprobadaPor');

        if ($aprobadaPor !== null) {
            $filas[] = Nodo::de('fichaFila', ['clave' => 'Aprobada por'], [
                Nodo::texto($aprobadaPor, ['bold']),
                Nodo::texto(' · '.($this->cadena($p, 'aprobadaEn') ?? '—')),
            ]);
        }

        $proxima = $this->cadena($p, 'proximaRevision');

        if ($proxima !== null) {
            $filas[] = Nodo::de('fichaFila', ['clave' => 'Próxima revisión'], [Nodo::texto($proxima)]);
        }

        return $filas;
    }

    /**
     * La frase con la que el documento dice cómo se hizo.
     *
     * Es lo único de la portada que depende de si alguien lo ha editado, y por
     * eso es un bloque aparte y no parte de la ficha. Un auditor respeta un
     * documento que dice cómo se hizo; el que no lo dice es el que suspende.
     *
     * @return list<array<string, mixed>>
     */
    private function portadaPie(bool $editado, TipoDocumento $tipo): array
    {
        /*
         * Un documento redactado no tiene tablas ni cifras que explicar: lo
         * escribe la organización de principio a fin, y decir que «se genera
         * desde el registro de implantaciones» sería falso en su propia portada.
         * Lo que sí tiene que decir es lo mismo que los demás: cómo se hizo.
         */
        $frase = match (true) {
            $editado => 'Statera — un producto de RM Technology. Este documento se generó desde el registro de '.
                'implantaciones y después se editó a mano. Los apartados calculados que se hayan '.
                'modificado, o que ya no coincidan con el registro, figuran en el apartado de limitaciones.',

            $tipo->esRedactado() => 'Statera — un producto de RM Technology. El contenido de este documento lo '.
                'redacta la organización; Statera aporta el control de versiones, la huella del fichero '.
                'entregado y el registro de su aprobación.',

            default => 'Statera — un producto de RM Technology. '.
                'Las tablas, las cifras y la derivación de la categoría se generan desde el registro de '.
                'implantaciones y no se mantienen a mano; los textos de presentación los redacta la '.
                'organización.',
        };

        return [Nodo::de('pieDePortada', [], [Nodo::texto($frase)])];
    }

    // --- Resumen ------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    private function resumenCifras(ContenidoDocumento $contenido, TipoDocumento $tipo): array
    {
        $r = $contenido->resumen;

        $total = $this->entero($r, 'total');
        $enElMarco = $this->entero($r, 'enElMarco');
        $aplicables = $this->entero($r, 'aplicables');
        $madurez = $r['madurezMedia'] ?? null;
        $porcentaje = $r['porcentaje'] ?? null;

        $cifras = Nodo::de('cifras', [], [
            // Con su denominador: el marco puede exigir menos de lo que tiene.
            $this->cifra((string) $total, 'de '.$enElMarco, ColumnasTabla::etiquetaTotal($tipo)),
            $this->cifra((string) $aplicables, 'de '.$total, 'Aplicables'),
            $this->cifra((string) $this->entero($r, 'excluidos'), null, 'Excluidos'),
            $this->cifra(
                (string) $this->entero($r, 'implantados'),
                'de '.$aplicables,
                'Implantados'.($porcentaje === null ? '' : ' · '.$porcentaje.'%'),
            ),
            // Sin ninguna valorada la media no es cero: es que no se sabe.
            $this->cifra(
                $madurez === null ? '—' : 'L'.$madurez,
                'sobre '.$this->entero($r, 'madurezEvaluadas'),
                'Madurez media',
            ),
            $this->cifra((string) $this->entero($r, 'sinEvidencia'), 'de '.$aplicables, 'Sin evidencia'),
        ]);

        $nodos = [$cifras];

        if ($total < $enElMarco) {
            $nodos[] = Nodo::parrafo(
                'El marco tiene '.$enElMarco.' requisitos y a este sistema se le exigen '.$total.'. '.
                'Los '.($enElMarco - $total).' restantes no se le exigen por su categoría, y por eso no '.
                'figuran en la tabla: la exigencia se deriva de la valoración de las cinco dimensiones, '.
                'no se marca a mano.',
                'suave',
            );
        }

        return $nodos;
    }

    /**
     * Las cifras del plan de adecuación, que no son las de una declaración.
     *
     * **El denominador va delante y no implícito.** Una tabla de treinta y tres
     * filas sin él se lee como si al sistema se le exigieran treinta y tres
     * medidas; lo que dice de verdad es que de cincuenta y dos exigidas hay
     * diecinueve puestas y éstas son las que faltan. La frase de debajo lo dice
     * con palabras, igual que hace la DdA con las medidas que su categoría no le
     * exige.
     *
     * @return list<array<string, mixed>>
     */
    private function resumenPlan(ContenidoDocumento $contenido): array
    {
        $r = $contenido->resumen;

        $total = $this->entero($r, 'total');
        $exigibles = $this->entero($r, 'exigibles');
        $implantadas = $this->entero($r, 'implantadas');
        $sinEstimar = $this->entero($r, 'costeSinEstimar');
        $coste = $this->cadena($r, 'costeTotal');

        $cifras = Nodo::de('cifras', [], [
            $this->cifra((string) $total, 'de '.$exigibles, 'Medidas en este plan'),
            $this->cifra((string) $implantadas, 'de '.$exigibles, 'Ya implantadas'),
            $this->cifra((string) $this->entero($r, 'sinFecha'), 'de '.$total, 'Sin fecha objetivo'),
            $this->cifra((string) $this->entero($r, 'fueraDePlazo'), 'de '.$total, 'Fuera de plazo'),
            $this->cifra((string) $this->entero($r, 'sinTrabajo'), 'de '.$total, 'Sin trabajo planificado'),
            $this->cifra(
                $coste ?? '—',
                $sinEstimar === 0 ? null : $sinEstimar.' sin estimar',
                'Coste estimado',
            ),
        ]);

        $nodos = [$cifras];

        $nodos[] = Nodo::parrafo(
            'Al sistema se le exigen '.$exigibles.' medidas del Anexo II, de las cuales '.$implantadas.
            ' figuran implantadas. Este plan recoge las '.$total.' restantes.',
            'suave',
        );

        return $nodos;
    }

    /**
     * @return array<string, mixed>
     */
    private function cifra(string $valor, ?string $de, string $etiqueta): array
    {
        return Nodo::de('cifraDato', ['valor' => $valor, 'de' => $de, 'etiqueta' => $etiqueta]);
    }

    /**
     * La barra por tramos y su leyenda.
     *
     * El nodo guarda el reparto, no el dibujo. La leyenda va siempre que haya
     * tramos: la identidad de un estado nunca depende sólo del color —DESIGN.md
     * §3 deja tres por debajo de 4.5:1, y `implantado` y `en_progreso` no se
     * distinguen con protanopia—.
     *
     * @return list<array<string, mixed>>
     */
    private function resumenGrafica(ContenidoDocumento $contenido): array
    {
        $segmentos = $contenido->resumen['segmentos'] ?? [];

        if (! is_array($segmentos) || $segmentos === []) {
            return [];
        }

        $datos = [];
        $badges = [];

        foreach ($segmentos as $segmento) {
            $clave = is_object($segmento) ? ($segmento->clave ?? null) : ($segmento['clave'] ?? null);
            $etiqueta = is_object($segmento) ? ($segmento->etiqueta ?? null) : ($segmento['etiqueta'] ?? null);
            $valor = is_object($segmento) ? ($segmento->valor ?? null) : ($segmento['valor'] ?? null);

            if (! is_string($clave) || ! is_string($etiqueta) || ! is_int($valor)) {
                continue;
            }

            $datos[] = ['clave' => $clave, 'etiqueta' => $etiqueta, 'valor' => $valor];

            if ($valor > 0) {
                $badges[] = Nodo::badge($clave, $etiqueta.': '.$valor);
            }
        }

        if ($datos === []) {
            return [];
        }

        return [Nodo::de('grafica', ['segmentos' => $datos], [Nodo::de('leyenda', [], $badges)])];
    }

    // --- La tabla larga -----------------------------------------------------

    /**
     * Un requisito por fila, agrupados por su epígrafe.
     *
     * La fila de grupo es un `<th colspan>` dentro del `<tbody>`, no una
     * cabecera: se queda entre las filas que agrupa en vez de repetirse en cada
     * página, y por eso `RenderizadorCuerpo` la excluye explícitamente del
     * `<thead>`.
     *
     * @return list<array<string, mixed>>
     */
    private function tablaRequisitos(ContenidoDocumento $contenido, TipoDocumento $tipo): array
    {
        $columnas = ColumnasTabla::para($tipo);

        $filas = [Nodo::fila(array_map(
            static fn (array $columna): array => Nodo::cabeceraCelda($columna['titulo'], $columna['ancho'], 'col'),
            $columnas,
        ))];

        foreach ($contenido->filasPorGrupo() as $grupo => $delGrupo) {
            $filas[] = Nodo::fila(
                [Nodo::cabeceraCelda((string) $grupo, null, 'colgroup', count($columnas))],
                'grupo',
            );

            foreach ($delGrupo as $fila) {
                $filas[] = Nodo::fila(array_map(
                    fn (array $columna): array => $this->celdaDeFila($fila, $columna['clave']),
                    $columnas,
                ));
            }
        }

        return [Nodo::de('table', ['clase' => 'fija'], $filas)];
    }

    /**
     * @return array<string, mixed>
     */
    private function celdaDeFila(FilaRequisito $fila, string $clave): array
    {
        $clase = $clave === 'codigo' ? 'codigo' : null;

        return match ($clave) {
            'codigo' => Nodo::celdaTexto($fila->codigo, $clase),
            'titulo' => Nodo::celdaTexto($fila->titulo),

            // Nunca sólo color: la celda lleva la palabra, y el «No» en negrita.
            'aplica' => Nodo::celda([Nodo::texto($fila->aplica ? 'Sí' : 'No', $fila->aplica ? [] : ['bold'])]),

            'estado' => Nodo::celda([Nodo::badge($fila->estadoTono, $fila->estadoEtiqueta)]),
            'justificacionInclusion' => Nodo::celdaTexto($fila->justificacionInclusion ?? '—'),

            // «SIN JUSTIFICAR» en una exclusión es un hallazgo, y se dice.
            'justificacion' => Nodo::celdaTexto($fila->justificacion ?? ($fila->aplica ? '—' : 'SIN JUSTIFICAR')),

            'exigencia' => $fila->exigencia === null
                ? Nodo::celdaTexto('—')
                : Nodo::celda([Nodo::badge('neutro', $fila->exigencia)]),

            'origenExigencia' => Nodo::celda(
                $fila->dimensionModuladora === null
                    ? [Nodo::texto($fila->origenExigencia ?? '—')]
                    : [
                        Nodo::texto($fila->origenExigencia ?? '—'),
                        Nodo::de('nota', [], [Nodo::texto('Dimensión: '.$fila->dimensionModuladora)]),
                    ],
            ),

            'madurez' => Nodo::celdaTexto($fila->madurez ?? '—'),
            'responsable' => Nodo::celdaTexto($fila->responsable ?? 'Sin asignar'),

            // Se dice explícitamente: una celda vacía se lee como un descuido.
            'evidencias' => $fila->tieneEvidencia()
                ? Nodo::celdaTexto($fila->evidenciaODefecto())
                : Nodo::celda([Nodo::texto('Sin evidencia registrada', ['suave'])]),

            'correspondencias' => $fila->correspondencias === []
                ? Nodo::celda([Nodo::texto('—', ['suave'])])
                : Nodo::celda([Nodo::texto(implode(', ', $fila->correspondencias), ['cifra'])]),

            // Las cuatro del plan de adecuación. Ninguna se deja en blanco: en
            // un plan, lo que falta ES el contenido, y una celda vacía se lee
            // como un descuido de maquetación en vez de como un hallazgo.
            'fechaObjetivo' => $fila->fechaObjetivo === null
                ? Nodo::celda([Nodo::texto('Sin fecha', ['suave'])])
                : Nodo::celda([Nodo::texto($fila->fechaObjetivo, ['cifra'])]),

            'tareas' => $fila->tareas === []
                ? Nodo::celda([Nodo::texto('Sin trabajo planificado', ['suave'])])
                : Nodo::celda([Nodo::texto(implode('; ', $fila->tareas))]),

            'coste' => $fila->costeEstimado === null
                ? Nodo::celda([Nodo::texto('Sin estimar', ['suave'])])
                : Nodo::celda([Nodo::texto($fila->costeEstimado, ['cifra'])]),

            'riesgos' => $fila->riesgos === []
                ? Nodo::celda([Nodo::texto('—', ['suave'])])
                : Nodo::celda([Nodo::texto(implode(', ', $fila->riesgos), ['cifra'])]),

            /*
             * Una columna declarada en `ColumnasTabla` y olvidada aquí salía
             * como una raya en las noventa y tres filas del documento, sin
             * ningún error: la cabecera con su título y la columna entera
             * vacía. Las claves no llegan de fuera —las declara
             * `ColumnasTabla::para()`, que es código—, así que esto sólo puede
             * dispararlo un descuido de programación y nunca un dato de
             * usuario.
             */
            default => throw new LogicException("Columna sin celda en MaterializarCuerpo: {$clave}."),
        };
    }

    // --- Plan: lo que no tiene a nadie detrás -------------------------------

    /**
     * Las medidas pendientes de las que nadie ha apuntado qué va a hacer.
     *
     * Repite filas que ya salen en la tabla larga, y la duplicación es
     * deliberada por el mismo motivo que la de exclusiones en la SoA: es lo que
     * la dirección y el auditor van a mirar seguro, y hacerles filtrar treinta y
     * tres filas para encontrar seis sería hacerles trabajar de más.
     *
     * Un plan completo no es el que no tiene ninguna, es el que las declara.
     *
     * @return list<array<string, mixed>>
     */
    private function tablaSinTrabajo(ContenidoDocumento $contenido): array
    {
        $sinTrabajo = array_values(array_filter(
            $contenido->filas,
            static fn (FilaRequisito $fila): bool => $fila->tareas === [],
        ));

        $total = $this->entero($contenido->resumen, 'total');

        if ($sinTrabajo === []) {
            return [Nodo::parrafo(
                'Todas las medidas de este plan tienen al menos una tarea abierta asociada.',
                'vacio',
            )];
        }

        $filas = [Nodo::fila([
            Nodo::cabeceraCelda('Medida', '0.9in', 'col'),
            Nodo::cabeceraCelda('Título', '3.2in', 'col'),
            Nodo::cabeceraCelda('Responsable', '1.6in', 'col'),
            Nodo::cabeceraCelda('Fecha objetivo', null, 'col'),
        ])];

        foreach ($sinTrabajo as $fila) {
            $filas[] = Nodo::fila([
                Nodo::celdaTexto($fila->codigo, 'codigo'),
                Nodo::celdaTexto($fila->titulo),
                Nodo::celdaTexto($fila->responsable ?? 'Sin asignar'),
                Nodo::celdaTexto($fila->fechaObjetivo ?? 'Sin fecha'),
            ]);
        }

        return [
            Nodo::parrafo(
                count($sinTrabajo).' de las '.$total.' medidas de este plan no tienen ninguna tarea '.
                'abierta asociada. Eso no significa que no se esté trabajando en ellas: significa que '.
                'no consta en el registro, y por tanto no se puede seguir ni presupuestar.',
                'suave',
            ),
            Nodo::de('table', ['clase' => 'fija'], $filas),
        ];
    }

    // --- ISO: las exclusiones -----------------------------------------------

    /**
     * Repite, completas, las filas excluidas que ya salen en la tabla general.
     *
     * La duplicación es deliberada: es la sección que el auditor abre primero,
     * porque la cláusula 6.1.3 d) le obliga a comprobar que toda exclusión está
     * justificada. Hacerle filtrar noventa y tres filas para encontrar cuatro
     * sería hacerle trabajar de más para lo único que va a mirar seguro.
     *
     * @return list<array<string, mixed>>
     */
    private function tablaExclusiones(ContenidoDocumento $contenido): array
    {
        $excluidas = $contenido->excluidas();
        $total = $this->entero($contenido->resumen, 'total');

        if ($excluidas === []) {
            return [Nodo::parrafo(
                'No se ha excluido ningún control del Anexo A. Los '.$total.' controles son aplicables al sistema.',
                'vacio',
            )];
        }

        $filas = [Nodo::fila([
            Nodo::cabeceraCelda('Control', '0.9in', 'col'),
            Nodo::cabeceraCelda('Título', '2.6in', 'col'),
            Nodo::cabeceraCelda('Justificación de la exclusión', null, 'col'),
        ])];

        foreach ($excluidas as $fila) {
            $filas[] = Nodo::fila([
                Nodo::celdaTexto($fila->codigo, 'codigo'),
                Nodo::celdaTexto($fila->titulo),
                Nodo::celdaTexto($fila->justificacion ?? 'SIN JUSTIFICAR'),
            ]);
        }

        return [
            Nodo::parrafo(
                count($excluidas).' de '.$total.' controles quedan fuera del alcance, cada uno con el '.
                'motivo que registró la organización.',
                'suave',
            ),
            Nodo::de('table', ['clase' => 'fija'], $filas),
        ];
    }

    // --- ENS: la derivación y el Anexo II -----------------------------------

    /**
     * De dónde sale la categoría.
     *
     * Enseñar la categoría sin las cinco dimensiones y sus justificaciones
     * obligaría al auditor a fiarse, y un auditor no se fía: comprueba.
     *
     * @return list<array<string, mixed>>
     */
    private function tablaDerivacion(ContenidoDocumento $contenido): array
    {
        $d = $contenido->extras['derivacion'] ?? [];
        $dimensiones = is_array($d) ? ($d['dimensiones'] ?? []) : [];

        if (! is_array($dimensiones) || $dimensiones === []) {
            return [Nodo::parrafo(
                'El sistema no tiene valoradas las cinco dimensiones, así que no hay categoría de la que '.
                'derivar el conjunto de medidas exigibles.',
                'vacio',
            )];
        }

        // Si `$d` no fuera un array no habría dimensiones y ya habríamos vuelto.
        $formula = $this->cadena($d, 'formula');

        $filas = [Nodo::fila([
            Nodo::cabeceraCelda('Dim.', '0.5in', 'col'),
            Nodo::cabeceraCelda('Dimensión', '1.7in', 'col'),
            Nodo::cabeceraCelda('Nivel', '0.9in', 'col'),
            Nodo::cabeceraCelda('Justificación de la valoración', null, 'col'),
        ])];

        foreach ($dimensiones as $dimension) {
            if (! is_array($dimension)) {
                continue;
            }

            $filas[] = Nodo::fila([
                Nodo::celdaTexto($this->cadena($dimension, 'codigo') ?? '—', 'codigo'),
                Nodo::celdaTexto($this->cadena($dimension, 'nombre') ?? '—'),
                Nodo::celdaTexto($this->cadena($dimension, 'nivel') ?? '—'),
                Nodo::celdaTexto($this->cadena($dimension, 'justificacion') ?? 'Sin justificar'),
            ]);
        }

        return [
            Nodo::de('caja', ['variante' => 'marca'], [
                Nodo::encabezado(4, 'Derivación'),
                Nodo::de('paragraph', ['estilo' => 'formula'], [Nodo::texto($formula ?? '—', ['bold'])]),
            ]),
            Nodo::de('table', [], $filas),
        ];
    }

    /**
     * Las dos brechas de modelo que el catálogo declara, impresas.
     *
     * Una DdA que exige de más en silencio, o que se come una alternativa entre
     * refuerzos, es lo que un auditor detecta con el Anexo II delante.
     * Declararlo convierte un fallo silencioso en una nota al pie.
     *
     * @return list<array<string, mixed>>
     */
    private function notasAnexoII(ContenidoDocumento $contenido): array
    {
        $notas = $contenido->extras['notasAnexoII'] ?? [];

        if (! is_array($notas) || $notas === []) {
            return [];
        }

        return [Nodo::de('limitaciones', [], [
            Nodo::lista(array_values(array_filter($notas, 'is_string'))),
        ])];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tablaMadurez(ContenidoDocumento $contenido): array
    {
        $marcos = $contenido->extras['madurezPorMarco'] ?? [];

        if (! is_array($marcos) || $marcos === []) {
            return [];
        }

        $filas = [Nodo::fila([
            Nodo::cabeceraCelda('Marco', '0.6in', 'col'),
            Nodo::cabeceraCelda('Nombre', '2.2in', 'col'),
            Nodo::cabeceraCelda('Exigibles', '1in', 'col'),
            Nodo::cabeceraCelda('Evaluadas', '1in', 'col'),
            Nodo::cabeceraCelda('Madurez media', null, 'col'),
        ])];

        foreach ($marcos as $marco) {
            if (! is_array($marco)) {
                continue;
            }

            $exigibles = $this->entero($marco, 'exigibles');
            $media = $marco['media'] ?? null;

            $filas[] = Nodo::fila([
                Nodo::celdaTexto($this->cadena($marco, 'codigo') ?? '—', 'codigo'),
                Nodo::celdaTexto($this->cadena($marco, 'nombre') ?? '—'),
                Nodo::celdaTexto((string) $exigibles),
                Nodo::celdaTexto($this->entero($marco, 'evaluadas').' de '.$exigibles),
                // Sin ninguna evaluada la media no es L0: es que no se sabe.
                Nodo::celdaTexto($media === null ? 'Sin evaluar' : 'L'.$media),
            ]);
        }

        return [Nodo::de('table', [], $filas)];
    }

    // --- Contexto de la organización (§ 4.1) ---------------------------------

    /**
     * El DAFO: una tabla por cuadrante, en el orden en que se lee la matriz.
     *
     * **Cuatro tablas y no una rejilla 2×2.** En pantalla el DAFO es una matriz
     * porque cabe de un vistazo; en papel, un cuadrante con doce cuestiones
     * partiría la rejilla a mitad de página y dejaría columnas huérfanas. Cuatro
     * tablas con su título cada una se parten limpio y conservan lo único que la
     * matriz aporta: qué cuadrante es cada cosa, escrito.
     *
     * **El ámbito y el signo van escritos bajo cada título.** En pantalla los
     * llevan la posición y el color; aquí no hay posición, y el color no puede
     * cargar solo con dos ejes. Es la misma regla de DESIGN.md § 11 aplicada al
     * papel.
     *
     * Un cuadrante vacío **se imprime igual**, con su línea de «ninguna». Un DAFO
     * sin oportunidades es una organización que sólo ha mirado lo que le puede
     * salir mal, y esconder el apartado esconde justamente eso.
     *
     * @return list<array<string, mixed>>
     */
    private function dafoCuadrantes(ContenidoDocumento $contenido): array
    {
        $cuadrantes = $contenido->extras['dafo'] ?? [];

        if (! is_array($cuadrantes) || $cuadrantes === []) {
            return [Nodo::parrafo(
                'No hay ninguna cuestión registrada en el análisis del contexto.',
                'vacio',
            )];
        }

        $nodos = [];

        foreach ($cuadrantes as $cuadrante) {
            if (! is_array($cuadrante)) {
                continue;
            }

            $cuestiones = $cuadrante['cuestiones'] ?? [];
            $cuestiones = is_array($cuestiones) ? $cuestiones : [];

            $nodos[] = Nodo::encabezado(3, $this->cadena($cuadrante, 'etiqueta') ?? '—', null, 'separado');
            $nodos[] = Nodo::de('paragraph', ['clase' => 'pequeno_suave'], [
                Nodo::texto(sprintf(
                    '%s · %s · %d %s',
                    $this->cadena($cuadrante, 'ambito') ?? '—',
                    $this->cadena($cuadrante, 'signo') ?? '—',
                    count($cuestiones),
                    count($cuestiones) === 1 ? 'cuestión' : 'cuestiones',
                )),
            ]);

            if ($cuestiones === []) {
                $nodos[] = Nodo::parrafo('Ninguna registrada.', 'vacio');

                continue;
            }

            $filas = [Nodo::fila([
                Nodo::cabeceraCelda('Cód.', '0.7in', 'col'),
                Nodo::cabeceraCelda('Cuestión', '3.6in', 'col'),
                Nodo::cabeceraCelda('Materia', '1.5in', 'col'),
                Nodo::cabeceraCelda('Responsable', '1.3in', 'col'),
                Nodo::cabeceraCelda('Riesgos', '1.6in', 'col'),
                Nodo::cabeceraCelda('Trabajo', '1.0in', 'col'),
            ])];

            foreach ($cuestiones as $cuestion) {
                if (! is_array($cuestion)) {
                    continue;
                }

                $riesgos = $cuestion['riesgos'] ?? [];
                $riesgos = is_array($riesgos) ? array_values(array_filter($riesgos, 'is_string')) : [];

                $titulo = $this->cadena($cuestion, 'titulo') ?? '—';
                $descripcion = $this->cadena($cuestion, 'descripcion');
                $clima = ($cuestion['esClimatica'] ?? false) === true;

                $celdaCuestion = [Nodo::texto($titulo, ['bold'])];

                if ($clima) {
                    $celdaCuestion[] = Nodo::texto(' · cambio climático', ['suave']);
                }

                if ($descripcion !== null) {
                    $celdaCuestion[] = Nodo::de('hardBreak');
                    $celdaCuestion[] = Nodo::texto($descripcion);
                }

                $filas[] = Nodo::fila([
                    Nodo::celdaTexto($this->cadena($cuestion, 'codigo') ?? '—', 'codigo'),
                    Nodo::celda($celdaCuestion),
                    Nodo::celdaTexto($this->cadena($cuestion, 'materiaEtiqueta') ?? '—'),
                    Nodo::celdaTexto($this->cadena($cuestion, 'responsable') ?? 'Sin asignar'),
                    // Los códigos y no un recuento: «R-014» dice cuál, y «1» no.
                    Nodo::celdaTexto($riesgos === [] ? 'Ninguno' : implode(', ', $riesgos), 'codigo'),
                    Nodo::celdaTexto($this->entero($cuestion, 'tareas') === 0
                        ? 'Nada apuntado'
                        : $this->entero($cuestion, 'tareas').' tarea(s)'),
                ]);
            }

            $nodos[] = Nodo::de('table', [], $filas);
        }

        return $nodos;
    }

    /**
     * Las partes interesadas y lo que exige cada una. Cláusula 4.2.
     *
     * **Una fila por requisito y el código de la parte repetido**, en vez de
     * agrupar con `rowspan`. Una celda combinada que cae justo en un salto de
     * página deja la mitad de la tabla sin cabecera de grupo, y en un documento de
     * archivo eso no se puede arreglar desplazándose.
     *
     * Una parte sin nada escrito **también sale**: es la que hay que mirar, porque
     * declarar a un regulador y no decir qué exige es la mitad de la cláusula.
     *
     * @return list<array<string, mixed>>
     */
    private function tablaPartesInteresadas(ContenidoDocumento $contenido): array
    {
        $partes = $contenido->extras['partes'] ?? [];

        if (! is_array($partes) || $partes === []) {
            return [Nodo::parrafo('No hay ninguna parte interesada registrada.', 'vacio')];
        }

        $filas = [Nodo::fila([
            Nodo::cabeceraCelda('Cód.', '0.6in', 'col'),
            Nodo::cabeceraCelda('Parte interesada', '1.8in', 'col'),
            Nodo::cabeceraCelda('Tipo', '1.3in', 'col'),
            Nodo::cabeceraCelda('Ámbito', '0.7in', 'col'),
            Nodo::cabeceraCelda('Qué exige o espera', '2.9in', 'col'),
            Nodo::cabeceraCelda('Naturaleza', '1.0in', 'col'),
            Nodo::cabeceraCelda('Cómo se atiende', '1.9in', 'col'),
        ])];

        foreach ($partes as $parte) {
            if (! is_array($parte)) {
                continue;
            }

            $requisitos = $parte['requisitos'] ?? [];
            $requisitos = is_array($requisitos) ? $requisitos : [];

            $identidad = [
                Nodo::celdaTexto($this->cadena($parte, 'codigo') ?? '—', 'codigo'),
                Nodo::celdaTexto($this->cadena($parte, 'nombre') ?? '—'),
                Nodo::celdaTexto($this->cadena($parte, 'tipoEtiqueta') ?? '—'),
                Nodo::celdaTexto($this->cadena($parte, 'ambitoEtiqueta') ?? '—'),
            ];

            if ($requisitos === []) {
                $filas[] = Nodo::fila([
                    ...$identidad,
                    Nodo::celdaTexto('Sin requisitos escritos'),
                    Nodo::celdaTexto('—'),
                    Nodo::celdaTexto('—'),
                ]);

                continue;
            }

            foreach ($requisitos as $requisito) {
                if (! is_array($requisito)) {
                    continue;
                }

                $filas[] = Nodo::fila([
                    ...$identidad,
                    Nodo::celda($this->celdaRequisito($requisito)),
                    Nodo::celdaTexto($this->cadena($requisito, 'naturalezaEtiqueta') ?? '—'),
                    Nodo::celda($this->celdaCobertura($requisito)),
                ]);
            }
        }

        return [Nodo::de('table', [], $filas)];
    }

    /**
     * Qué pide, con su referencia debajo si la tiene.
     *
     * @param  array<string, mixed>  $requisito
     * @return list<array<string, mixed>>
     */
    private function celdaRequisito(array $requisito): array
    {
        $celda = [Nodo::texto($this->cadena($requisito, 'descripcion') ?? '—')];

        if (($requisito['esClimatico'] ?? false) === true) {
            $celda[] = Nodo::texto(' · cambio climático', ['suave']);
        }

        $referencia = $this->cadena($requisito, 'referencia');

        if ($referencia !== null) {
            $celda[] = Nodo::de('hardBreak');
            $celda[] = Nodo::texto($referencia, ['cifra']);
        }

        return $celda;
    }

    /**
     * Las medidas que lo cubren, o la falta de ellas.
     *
     * **Sólo se señala la ausencia en lo que obliga.** Una expectativa sin medida
     * detrás no es una laguna; decirlo con las mismas palabras que un requisito
     * legal sin cubrir sería hacer que el documento señalara treinta cosas donde
     * hay tres.
     *
     * @param  array<string, mixed>  $requisito
     * @return list<array<string, mixed>>
     */
    private function celdaCobertura(array $requisito): array
    {
        $implantaciones = $requisito['implantaciones'] ?? [];
        $implantaciones = is_array($implantaciones) ? $implantaciones : [];

        if ($implantaciones === []) {
            $texto = $this->cadena($requisito, 'comoSeAtiende');

            if ($texto !== null) {
                return [Nodo::texto($texto)];
            }

            return ($requisito['obliga'] ?? false) === true
                ? [Nodo::texto('Sin ninguna medida registrada', ['bold'])]
                : [Nodo::texto('—')];
        }

        $celda = [];

        foreach ($implantaciones as $i => $implantacion) {
            if (! is_array($implantacion)) {
                continue;
            }

            if ($i > 0) {
                $celda[] = Nodo::de('hardBreak');
            }

            $celda[] = Nodo::texto($this->cadena($implantacion, 'requisito') ?? '—', ['cifra']);
            $celda[] = Nodo::texto(' · '.($this->cadena($implantacion, 'estadoEtiqueta') ?? '—'));
        }

        return $celda === [] ? [Nodo::texto('—')] : $celda;
    }

    /**
     * La determinación sobre el cambio climático, que exige la enmienda 1:2024.
     *
     * **Va en caja y no en una línea más.** La norma no pide apuntar cuestiones
     * climáticas: pide **determinar si** el cambio climático es pertinente, y es
     * de las primeras cosas que un auditor busca desde 2024. Una respuesta
     * enterrada en un párrafo se lee como que no está.
     *
     * Sin contestar **se dice**, en vez de omitir el apartado: la ausencia de la
     * declaración es exactamente el hallazgo.
     *
     * @return list<array<string, mixed>>
     */
    private function declaracionClimatica(ContenidoDocumento $contenido): array
    {
        $clima = $contenido->extras['clima'] ?? [];
        $clima = is_array($clima) ? $clima : [];

        $pertinente = $clima['pertinente'] ?? null;

        $respuesta = match ($pertinente) {
            true => 'Sí: el cambio climático es una cuestión pertinente para la organización.',
            false => 'No: el cambio climático no se considera una cuestión pertinente para la organización.',
            default => 'Sin determinar.',
        };

        $nodos = [
            Nodo::de('caja', ['variante' => 'marca'], [
                Nodo::encabezado(4, '¿Es pertinente el cambio climático?'),
                Nodo::de('paragraph', [], [Nodo::texto($respuesta, ['bold'])]),
            ]),
        ];

        $justificacion = $this->cadena($clima, 'justificacion');

        if ($justificacion !== null) {
            $nodos[] = Nodo::parrafo($justificacion);
        }

        return $nodos;
    }

    /**
     * El alcance declarado de cada sistema, congelado el día de la aprobación.
     *
     * Es la cláusula 4.3, y vive en `sistemas` desde la primera migración: aquí no
     * se reescribe, se copia. Lo que este documento le añade es la fecha —qué
     * decía **entonces**—, que es lo que ese campo no tenía.
     *
     * @return list<array<string, mixed>>
     */
    private function alcanceSistemas(ContenidoDocumento $contenido): array
    {
        $sistemas = $contenido->extras['alcance'] ?? [];

        if (! is_array($sistemas) || $sistemas === []) {
            return [Nodo::parrafo(
                'La organización no tiene ningún sistema activo, así que no hay alcance declarado que recoger.',
                'vacio',
            )];
        }

        $filas = [Nodo::fila([
            Nodo::cabeceraCelda('Cód.', '0.8in', 'col'),
            Nodo::cabeceraCelda('Sistema', '2.2in', 'col'),
            Nodo::cabeceraCelda('Marco', '1.0in', 'col'),
            Nodo::cabeceraCelda('Alcance declarado', '3.5in', 'col'),
            Nodo::cabeceraCelda('Exclusiones justificadas', '2.5in', 'col'),
        ])];

        foreach ($sistemas as $sistema) {
            if (! is_array($sistema)) {
                continue;
            }

            $filas[] = Nodo::fila([
                Nodo::celdaTexto($this->cadena($sistema, 'codigo') ?? '—', 'codigo'),
                Nodo::celdaTexto($this->cadena($sistema, 'nombre') ?? '—'),
                Nodo::celdaTexto($this->cadena($sistema, 'marco') ?? '—', 'codigo'),
                // «Sin declarar» y no una celda vacía: un alcance en blanco es un
                // hallazgo, y una celda vacía parece un error de maquetación.
                Nodo::celdaTexto($this->cadena($sistema, 'alcanceDeclarado') ?? 'Sin declarar'),
                Nodo::celdaTexto($this->cadena($sistema, 'exclusiones') ?? 'Ninguna declarada'),
            ]);
        }

        return [Nodo::de('table', [], $filas)];
    }

    // --- Revisión por la dirección (§ 4.15) ----------------------------------

    /**
     * La ficha de la reunión: cuándo, de qué periodo, quiénes y quién firmó.
     *
     * Va la primera del cuerpo porque es lo que identifica el acta. **El periodo
     * revisado es el dato que no se puede deducir de ninguna otra parte**: una
     * revisión del ejercicio pasado se celebra casi siempre en el siguiente, así
     * que la fecha de la reunión no dice de qué habla el acta.
     *
     * @return list<array<string, mixed>>
     */
    private function fichaRevision(ContenidoDocumento $contenido): array
    {
        $r = $contenido->extras['revision'] ?? [];

        if (! is_array($r)) {
            return [Nodo::parrafo('No hay ninguna revisión que recoger.', 'vacio')];
        }

        $filas = [
            Nodo::de('fichaFila', ['clave' => 'Revisión'], [Nodo::texto($this->cadena($r, 'codigo') ?? '—', ['cifra'])]),
            Nodo::de('fichaFila', ['clave' => 'Celebrada el'], [Nodo::texto($this->cadena($r, 'fecha') ?? '—')]),
            Nodo::de('fichaFila', ['clave' => 'Periodo revisado'], [Nodo::texto($this->cadena($r, 'periodo') ?? '—')]),
        ];

        // «Sin registrar» y no una fila ausente: quién asistió a una revisión por
        // la dirección es lo primero que un auditor comprueba, y una fila que no
        // está se lee como que el dato no aplica.
        $filas[] = Nodo::de('fichaFila', ['clave' => 'Asistentes'], [
            Nodo::texto($this->cadena($r, 'asistentes') ?? 'Sin registrar'),
        ]);

        $firmante = $this->cadena($r, 'aprobadaPor');
        $firmada = $this->cadena($r, 'aprobadaEn');

        $filas[] = Nodo::de('fichaFila', ['clave' => 'Acta aprobada por'], [
            Nodo::texto($firmante === null
                ? 'Sin firmar'
                : $firmante.($firmada === null ? '' : ' · '.$firmada)),
        ]);

        return [Nodo::de('ficha', [], $filas)];
    }

    /**
     * Las siete entradas de la cláusula 9.3.2, en el orden en que la norma las
     * enumera.
     *
     * **El orden es el de la norma y no el que quedaría mejor.** Un auditor
     * recorre la 9.3.2 de la a) a la g) con el acta delante, y reordenarlas le
     * obliga a buscar cada una.
     *
     * Todo sale de la instantánea, congelada al aprobar. Un cero es una entrada
     * recogida y no una entrada que falte: una organización puede llegar a su
     * primera revisión sin auditorías en el periodo, y eso es lo que el acta
     * tiene que decir.
     *
     * @return list<array<string, mixed>>
     */
    private function entradasRevision(ContenidoDocumento $contenido): array
    {
        $e = $contenido->extras['entradas'] ?? [];

        if (! is_array($e) || $e === []) {
            return [Nodo::parrafo(
                'No hay entradas congeladas para esta revisión. El acta se genera desde lo que se recogió al aprobarla.',
                'vacio',
            )];
        }

        return [
            ...$this->entradaAccionesPrevias($e),
            ...$this->entradaContexto($e),
            ...$this->entradaPartes($e),
            ...$this->entradaDesempeno($e),
            ...$this->entradaRiesgos($e),
            ...$this->entradaMejoras($e),
        ];
    }

    /**
     * a) El estado de las acciones de revisiones previas.
     *
     * @param  array<string, mixed>  $entradas
     * @return list<array<string, mixed>>
     */
    private function entradaAccionesPrevias(array $entradas): array
    {
        $bloque = $entradas['accionesPrevias'] ?? [];
        $bloque = is_array($bloque) ? $bloque : [];

        $nodos = [Nodo::encabezado(3, 'a) Estado de las acciones de revisiones previas')];

        $anterior = $bloque['revision'] ?? null;
        $acciones = is_array($bloque['acciones'] ?? null) ? $bloque['acciones'] : [];

        if (! is_array($anterior)) {
            $nodos[] = Nodo::parrafo(
                'Es la primera revisión por la dirección registrada, así que no hay acciones previas que comprobar.',
                'suave',
            );

            return $nodos;
        }

        $nodos[] = Nodo::parrafo(sprintf(
            'Decisiones de la revisión %s, celebrada el %s: %d de %d siguen abiertas.',
            $this->cadena($anterior, 'codigo') ?? '—',
            $this->cadena($anterior, 'fecha') ?? '—',
            (int) ($bloque['abiertas'] ?? 0),
            count($acciones),
        ), 'suave');

        if ($acciones !== []) {
            $nodos[] = $this->tablaDeAcciones($acciones);
        }

        return $nodos;
    }

    /**
     * b) Los cambios en las cuestiones internas y externas.
     *
     * @param  array<string, mixed>  $entradas
     * @return list<array<string, mixed>>
     */
    private function entradaContexto(array $entradas): array
    {
        $bloque = is_array($entradas['contexto'] ?? null) ? $entradas['contexto'] : [];
        $analisis = $bloque['analisis'] ?? null;

        $nodos = [Nodo::encabezado(3, 'b) Cambios en las cuestiones internas y externas')];

        if (! is_array($analisis)) {
            $nodos[] = Nodo::parrafo(
                'No hay ningún análisis del contexto aprobado, así que esta entrada no se pudo recoger de la herramienta.',
                'vacio',
            );

            return $nodos;
        }

        $nodos[] = Nodo::parrafo(sprintf(
            '%s, aprobado el %s, con %d cuestiones vigentes. El detalle figura en su propio documento.',
            $this->cadena($analisis, 'etiqueta') ?? '—',
            $this->cadena($analisis, 'fecha') ?? '—',
            (int) ($bloque['cuestiones'] ?? 0),
        ), 'suave');

        // El clima va aquí y no en un apartado propio: la enmienda 1:2024 lo pide
        // como cuestión del contexto, no como entrada aparte de la 9.3.
        $clima = is_array($bloque['clima'] ?? null) ? $bloque['clima'] : null;

        if ($clima !== null && array_key_exists('pertinente', $clima)) {
            $nodos[] = Nodo::parrafo(
                $clima['pertinente'] === true
                    ? 'El cambio climático se ha determinado pertinente para la organización.'
                    : 'El cambio climático se ha determinado no pertinente para la organización.',
                'suave',
            );
        }

        return $nodos;
    }

    /**
     * c) y e) Las partes interesadas: sus necesidades y su retroalimentación.
     *
     * **Las dos entradas comparten apartado y el acta lo dice**, porque Statera
     * sólo tiene la primera. Repartirlas en dos apartados con el mismo contenido
     * daría la impresión de que las dos están cubiertas.
     *
     * @param  array<string, mixed>  $entradas
     * @return list<array<string, mixed>>
     */
    private function entradaPartes(array $entradas): array
    {
        $bloque = is_array($entradas['partesInteresadas'] ?? null) ? $entradas['partesInteresadas'] : [];
        $partes = is_array($bloque['partes'] ?? null) ? $bloque['partes'] : [];

        $nodos = [Nodo::encabezado(3, 'c) y e) Partes interesadas: necesidades y retroalimentación')];

        if ($partes === []) {
            $nodos[] = Nodo::parrafo('No hay partes interesadas registradas.', 'vacio');

            return $nodos;
        }

        $filas = [Nodo::fila([
            Nodo::cabeceraCelda('Parte interesada', '3.0in', 'col'),
            Nodo::cabeceraCelda('Tipo', '1.8in', 'col'),
            Nodo::cabeceraCelda('Ámbito', '1.2in', 'col'),
            Nodo::cabeceraCelda('Requisitos', '1.0in', 'col'),
        ])];

        foreach ($partes as $parte) {
            if (! is_array($parte)) {
                continue;
            }

            $filas[] = Nodo::fila([
                Nodo::celdaTexto($this->cadena($parte, 'nombre') ?? '—'),
                Nodo::celdaTexto($this->cadena($parte, 'tipo') ?? '—'),
                Nodo::celdaTexto($this->cadena($parte, 'ambito') ?? '—'),
                Nodo::celdaTexto((string) (int) ($parte['requisitos'] ?? 0), 'cifra'),
            ]);
        }

        $nodos[] = Nodo::de('table', [], $filas);

        $nodos[] = Nodo::parrafo(
            'La retroalimentación de las partes interesadas —quejas, encuestas y comunicaciones recibidas— '
            .'no se registra en la herramienta y se aporta fuera de este documento.',
            'suave',
        );

        return $nodos;
    }

    /**
     * d) El desempeño y la eficacia del sistema de gestión.
     *
     * La entrada más larga porque la norma la desglosa en cuatro: no
     * conformidades, seguimiento y medición, auditorías y **cumplimiento de los
     * objetivos**.
     *
     * @param  array<string, mixed>  $entradas
     * @return list<array<string, mixed>>
     */
    private function entradaDesempeno(array $entradas): array
    {
        $d = is_array($entradas['desempeno'] ?? null) ? $entradas['desempeno'] : [];

        $nc = is_array($d['noConformidades'] ?? null) ? $d['noConformidades'] : [];
        $ind = is_array($d['indicadores'] ?? null) ? $d['indicadores'] : [];
        $aud = is_array($d['auditorias'] ?? null) ? $d['auditorias'] : [];
        $obj = is_array($d['objetivos'] ?? null) ? $d['objetivos'] : [];

        $nodos = [
            Nodo::encabezado(3, 'd) Desempeño y eficacia del sistema de gestión'),
            Nodo::de('cifras', [], [
                $this->cifra((string) (int) ($nc['abiertas'] ?? 0), 'de '.(int) ($nc['total'] ?? 0), 'No conformidades abiertas'),
                $this->cifra((string) (int) ($nc['sinVerificar'] ?? 0), null, 'Sin verificar la eficacia'),
                $this->cifra((string) (int) ($ind['fueraDeObjetivo'] ?? 0), 'de '.(int) ($ind['activos'] ?? 0), 'Indicadores fuera de objetivo'),
                $this->cifra((string) (int) ($ind['periodoSinMedir'] ?? 0), null, 'Con el periodo sin medir'),
                $this->cifra((string) (int) ($aud['total'] ?? 0), null, 'Auditorías en el periodo'),
                $this->cifra((string) (int) ($obj['vivos'] ?? 0), 'de '.(int) ($obj['total'] ?? 0), 'Objetivos en curso'),
            ]),
        ];

        $auditorias = is_array($aud['detalle'] ?? null) ? $aud['detalle'] : [];

        if ($auditorias === []) {
            $nodos[] = Nodo::parrafo('No se celebró ninguna auditoría dentro del periodo revisado.', 'suave');
        } else {
            $filas = [Nodo::fila([
                Nodo::cabeceraCelda('Cód.', '1.0in', 'col'),
                Nodo::cabeceraCelda('Tipo', '1.6in', 'col'),
                Nodo::cabeceraCelda('Fecha', '1.0in', 'col'),
                Nodo::cabeceraCelda('Estado', '1.2in', 'col'),
                Nodo::cabeceraCelda('Hallazgos', '1.0in', 'col'),
            ])];

            foreach ($auditorias as $auditoria) {
                if (! is_array($auditoria)) {
                    continue;
                }

                $filas[] = Nodo::fila([
                    Nodo::celdaTexto($this->cadena($auditoria, 'codigo') ?? '—', 'codigo'),
                    Nodo::celdaTexto($this->cadena($auditoria, 'tipo') ?? '—'),
                    Nodo::celdaTexto($this->cadena($auditoria, 'fecha') ?? '—'),
                    Nodo::celda([Nodo::badge(
                        $this->cadena($auditoria, 'tono') ?? 'no_iniciado',
                        $this->cadena($auditoria, 'estado') ?? '—',
                    )]),
                    Nodo::celdaTexto((string) (int) ($auditoria['hallazgos'] ?? 0), 'cifra'),
                ]);
            }

            $nodos[] = Nodo::de('table', [], $filas);
        }

        $objetivos = is_array($obj['detalle'] ?? null) ? $obj['detalle'] : [];

        if ($objetivos === []) {
            $nodos[] = Nodo::parrafo(
                'No hay objetivos de seguridad registrados. La cláusula 6.2 pide establecerlos.',
                'suave',
            );

            return $nodos;
        }

        $filas = [Nodo::fila([
            Nodo::cabeceraCelda('Cód.', '1.0in', 'col'),
            Nodo::cabeceraCelda('Objetivo', '3.4in', 'col'),
            Nodo::cabeceraCelda('Estado', '1.4in', 'col'),
            Nodo::cabeceraCelda('Evaluación', '1.2in', 'col'),
            Nodo::cabeceraCelda('Fecha', '1.0in', 'col'),
        ])];

        foreach ($objetivos as $objetivo) {
            if (! is_array($objetivo)) {
                continue;
            }

            $filas[] = Nodo::fila([
                Nodo::celdaTexto($this->cadena($objetivo, 'codigo') ?? '—', 'codigo'),
                Nodo::celdaTexto($this->cadena($objetivo, 'titulo') ?? '—'),
                Nodo::celda([Nodo::badge(
                    $this->cadena($objetivo, 'tono') ?? 'no_iniciado',
                    $this->cadena($objetivo, 'estado') ?? '—',
                )]),
                Nodo::celdaTexto($this->cadena($objetivo, 'avance') ?? '—'),
                Nodo::celdaTexto($this->cadena($objetivo, 'fecha') ?? '—'),
            ]);
        }

        $nodos[] = Nodo::de('table', [], $filas);

        return $nodos;
    }

    /**
     * f) Los resultados de la apreciación de riesgos y el estado del tratamiento.
     *
     * @param  array<string, mixed>  $entradas
     * @return list<array<string, mixed>>
     */
    private function entradaRiesgos(array $entradas): array
    {
        $r = is_array($entradas['riesgos'] ?? null) ? $entradas['riesgos'] : [];
        $total = (int) ($r['total'] ?? 0);

        $nodos = [Nodo::encabezado(3, 'f) Apreciación de riesgos y estado del tratamiento')];

        if ($total === 0) {
            $nodos[] = Nodo::parrafo('No hay riesgos registrados.', 'vacio');

            return $nodos;
        }

        $nodos[] = Nodo::de('cifras', [], [
            $this->cifra((string) $total, null, 'Riesgos registrados'),
            $this->cifra((string) (int) ($r['sobreUmbral'] ?? 0), 'de '.$total, 'Sobre el umbral de aceptación'),
            $this->cifra((string) (int) ($r['sinValorar'] ?? 0), 'de '.$total, 'Sin valorar'),
            $this->cifra((string) (int) ($r['sinAceptar'] ?? 0), null, 'Sobre el umbral y sin aceptar'),
            $this->cifra((string) (int) ($r['revisionVencida'] ?? 0), null, 'Con la reevaluación vencida'),
            $this->cifra((string) (int) ($r['residualSinRespaldo'] ?? 0), null, 'Residual sin salvaguarda'),
        ]);

        return $nodos;
    }

    /**
     * g) Las oportunidades de mejora continua.
     *
     * @param  array<string, mixed>  $entradas
     * @return list<array<string, mixed>>
     */
    private function entradaMejoras(array $entradas): array
    {
        $m = is_array($entradas['mejoras'] ?? null) ? $entradas['mejoras'] : [];
        $detalle = is_array($m['detalle'] ?? null) ? $m['detalle'] : [];

        $nodos = [Nodo::encabezado(3, 'g) Oportunidades de mejora continua')];

        $nodos[] = Nodo::parrafo(sprintf(
            '%d registradas, de las cuales %d siguen abiertas y %d todavía sin empezar.',
            (int) ($m['total'] ?? 0),
            (int) ($m['abiertas'] ?? 0),
            (int) ($m['sinEmpezar'] ?? 0),
        ), 'suave');

        if ($detalle === []) {
            return $nodos;
        }

        $filas = [Nodo::fila([
            Nodo::cabeceraCelda('Cód.', '1.0in', 'col'),
            Nodo::cabeceraCelda('Mejora', '3.6in', 'col'),
            Nodo::cabeceraCelda('Estado', '1.2in', 'col'),
            Nodo::cabeceraCelda('Origen', '1.6in', 'col'),
            Nodo::cabeceraCelda('Responsable', '1.6in', 'col'),
        ])];

        foreach ($detalle as $mejora) {
            if (! is_array($mejora)) {
                continue;
            }

            $filas[] = Nodo::fila([
                Nodo::celdaTexto($this->cadena($mejora, 'codigo') ?? '—', 'codigo'),
                Nodo::celdaTexto($this->cadena($mejora, 'titulo') ?? '—'),
                Nodo::celda([Nodo::badge(
                    $this->cadena($mejora, 'tono') ?? 'no_iniciado',
                    $this->cadena($mejora, 'estado') ?? '—',
                )]),
                Nodo::celdaTexto($this->cadena($mejora, 'origen') ?? '—'),
                Nodo::celdaTexto($this->cadena($mejora, 'responsable') ?? 'Sin asignar'),
            ]);
        }

        $nodos[] = Nodo::de('table', [], $filas);

        return $nodos;
    }

    /**
     * Las salidas de la revisión (9.3.3).
     *
     * **Se leen de la pivote y no de la instantánea**, a diferencia de todo lo
     * anterior, y es deliberado: son las salidas del acta y pueden crecer después
     * de firmarla —una decisión se ejecuta en las semanas siguientes—. Lo que se
     * congeló es lo que la dirección **tuvo delante**, no lo que mandó hacer.
     *
     * @return list<array<string, mixed>>
     */
    private function tablaDecisiones(ContenidoDocumento $contenido): array
    {
        $decisiones = $contenido->extras['decisiones'] ?? [];

        if (! is_array($decisiones) || $decisiones === []) {
            return [Nodo::parrafo(
                'La revisión no registró ninguna decisión. La cláusula 9.3.3 pide dejar constancia de las '
                .'decisiones relacionadas con oportunidades de mejora y con cambios en el sistema de gestión.',
                'vacio',
            )];
        }

        return [$this->tablaDeAcciones($decisiones)];
    }

    /**
     * La tabla de acciones, que se pinta igual en la entrada a) y en las salidas.
     *
     * Una sola función porque son lo mismo mirado desde dos actas distintas: las
     * decisiones de una revisión son las acciones previas de la siguiente. Con
     * dos copias, la de arriba y la de abajo acabarían discrepando en columnas.
     *
     * @param  array<int|string, mixed>  $acciones
     * @return array<string, mixed>
     */
    private function tablaDeAcciones(array $acciones): array
    {
        $filas = [Nodo::fila([
            Nodo::cabeceraCelda('Acción', '4.4in', 'col'),
            Nodo::cabeceraCelda('Estado', '1.4in', 'col'),
            Nodo::cabeceraCelda('Responsable', '1.8in', 'col'),
            Nodo::cabeceraCelda('Plazo', '1.0in', 'col'),
        ])];

        foreach ($acciones as $accion) {
            if (! is_array($accion)) {
                continue;
            }

            $filas[] = Nodo::fila([
                Nodo::celdaTexto($this->cadena($accion, 'titulo') ?? '—'),
                Nodo::celda([Nodo::badge(
                    $this->cadena($accion, 'tono') ?? 'no_iniciado',
                    $this->cadena($accion, 'estado') ?? '—',
                )]),
                Nodo::celdaTexto($this->cadena($accion, 'responsable') ?? 'Sin asignar'),
                Nodo::celdaTexto($this->cadena($accion, 'fecha') ?? 'Sin plazo'),
            ]);
        }

        return Nodo::de('table', [], $filas);
    }

    // --- Declaración de Conformidad (§ 4.17) --------------------------------

    /**
     * La frase que el documento existe para decir.
     *
     * **Se construye, no se redacta**, y se vuelve a pedir en cada generación
     * (`EsquemaCuerpo::SIEMPRE_RECALCULADOS`): quién declara, qué sistema, qué
     * categoría y sobre qué autoevaluación son identificación y tienen que
     * coincidir con el registro.
     *
     * @return list<array<string, mixed>>
     */
    private function declaracionFormal(ContenidoDocumento $contenido): array
    {
        $d = $contenido->extras['declaracion'] ?? null;

        if (! is_array($d)) {
            return [Nodo::parrafo('No hay ninguna declaración de conformidad iniciada que recoger.', 'vacio')];
        }

        $organizacion = $this->cadena($d, 'organizacion') ?? 'La organización';
        $cif = $this->cadena($d, 'cif');
        $sistema = trim(($this->cadena($d, 'sistemaCodigo') ?? '').' '.($this->cadena($d, 'sistemaNombre') ?? ''));
        $categoria = mb_strtoupper($this->cadena($d, 'categoria') ?? '—');
        $autoevaluacion = $this->cadena($d, 'autoevaluacion') ?? '—';
        $cierre = $this->cadena($d, 'fechaCierre');

        $frase = '**'.$organizacion.'**'.($cif === null ? '' : ', con CIF '.$cif.',')
            .' declara que el sistema de información **'.$sistema.'**, de categoría **'.$categoria.'**, '
            .'es conforme con el Esquema Nacional de Seguridad, regulado por el Real Decreto 311/2022, '
            .'de 3 de mayo, según la autoevaluación **'.$autoevaluacion.'**'
            .($cierre === null ? '' : ', cerrada el '.$cierre).'.';

        $firmante = $this->cadena($d, 'firmante');
        $fechaFirma = $this->cadena($d, 'fechaFirma');
        $vigente = $this->cadena($d, 'vigenteHasta');

        $pie = $firmante === null
            ? 'Pendiente de firma. La declaración surte efecto cuando se aprueba y se emite esta versión.'
            : 'Firmada por '.$firmante.($fechaFirma === null ? '' : ' el '.$fechaFirma)
                .($vigente === null ? '.' : '. Vigente hasta el '.$vigente.', salvo que se retire antes.');

        return [
            Nodo::de('caja', ['variante' => 'marca'], [
                Nodo::parrafoRico($frase),
            ]),
            Nodo::parrafo($pie, 'suave'),
        ];
    }

    /**
     * De dónde sale la declaración: la autoevaluación que la respalda.
     *
     * «Sin registrar» y no una fila ausente, como en la ficha de la reunión:
     * quién hizo la autoevaluación es lo primero que se comprueba.
     *
     * @return list<array<string, mixed>>
     */
    private function fichaAutoevaluacion(ContenidoDocumento $contenido): array
    {
        $d = $contenido->extras['declaracion'] ?? null;

        if (! is_array($d)) {
            return [Nodo::parrafo('No hay ninguna autoevaluación que recoger.', 'vacio')];
        }

        $filas = [
            Nodo::de('fichaFila', ['clave' => 'Autoevaluación'], [Nodo::texto($this->cadena($d, 'autoevaluacion') ?? '—', ['cifra'])]),
            Nodo::de('fichaFila', ['clave' => 'Realizada el'], [Nodo::texto($this->cadena($d, 'fechaAutoevaluacion') ?? '—')]),
            Nodo::de('fichaFila', ['clave' => 'Cerrada el'], [Nodo::texto($this->cadena($d, 'fechaCierre') ?? '—')]),
            Nodo::de('fichaFila', ['clave' => 'Realizada por'], [Nodo::texto($this->cadena($d, 'auditor') ?? 'Sin registrar')]),
            Nodo::de('fichaFila', ['clave' => 'Categoría declarada'], [Nodo::texto($this->cadena($d, 'categoria') ?? '—', ['bold'])]),
        ];

        $alcance = $this->cadena($d, 'alcanceAuditado');

        if ($alcance !== null) {
            $filas[] = Nodo::de('fichaFila', ['clave' => 'Alcance auditado'], [Nodo::texto($alcance)]);
        }

        return [Nodo::de('ficha', [], $filas)];
    }

    /**
     * El resultado, contado sobre la checklist congelada y con su denominador.
     *
     * **En texto y no en badges**: «no conforme» y «no conformidad mayor» gastan
     * el rojo en la aplicación, y el documento no lo tiene entre sus tonos
     * (`EsquemaCuerpo::TONOS_BADGE`). Pintarlos en gris los igualaría a una
     * medida fuera de muestra, que es peor que no colorear ninguno.
     *
     * @return list<array<string, mixed>>
     */
    private function resultadoAutoevaluacion(ContenidoDocumento $contenido): array
    {
        $r = $contenido->extras['resultado'] ?? null;

        if (! is_array($r) || $this->entero($r, 'total') === 0) {
            return [Nodo::parrafo('La autoevaluación no tiene checklist: no hay medidas revisadas que contar.', 'vacio')];
        }

        $total = $this->entero($r, 'total');

        $filas = [Nodo::fila([
            Nodo::cabeceraCelda('Resultado', null, 'col'),
            Nodo::cabeceraCelda('Medidas', '1.2in', 'col'),
        ])];

        foreach (is_array($r['puntos'] ?? null) ? $r['puntos'] : [] as $punto) {
            if (! is_array($punto)) {
                continue;
            }

            $filas[] = Nodo::fila([
                Nodo::celdaTexto($this->cadena($punto, 'etiqueta') ?? '—'),
                Nodo::celda([Nodo::texto((string) $this->entero($punto, 'total'), ['cifra'])]),
            ]);
        }

        $filas[] = Nodo::fila([
            Nodo::celda([Nodo::texto('Total de medidas de la checklist', ['bold'])]),
            Nodo::celda([Nodo::texto((string) $total, ['bold', 'cifra'])]),
        ]);

        $hallazgos = [Nodo::fila([
            Nodo::cabeceraCelda('Hallazgo', null, 'col'),
            Nodo::cabeceraCelda('Registrados', '1.2in', 'col'),
        ])];

        foreach (is_array($r['hallazgos'] ?? null) ? $r['hallazgos'] : [] as $hallazgo) {
            if (! is_array($hallazgo)) {
                continue;
            }

            $hallazgos[] = Nodo::fila([
                Nodo::celdaTexto($this->cadena($hallazgo, 'etiqueta') ?? '—'),
                Nodo::celda([Nodo::texto((string) $this->entero($hallazgo, 'total'), ['cifra'])]),
            ]);
        }

        return [
            Nodo::de('table', [], $filas),
            Nodo::encabezado(3, 'Hallazgos', null, 'separado'),
            Nodo::de('table', [], $hallazgos),
        ];
    }

    // --- Cierre -------------------------------------------------------------

    /**
     * Lo que este documento NO puede afirmar hoy, y por qué.
     *
     * Se declara expresamente en lugar de omitirse, y no se puede borrar desde
     * ninguna parte: este bloque se vuelve a pedir en cada generación, así que
     * quitarlo en el editor no lo quita del PDF. Lo que la organización quiera
     * añadir va debajo como texto libre, que es lo que permite añadir sin
     * necesidad de borrar.
     *
     * @param  list<string>  $tocados
     * @return list<array<string, mixed>>
     */
    private function limitaciones(ContenidoDocumento $contenido, bool $editado, array $tocados): array
    {
        $puntos = $contenido->limitaciones;

        if ($editado) {
            $puntos[] = $this->declaracionDeEdicion($tocados);
        }

        if ($puntos === []) {
            return [Nodo::parrafo(
                'No hay ninguna limitación que declarar: el registro está completo para todo lo que este '.
                'documento afirma.',
                'vacio',
            )];
        }

        return [
            Nodo::parrafoRico(
                'Lo que este documento **no** puede afirmar hoy, y por qué. Se declara expresamente en '.
                'lugar de omitirse.',
                'suave',
            ),
            Nodo::de('limitaciones', [], [Nodo::lista($puntos)]),
        ];
    }

    /**
     * La frase que convierte un documento retocado en uno que se puede auditar.
     *
     * No se redacta: se construye. Y no se puede quitar, porque este bloque se
     * vuelve a pedir en cada generación pase lo que pase en el editor. Un
     * auditor respeta un documento que dice cómo se hizo; el que no lo dice es
     * el que suspende.
     *
     * @param  list<string>  $tocados
     */
    private function declaracionDeEdicion(array $tocados): string
    {
        if ($tocados === []) {
            return '**Este documento se ha editado a mano.** El texto de presentación lo ha redactado la '.
                'organización; los apartados calculados siguen siendo los que generó Statera desde el '.
                'registro de implantaciones.';
        }

        $nombres = array_map(
            static fn (string $fuente): string => self::NOMBRES_DE_BLOQUE[$fuente] ?? $fuente,
            $tocados,
        );

        return '**Este documento se ha editado a mano.** '.count($nombres).' apartados calculados se han '.
            'modificado después de generarse: '.implode(', ', $nombres).'. Lo que figura en ellos puede no '.
            'corresponder con lo que la herramienta tiene registrado hoy.';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function controlVersiones(ContenidoDocumento $contenido): array
    {
        if ($contenido->historial === []) {
            $nodos = [Nodo::parrafo('No hay versiones emitidas anteriores. Ésta sería la primera entrega.', 'vacio')];
        } else {
            $filas = [Nodo::fila([
                Nodo::cabeceraCelda('Versión', '0.7in', 'col'),
                Nodo::cabeceraCelda('Emitida', '1in', 'col'),
                Nodo::cabeceraCelda('Emitida por', '1.6in', 'col'),
                Nodo::cabeceraCelda('Motivo', null, 'col'),
                Nodo::cabeceraCelda('SHA-256', '3.4in', 'col'),
            ])];

            foreach ($contenido->historial as $version) {
                $filas[] = Nodo::fila([
                    Nodo::celdaTexto('v'.$this->entero($version, 'numero'), 'codigo'),
                    Nodo::celdaTexto($this->cadena($version, 'emitida') ?? '—'),
                    Nodo::celdaTexto($this->cadena($version, 'quien') ?? '—'),
                    Nodo::celdaTexto($this->cadena($version, 'motivo') ?? '—'),
                    Nodo::celdaTexto($this->cadena($version, 'huella') ?? '—', 'huella'),
                ]);
            }

            $nodos = [Nodo::de('table', [], $filas)];
        }

        // La huella de ESTA versión no puede ir aquí: sería autorreferencia,
        // porque el SHA-256 se calcula sobre el PDF ya generado.
        $nodos[] = Nodo::de('paragraph', ['clase' => 'pequeno_suave', 'estilo' => 'nota_al_pie'], [
            Nodo::texto(
                'La huella SHA-256 de esta versión se calcula sobre el PDF ya generado, así que no puede '.
                'figurar dentro de él. Consta en el registro del documento en Statera, y es con ella con la '.
                'que se comprueba que este fichero es el que se emitió.'
            ),
        ]);

        return $nodos;
    }

    // --- Lectura defensiva de los arrays del contenido ----------------------

    /**
     * @param  array<string, mixed>  $datos
     */
    private function cadena(array $datos, string $clave): ?string
    {
        $valor = $datos[$clave] ?? null;

        if (is_int($valor)) {
            return (string) $valor;
        }

        return is_string($valor) && $valor !== '' ? $valor : null;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function entero(array $datos, string $clave): int
    {
        $valor = $datos[$clave] ?? 0;

        return is_int($valor) ? $valor : 0;
    }
}
