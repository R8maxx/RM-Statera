<?php

declare(strict_types=1);

namespace App\Domain\Documento\Contenido;

use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Documento\Narrativa\MarkdownDocumento;
use App\Domain\Documento\Narrativa\ResolverNarrativa;
use App\Domain\Implantacion\CorrespondenciasCruzadas;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Models\Implantacion;
use App\Http\Resources\Implantacion\Correspondencia;
use App\Http\Resources\Panel\SegmentoEstado;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Lo que comparten la SoA de ISO y la DdA del ENS.
 *
 * Las dos son consultas sobre `implantaciones` —nunca documentos mantenidos a
 * mano— y las dos contestan a las mismas tres preguntas: qué aplica, cómo se
 * cumple y dónde está la prueba. Lo que cambia es la naturaleza de la primera,
 * y por eso cada subclase arma sus propias columnas.
 */
abstract class DeclaracionAplicabilidad implements GeneradorDocumento
{
    public function __construct(
        protected readonly CorrespondenciasCruzadas $correspondencias,
        protected readonly ResolverNarrativa $narrativa,
        protected readonly MarkdownDocumento $markdown,
    ) {}

    /**
     * Los textos que la organización ha redactado para este documento.
     *
     * Resuelve por la cadena documento → plantilla → fábrica, así que un
     * documento que nunca haya pasado por el editor sale con el texto de fábrica
     * y su PDF es idéntico al que salía antes de que esto existiera.
     */
    protected function textosDe(Documento $documento): TextosDocumento
    {
        return TextosDocumento::desdeMarkdown(
            $this->narrativa->paraDocumento($documento),
            $this->markdown,
        );
    }

    /** `control` para el Anexo A de ISO, `medida` para el Anexo II del ENS. */
    abstract protected function tipoDeRequisito(): string;

    /** @param  array<int, list<Correspondencia>>  $correspondencias */
    abstract protected function fila(Implantacion $implantacion, array $correspondencias): FilaRequisito;

    /**
     * Las implantaciones del sistema, en el orden del marco.
     *
     * **Se filtra por tipo de requisito y no se coge todo lo que cuelga del
     * sistema**: `GeneradorImplantaciones` materializa también las cláusulas 4 a
     * 10 de ISO, que son el sistema de gestión y no controles del Anexo A. Una
     * SoA que listara «6.1.2 Apreciación de riesgos» como control es un
     * hallazgo, no una fila de más.
     *
     * `orden` y no `codigo`: ordenar por el texto pondría `op.acc.10` antes que
     * `op.acc.2`.
     *
     * @return Collection<int, Implantacion>
     */
    protected function implantaciones(Documento $documento): Collection
    {
        return Implantacion::query()
            ->select('implantaciones.*')
            ->join('requisitos', 'requisitos.id', '=', 'implantaciones.requisito_id')
            ->where('implantaciones.sistema_id', $documento->sistema_id)
            ->where('requisitos.tipo', $this->tipoDeRequisito())
            // Sin esto, noventa y tres controles son doscientas consultas y el
            // documento se come el tiempo de la cola.
            ->with(['requisito.padre', 'requisito.marco', 'responsable', 'evidencias'])
            ->orderBy('requisitos.orden')
            ->orderBy('requisitos.id')
            ->get();
    }

    /**
     * Las correspondencias de todas las filas, en dos consultas.
     *
     * @param  Collection<int, Implantacion>  $implantaciones
     * @return array<int, list<Correspondencia>>
     */
    protected function correspondenciasDe(Collection $implantaciones): array
    {
        return $this->correspondencias->paraRequisitos(
            $implantaciones->pluck('requisito_id')->unique()->values()->all(),
        );
    }

    /**
     * Los códigos del otro marco que cubren este requisito.
     *
     * @param  array<int, list<Correspondencia>>  $correspondencias
     * @return list<string>
     */
    protected function codigosCorrespondientes(Implantacion $implantacion, array $correspondencias): array
    {
        return array_map(
            static fn (Correspondencia $c): string => $c->cubreDelTodo ? $c->codigo : "{$c->codigo} (parcial)",
            $correspondencias[$implantacion->requisito_id] ?? [],
        );
    }

    /**
     * Las pruebas del requisito, con su fecha.
     *
     * La fecha va porque una evidencia de hace tres años prueba lo de hace tres
     * años, y ésa es la mitad de la conversación con el auditor.
     *
     * @return list<string>
     */
    protected function evidenciasDe(Implantacion $implantacion): array
    {
        return $implantacion->evidencias
            ->map(fn ($evidencia): string => $evidencia->titulo.' ('.$evidencia->fecha_obtencion->format('d/m/Y').')')
            ->values()
            ->all();
    }

