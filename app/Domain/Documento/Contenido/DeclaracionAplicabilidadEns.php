<?php

declare(strict_types=1);

namespace App\Domain\Documento\Contenido;

use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Sistema\Models\ValoracionDimension;
use App\Http\Resources\Implantacion\Correspondencia;

/**
 * La Declaración de Aplicabilidad del ENS (RD 311/2022, Anexo II).
 *
 * **Aquí la aplicabilidad no es una decisión, es un cálculo**: el usuario valora
 * las cinco dimensiones, el sistema deriva la categoría y de ahí el conjunto
 * exigible (invariante 4). Lo que el auditor comprueba no es que alguien haya
 * marcado bien las casillas —no hay casillas—, sino que la derivación se
 * sostiene. Por eso este documento imprime la valoración dimensión a dimensión
 * **con su justificación**, la categoría resultante y, en cada medida, de dónde
 * sale su exigencia.
 *
 * Y por eso las dos brechas conocidas del modelo se imprimen en el propio
 * documento: una DdA que exige de más en silencio, o que se come una
 * alternativa entre refuerzos, es exactamente lo que un auditor detecta.
 */
final class DeclaracionAplicabilidadEns extends DocumentoCalculado
{
    use Concerns\ArmaFilaDelAnexoII;
    use Concerns\ResumeLaAplicabilidad;

    public function tipo(): TipoDocumento
    {
        return TipoDocumento::DdaEns;
    }

    protected function tipoDeRequisito(): string
    {
        return 'medida';
    }

