<?php

declare(strict_types=1);

namespace App\Domain\Documento\Contenido;

use App\Domain\Catalogo\Models\Requisito;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Narrativa\MarkdownDocumento;
use App\Domain\Documento\Narrativa\ResolverNarrativa;
use App\Domain\Implantacion\CorrespondenciasCruzadas;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Models\Implantacion;
use App\Http\Resources\Implantacion\Correspondencia;
use App\Http\Resources\Panel\SegmentoEstado;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lo que comparte todo documento que sale de una consulta sobre `implantaciones`.
 *
 * Hace pareja con `DocumentoRedactado`, que es el otro lado de la frontera que
 * define `TipoDocumento::esRedactado()`: aquí el contenido lo calcula el motor y
 * allí lo escribe la organización.
 *
 * **Se llamaba `DeclaracionAplicabilidad`**, y el nombre dejó de ser cierto al
 * entrar el plan de adecuación: un plan no declara aplicabilidad, lista lo que
 * falta por hacer. Lo que esta clase aporta —la consulta por tipo de requisito,
 * el agrupado por el nodo padre, las correspondencias cruzadas en dos consultas y
 * las evidencias ya formateadas— no es de un género documental, es la tubería.
 * Mismo caso que `IndicadorInventario` → `Indicador`: la forma era genérica y el
 * nombre mentía.
 *
 * Lo que cambia entre un hijo y otro es qué filas entran y qué cifras las
 * resumen, y por eso las dos cosas son abstractas.
 */
abstract class DocumentoCalculado implements GeneradorDocumento
{
    /*
     * La portada, el historial y las limitaciones son de cualquier entrega y no
     * sólo de una declaración: desde que existen los documentos redactados, los
     * comparten dos ramas que no tienen antepasado común. Las limitaciones son
     * el motivo de fondo —dos copias de esa lista es cómo se acaba con una
     * política declarando algo que la SoA ya no declara—.
     */
    use Concerns\ArmaContenidoComun;

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

    /** El epígrafe de las filas que cuelgan de la raíz: «Anexo A», «Anexo II». */
    abstract protected function grupoRaiz(): string;

    /**
     * El epígrafe bajo el que se agrupa una fila.
     *
     * **Un solo salto al padre y no la rama entera**: para `op.acc.4` el grupo
     * es `op.acc`, no `op`. La rama completa se recorre con la CTE recursiva de
     * `Requisito::ruta()`, que es lo que pinta la ficha; aquí agrupar por el
     * bisabuelo dejaría medio Anexo II bajo un mismo epígrafe.
     */
    protected function grupo(Implantacion $implantacion): string
    {
        $padre = $implantacion->requisito->padre;

        return $padre === null
            ? $this->grupoRaiz()
            : "{$padre->codigo} · {$padre->titulo}";
    }

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
        return $this->consultaDeRequisitos($documento)
            // Sin esto, noventa y tres controles son doscientas consultas y el
            // documento se come el tiempo de la cola.
            ->with(['requisito.padre', 'requisito.marco', 'responsable', 'evidencias', 'riesgos'])
            ->orderBy('requisitos.orden')
            ->orderBy('requisitos.id')
            ->get();
    }

    /**
     * El universo de este documento, sin filtrar ni ordenar.
     *
     * Existe para que un hijo pueda contar sobre **exactamente** las mismas
     * filas que lista, sin reescribir el join ni el filtro por tipo. El plan de
     * adecuación lo usa para su denominador: cuántas medidas se le exigen al
     * sistema, de las que su tabla enseña sólo las que faltan.
     *
     * @return Builder<Implantacion>
     */
    protected function consultaDeRequisitos(Documento $documento): Builder
    {
        return Implantacion::query()
            ->select('implantaciones.*')
            ->join('requisitos', 'requisitos.id', '=', 'implantaciones.requisito_id')
            ->where('implantaciones.sistema_id', $documento->sistema_id)
            ->where('requisitos.tipo', $this->tipoDeRequisito());
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
     * Las cifras del documento, todas con su denominador.
     *
     * **Es abstracto y no heredado a propósito.** Las dos declaraciones lo
     * resuelven igual —`Concerns\ResumeLaAplicabilidad`, que cuenta cuántos
     * aplican y qué porcentaje está implantado— y el plan de adecuación **no
     * puede usar eso**: sus filas son todas pendientes por construcción, así que
     * «porcentaje implantado» daría siempre cero sobre una tabla en la que ese
     * cero no significa nada.
     *
     * Heredar aquí una implementación que un hijo no debe llamar es una mina que
     * no caza ningún `match` exhaustivo: el día que alguien la invocara, el
     * documento imprimiría una cifra falsa sin ningún error.
     *
     * @param  list<FilaRequisito>  $filas
     * @return array<string, mixed>
     */
    abstract protected function resumen(Documento $documento, array $filas): array;

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
     * `$orden` lo pasa quien lista un subconjunto: el plan de adecuación no
     * enseña el tramo «Implantado», porque sobre una tabla que es toda pendiente
     * sería un cero fijo ocupando la cuarta parte de la leyenda.
     *
     * @param  list<FilaRequisito>  $filas
     * @param  list<EstadoImplantacion>|null  $orden
     * @return list<SegmentoEstado>
     */
    protected function segmentosDe(array $filas, ?array $orden = null): array
    {
        $orden ??= [
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
}