    /**
     * Las cifras, todas con su denominador.
     *
     * **Se cuentan sobre las filas que este documento lista**, no sobre las
     * implantaciones del sistema y menos aún sobre las de la organización. Un
     * sistema de ISO lleva, además de los 93 controles del Anexo A, las
     * cláusulas 4 a 10 —el sistema de gestión— y el documento no las enseña: la
     * barra decía 122 y la tabla que tiene debajo decía 93.
     *
     * Es el mismo criterio que ya rige en el panel de inventario: cada cifra se
     * cuenta con el mismo alcance que tiene lo que enseña al lado.
     *
     * @param  list<FilaRequisito>  $filas
     * @return array<string, mixed>
     */
    protected function resumenDe(Documento $documento, array $filas): array
    {
        /*
         * La madurez también se cuenta sobre las filas del documento y no sobre
         * todas las del sistema, por el mismo motivo que los tramos: un sistema
         * de ISO lleva además las cláusulas 4 a 10, y una media que las incluya
         * no es la media de los controles del Anexo A.
         */
        $valoradas = array_values(array_filter(
            $filas,
            static fn (FilaRequisito $f): bool => $f->aplica && $f->madurezValor !== null,
        ));

        $madurez = [
            'evaluadas' => count($valoradas),
            // Sin ninguna valorada la media no es cero: es que no se sabe.
            'media' => $valoradas === [] ? null : round(array_sum(array_map(
                static fn (FilaRequisito $f): int => (int) $f->madurezValor,
                $valoradas,
            )) / count($valoradas), 1),
        ];

        $aplicables = count(array_filter($filas, static fn (FilaRequisito $f): bool => $f->aplica));
        $implantados = count(array_filter($filas, static fn (FilaRequisito $f): bool => $f->estado === 'implantado'));

        return [
            'total' => count($filas),
            /*
             * Cuántos requisitos tiene el marco entero, que puede ser MÁS que
             * los que salen en el documento: en el ENS, una categoría básica
             * sólo exige 52 de las 73 medidas del Anexo II.
             *
             * Sin este denominador la tabla dice «52 medidas del Anexo II» y se
             * lee como si el Anexo II tuviera 52. Que falten veintiuna es una
             * consecuencia correcta de la categorización, pero el auditor tiene
             * que poder verla, no deducirla.
             */
            'enElMarco' => $this->requisitosDelMarco($documento),
            'aplicables' => $aplicables,
            'excluidos' => count($filas) - $aplicables,
            'implantados' => $implantados,
            // Sin nada exigible el porcentaje no es cero: es que no hay nada que
            // medir, y un 0 % ahí diría lo contrario de lo que pasa.
            'porcentaje' => $aplicables === 0 ? null : (int) round($implantados * 100 / $aplicables),
            'sinEvidencia' => count(array_filter(
                $filas,
                static fn (FilaRequisito $f): bool => $f->aplica && ! $f->tieneEvidencia(),
            )),
            'madurezMedia' => $madurez['media'],
            'madurezEvaluadas' => $madurez['evaluadas'],
            // Los tramos se cuentan sobre las MISMAS filas que lista el
            // documento, no sobre todas las del sistema.
            'segmentos' => $this->segmentosDe($filas),
        ];
    }

    /**
     * El reparto por estado de lo que este documento lista.
     *
     * **No se pide a `ResumenCumplimiento::porEstado()`**, aunque sería lo
     * cómodo: aquel cuenta TODAS las implantaciones del sistema, y un sistema de
     * ISO tiene además las cláusulas 4 a 10, que son el sistema de gestión y no
     * controles del Anexo A. La barra decía 122 y la tabla justo encima decía
     * 93. Una gráfica que contradice a la tabla que tiene debajo no es un
     * detalle de maquetación: es el documento desmintiéndose solo delante del
     * auditor.
     *
     * **El orden no se toca**, y es el mismo que fija `porEstado()`: implantado,
     * planificado, en progreso, no iniciado. El verde y el ámbar no se
     * distinguen con protanopia —ΔE 5.7, por debajo del suelo de 6— y meter el
     * azul entre los dos sube la peor pareja contigua a 14.0.
     *
     * @param  list<FilaRequisito>  $filas
     * @return list<SegmentoEstado>
     */
    protected function segmentosDe(array $filas): array
    {
        $orden = [
            EstadoImplantacion::Implantado,
            EstadoImplantacion::Planificado,
            EstadoImplantacion::EnProgreso,
            EstadoImplantacion::NoIniciado,
        ];

        $segmentos = [];

        foreach ($orden as $estado) {
            $segmentos[] = new SegmentoEstado(
                $estado->value,
                $estado->etiqueta(),
                count(array_filter(
                    $filas,
                    static fn (FilaRequisito $fila): bool => $fila->estado === $estado->value,
                )),
            );
        }

        return $segmentos;
    }