    public function construir(Documento $documento, DocumentoVersion $version, array $parametros = []): ContenidoDocumento
    {
        $implantaciones = $this->implantaciones($documento);
        $correspondencias = $this->correspondenciasDe($implantaciones);

        $filas = $implantaciones
            ->map(fn (Implantacion $i): FilaRequisito => $this->fila($i, $correspondencias))
            ->values()
            ->all();

        $sistema = $documento->sistema;
        $organizacion = $documento->organizacion;

        return new ContenidoDocumento(
            // El título del registro, no una constante: hasta ahora se podía
            // editar en el formulario y el documento seguía diciendo otra cosa.
            titulo: $documento->titulo,
            subtitulo: 'Esquema Nacional de Seguridad — RD 311/2022, Anexo II',
            portada: [
                ...$this->portadaBase($documento, $version),
                // `organizacion_id` es NOT NULL: la relación siempre está.
                'sujetoObligado' => (bool) $organizacion->sujeto_obligado_ens,
                'proveedorSectorPublico' => (bool) $organizacion->proveedor_sector_publico,
                'alcance' => $sistema?->alcance_declarado,
                'categoria' => $sistema?->categoria()?->etiqueta(),
            ],
            resumen: $this->resumen($documento, $filas),
            filas: $filas,
            limitaciones: [
                'El **análisis de riesgos** (`op.pl.1`) se gestiona en la herramienta y **no se '
                .'reproduce en este documento**: una Declaración de Aplicabilidad declara medidas, no '
                .'riesgos. La herramienta **todavía no genera el documento de análisis y tratamiento '
                .'de riesgos**, que ha de aportarse por separado. El **plan de adecuación** sí se '
                .'genera, en documento aparte: **no figura aquí por diseño**, porque una Declaración '
                .'de Aplicabilidad declara la situación y no el calendario de las medidas que faltan.',

                /*
                 * **Quinta reescritura de una limitación de este documento.**
                 * Decía que los roles ENS estaban «pendientes de designación en
                 * la herramienta (módulo de personas, § 4.8)», y con el § 4.8
                 * dentro eso pasó a ser **falso en el PDF que se le entrega al
                 * auditor** — que es peor que una limitación ausente, y es el
                 * mismo tratamiento que ya se les dio a las dos de riesgos, a la
                 * del flujo de aprobación, a la del plan y a la de auditorías.
                 *
                 * Lo que queda dicho es lo que de verdad sigue sin hacerse, y
                 * `ContenidoDdaTest` clava que la frase vieja no vuelve **y** que
                 * la nueva sigue declarando lo que falta.
                 */
                'Los **roles ENS** —responsable de la información, del servicio, de seguridad, del '
                .'sistema y administrador de la seguridad del sistema— **se designan en la '
                .'herramienta**, por sistema y con vigencia, y sus nombramientos **no figuran aquí '
                .'por diseño**: una Declaración de Aplicabilidad declara la situación de cada '
                .'medida, no quién responde de ella. Lo que la herramienta **no** hace es comprobar '
                .'que el nombramiento esté firmado por quien tiene potestad para hacerlo, ni que la '
                .'persona designada reúna la competencia que `mp.per.1` exige. Impide, eso sí, que '
                .'el responsable de seguridad y el responsable del sistema recaigan en la misma '
                .'persona dentro del mismo sistema.',

                /*
                 * Cuarta reescritura de una limitación de este documento, y por
                 * el mismo motivo que las tres anteriores —las dos de riesgos con
                 * el § 4.3, la del flujo de aprobación con el § 4.5 y la del plan
                 * con el § 4.18—: decir que el módulo de auditorías no está
                 * implantado pasó a ser **falso en el PDF que se le entrega al
                 * auditor** en cuanto llegaron el § 4.12 y el § 4.13, y una
                 * limitación inventada se suspende; una declarada, se respeta.
                 *
                 * Lo que queda dicho es lo que de verdad sigue sin hacerse, y el
                 * último punto es el que importa: sin comprobar la cobertura del
                 * muestreo, «esta medida no tiene hallazgos» se lee como «esta
                 * medida se auditó y estaba conforme». Es el mismo argumento que
                 * hace que un punto de la checklist distinga `pendiente` de
                 * `conforme`.
                 */
                /*
                 * **Y quinta con el § 4.18**, que trajo el informe de auditoría
                 * como documento: decir que no se genera pasó a ser falso en el
                 * PDF entregado. Se dice dónde está y por qué no aquí, igual que
                 * con el plan de adecuación.
                 */
                'Las **auditorías y autoevaluaciones** se registran en la herramienta, con su '
                .'checklist, sus hallazgos y el tratamiento de las no conformidades, y las internas y '
                .'las autoevaluaciones tienen su **informe de auditoría en documento aparte**; su '
                .'resultado **no figura aquí por '
                .'diseño**, porque una Declaración de Aplicabilidad declara la situación de cada '
                .'medida y no el resultado de quien la revisó. Lo que la herramienta **todavía no '
                .'hace**: '
                /*
                 * **Precisada, no borrada, con el § 4.16.** El calendario avisa
                 * ya de la auditoría que toca, pero avisar no es programar: la
                 * 9.2.2 llama programa a planificar alcance, criterios y método,
                 * y eso sigue sin hacerse. Decir que el programa está hecho
                 * porque hay un aviso sería afirmar de más en un entregable.
                 */
                .'llevar el programa anual de auditoría —el calendario de obligaciones (§ 4.16) avisa '
                .'de la auditoría que toca, pero no planifica su alcance, sus criterios ni su '
                .'método—, y **comprobar que el alcance auditado '
                .'cubra las medidas exigibles** — de modo que la ausencia de hallazgos sobre una '
                .'medida no significa que se haya revisado.',

                ...$this->limitacionesBase($version, $filas),
            ],
            historial: $this->historialDe($documento),
            textos: $this->textosDe($documento),
            extras: [
                'derivacion' => $this->derivacion($documento),
                'madurezPorMarco' => $this->madurezPorMarco($filas),
                'notasAnexoII' => $this->notasAnexoII(),
            ],
        );
    }

    /** @param  array<int, list<Correspondencia>>  $correspondencias */
    protected function fila(Implantacion $implantacion, array $correspondencias): FilaRequisito
    {
        // Los cuatro campos del plan de adecuación se quedan a nulo: una
        // declaración dice cómo está una medida, no qué se va a hacer con ella.
        return $this->filaDelAnexoII($implantacion, $correspondencias);
    }

