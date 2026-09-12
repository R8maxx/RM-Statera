<?php

declare(strict_types=1);

namespace App\Domain\Documento\Cuerpo;

use App\Domain\Documento\Contenido\ContenidoDocumento;
use App\Domain\Documento\Contenido\FilaRequisito;
use App\Domain\Documento\Enums\TipoDocumento;

/**
 * Rellena los huecos calculados del cuerpo con lo que dice el registro.
 *
 * Es el puente entre las dos mitades del producto: `DeclaracionAplicabilidad`
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
        'resumen_grafica' => 'la gráfica del resumen',
        'tabla_requisitos' => 'la tabla de requisitos',
        'tabla_exclusiones' => 'la tabla de exclusiones',
        'tabla_derivacion' => 'la derivación de la categoría',
        'notas_anexo_ii' => 'las notas del Anexo II',
        'tabla_madurez' => 'la madurez por marco',
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
            'portada_pie' => $this->portadaPie($editado),
            'resumen_cifras' => $this->resumenCifras($contenido, $tipo),
            'resumen_grafica' => $this->resumenGrafica($contenido),
            'tabla_requisitos' => $this->tablaRequisitos($contenido, $tipo),
            'tabla_exclusiones' => $this->tablaExclusiones($contenido),
            'tabla_derivacion' => $this->tablaDerivacion($contenido),
            'notas_anexo_ii' => $this->notasAnexoII($contenido),
            'tabla_madurez' => $this->tablaMadurez($contenido),
            'limitaciones_sistema' => $this->limitaciones($contenido, $editado, $tocados),
            'control_versiones' => $this->controlVersiones($contenido),
            default => [],
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

        $sistemaCodigo = $this->cadena($p, 'sistemaCodigo');
        $sistema = $sistemaCodigo === null
            ? [Nodo::texto($this->cadena($p, 'sistemaNombre') ?? '—')]
            : [Nodo::texto($sistemaCodigo, ['cifra']), Nodo::texto(' · '.($this->cadena($p, 'sistemaNombre') ?? '—'))];

        $filas[] = Nodo::de('fichaFila', ['clave' => 'Sistema'], $sistema);

        $marco = $this->cadena($p, 'marco') ?? '—';
        $version = $this->cadena($p, 'marcoVersion');

        $filas[] = Nodo::de('fichaFila', ['clave' => 'Marco'], [
            Nodo::texto($version === null ? $marco : $marco.' ('.$version.')'),
        ]);

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
    private function portadaPie(bool $editado): array
    {
        $frase = $editado
            ? 'Statera — un producto de RM Technology. Este documento se generó desde el registro de '.
              'implantaciones y después se editó a mano. Los apartados calculados que se hayan '.
              'modificado, o que ya no coincidan con el registro, figuran en el apartado de limitaciones.'
            : 'Statera — un producto de RM Technology. '.
              'Las tablas, las cifras y la derivación de la categoría se generan desde el registro de '.
              'implantaciones y no se mantienen a mano; los textos de presentación los redacta la '.
              'organización.';

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

            default => Nodo::celdaTexto('—'),
        };
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