    /**
     * Cuántos requisitos del tipo que lista este documento tiene el marco.
     *
     * Sólo hojas: los nodos de agrupación —`A.5`, `op.acc`— no son controles ni
     * medidas, son epígrafes.
     */
    protected function requisitosDelMarco(Documento $documento): int
    {
        $marcoId = $documento->sistema?->marco_id;

        if ($marcoId === null) {
            return 0;
        }

        return Requisito::query()
            ->where('marco_id', $marcoId)
            ->where('tipo', $this->tipoDeRequisito())
            ->whereDoesntHave('hijos')
            ->count();
    }

    /**
     * Las versiones ya entregadas, con su huella.
     *
     * Encadena la custodia: cada entrega puede demostrar cuál fue la anterior.
     * **La huella de la versión en curso no cabe aquí**, porque sería
     * autorreferencia: el PDF no puede contener su propio SHA-256.
     *
     * @return list<array<string, mixed>>
     */
    protected function historialDe(Documento $documento): array
    {
        return $documento->versionesEmitidas()
            ->with('generadaPor')
            ->get()
            ->map(fn (DocumentoVersion $version): array => [
                'numero' => $version->numero,
                'emitida' => $version->emitida_en?->format('d/m/Y'),
                'quien' => $version->generadaPor?->name,
                'motivo' => $version->motivo,
                'huella' => $version->hash_sha256,
            ])
            ->values()
            ->all();
    }

    /**
     * Los datos de cabecera comunes a los dos documentos.
     *
     * @return array<string, mixed>
     */
    protected function portadaBase(Documento $documento, DocumentoVersion $version): array
    {
        $organizacion = $documento->organizacion;
        $sistema = $documento->sistema;

        return [
            // `organizacion_id` es NOT NULL; `sistema_id` sí puede faltar en los
            // tipos de documento de ámbito organizativo que vendrán después.
            'organizacion' => $organizacion->nombre,
            'cif' => $organizacion->cif,
            'sistemaCodigo' => $sistema?->codigo,
            'sistemaNombre' => $sistema?->nombre,
            'marco' => $sistema?->marco->nombre,
            'marcoVersion' => $sistema?->marco->version,
            'documentoCodigo' => $documento->codigo,
            'clasificacion' => $documento->clasificacion->etiqueta(),
            'responsable' => $documento->responsable?->name,
            'version' => $version->etiqueta(),
            'esBorrador' => $version->esBorrador(),
            'fecha' => Carbon::now()->format('d/m/Y'),
        ];
    }

    /**
     * Las limitaciones comunes. Se imprimen, no se esconden.
     *
     * Un auditor respeta una limitación declarada y suspende una inventada: es
     * más barato decir que el flujo de aprobación no existe todavía que dejar
     * que lo descubra él.
     *
     * @param  list<FilaRequisito>  $filas
     * @return list<string>
     */
    protected function limitacionesBase(DocumentoVersion $version, array $filas): array
    {
        $limitaciones = [
            /*
             * Reescrita cuando la narrativa se volvió editable. Ahora la
             * organización puede redactar un texto de aprobación, y la frase
             * anterior —«no lleva aprobación formal»— se leería como una
             * contradicción con él. Borrarla sería mentir: el flujo sigue sin
             * existir. Así que se precisa qué es lo que no hace la herramienta y
             * de quién es el texto que aparece.
             */
            'La herramienta **no implementa un flujo de aprobación**: no registra quién aprobó, '
            .'cuándo ni con qué decisión. El texto de aprobación que figure en este documento, si '
            .'lo hay, lo ha redactado la organización y Statera no lo ha validado. Una versión '
            .'emitida no equivale a una versión aprobada por la dirección.',

            /*
             * Con la zona horaria escrita. La aplicación trabaja en UTC y quien
             * lee el documento no tiene por qué: sin la marca, un documento
             * generado a las 00:30 en España aparece fechado el día anterior, y
             * una fecha que no cuadra con el registro es un hallazgo barato de
             * encontrar.
             */
            'Datos extraídos el '.Carbon::now()->format('d/m/Y \a \l\a\s H:i T')
            .' sobre '.count($filas).' requisitos registrados.',
        ];

        if ($version->esBorrador()) {
            array_unshift(
                $limitaciones,
                '**Borrador.** Este PDF se regenera cada vez que se pide y no constituye una '
                .'entrega: sólo las versiones emitidas quedan registradas de forma inmutable '
                .'con su huella SHA-256.'
            );
        }

        return $limitaciones;
    }
}