    /**
     * La derivación de la categoría, impresa literal.
     *
     * Es el corazón de la DdA: el invariante 4 hecho papel. Enseñar la categoría
     * sin las cinco dimensiones de las que sale obligaría al auditor a fiarse, y
     * un auditor no se fía, comprueba.
     *
     * @return array<string, mixed>
     */
    private function derivacion(Documento $documento): array
    {
        $sistema = $documento->sistema;

        if ($sistema === null) {
            return [];
        }

        /*
         * En el orden del Anexo I —C, I, D, A, T—, que es el del enum y el que
         * usa el ENS en todas partes. Tal como vienen de la base salen por id o
         * en alfabético, y una DdA que empiece por autenticidad se lee mal antes
         * de que nadie sepa decir por qué.
         */
        $porDimension = $sistema->valoraciones->keyBy(
            fn (ValoracionDimension $valoracion): string => $valoracion->dimension->value,
        );

        $dimensiones = [];

        foreach (Dimension::cases() as $dimension) {
            $valoracion = $porDimension->get($dimension->value);

            if (! $valoracion instanceof ValoracionDimension) {
                continue;
            }

            $dimensiones[] = [
                'codigo' => $dimension->value,
                'nombre' => $dimension->nombre(),
                'nivel' => $valoracion->nivel->etiqueta(),
                'justificacion' => $valoracion->justificacion,
            ];
        }

        $formula = implode(', ', array_map(
            static fn (array $d): string => "{$d['codigo']}: ".mb_strtolower($d['nivel']),
            $dimensiones,
        ));

        $categoria = $sistema->categoria();

        return [
            'dimensiones' => $dimensiones,
            'categoria' => $categoria?->etiqueta(),
            'formula' => $categoria === null
                ? null
                : 'Categoría '.mb_strtoupper($categoria->etiqueta())." = máximo de ({$formula})",
        ];
    }

    /**
     * La madurez media de cada marco del Anexo II, con su denominador.
     *
     * Separada por `org`, `op` y `mp` porque es como la mira INES, y con
     * denominador porque una media sobre cuatro medidas de setenta y tres no
     * dice lo mismo que sobre las setenta y tres.
     *
     * @param  list<FilaRequisito>  $filas
     * @return list<array<string, mixed>>
     */
    private function madurezPorMarco(array $filas): array
    {
        $nombres = [
            'org' => 'Marco organizativo',
            'op' => 'Marco operacional',
            'mp' => 'Medidas de protección',
        ];

        $resultado = [];

        foreach ($nombres as $prefijo => $nombre) {
            $delMarco = array_filter(
                $filas,
                static fn (FilaRequisito $f): bool => str_starts_with($f->codigo, $prefijo.'.') || $f->codigo === $prefijo,
            );

            $exigibles = array_filter($delMarco, static fn (FilaRequisito $f): bool => $f->aplica);
            $valoradas = array_filter($exigibles, static fn (FilaRequisito $f): bool => $f->madurezValor !== null);

            $resultado[] = [
                'codigo' => $prefijo,
                'nombre' => $nombre,
                'exigibles' => count($exigibles),
                'evaluadas' => count($valoradas),
                'media' => $valoradas === []
                    ? null
                    : round(array_sum(array_map(
                        static fn (FilaRequisito $f): int => (int) $f->madurezValor,
                        $valoradas,
                    )) / count($valoradas), 1),
            ];
        }

        return $resultado;
    }

    /**
     * Las dos brechas de modelo que el propio catálogo declara.
     *
     * Van impresas en el documento y no escondidas en un comentario del código.
     * La primera exige de más y nunca de menos, que es el lado seguro del error;
     * la segunda registra lo que se exige con seguridad. Las dos son
     * comprobables por el auditor con el Anexo II delante, así que declararlas
     * es lo que convierte un fallo silencioso en una nota al pie.
     *
     * @return list<string>
     */
    private function notasAnexoII(): array
    {
        return [
            'Nueve medidas del Anexo II están moduladas por **varias dimensiones a la vez** '
            .'(`op.acc.1`; `op.acc.2` a `op.acc.6`; `mp.com.3`; `mp.info.3`; `mp.si.2`). El modelo '
            .'registra una sola dimensión moduladora, así que estas medidas se leen **por categoría** '
            .'—el máximo de las cinco dimensiones—. El efecto está acotado y es conocido: puede '
            .'**exigir de más, nunca de menos**.',

            'Diez celdas de cuatro medidas (`op.acc.5`, `op.acc.6`, `mp.com.4`, `mp.s.2`) exigen un '
            .'refuerzo **a elegir** entre varios («+ [R1 o R2]»). El modelo guarda un único refuerzo '
            .'por celda, de modo que aquí queda registrado **lo que se exige con seguridad**; la '
            .'elección entre alternativas se documenta aparte.',

            'Los refuerzos del Anexo II **se acumulan**: «R2» se lee como «hasta R2», no como '
            .'«sólo R2».',
        ];
    }
}
