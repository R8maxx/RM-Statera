<?php

declare(strict_types=1);

namespace App\Domain\Documento;

use App\Domain\Documento\Enums\EstadoDocumental;
use App\Domain\Documento\Excepciones\AprobacionNoPermitida;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * La firma: la dirección aprueba el documento.
 *
 * Es el acto que ISO 27001 pide para una política —y `org.1` del ENS para la
 * política de seguridad— y el que convierte un fichero en un documento del SGSI.
 * Por eso tiene permiso propio, `documentos.aprobar`, aparte de
 * `documentos.generar`: preparar un documento y firmarlo no lo hace la misma
 * persona, que es toda la razón por la que la norma pide la aprobación.
 *
 * **Aprobar es lo que emite**, y no es una preferencia de flujo. La portada se
 * congela en `instantanea` al generar y el trigger vuelve la fila inmutable en
 * cuanto tiene número: una firma posterior no podría salir impresa en el PDF que
 * se entrega, que es justamente donde el auditor la busca. Así que firmar hace
 * dos cosas —escribe la aprobación y **manda regenerar**— y el número se asigna
 * al terminar esa generación, en `EmitirVersion`.
 *
 * De ahí el hueco transitorio: entre la firma y el final de la generación, la
 * fila está firmada y sin numerar. El `CHECK` de la tabla lo admite a propósito
 * —sólo exige firma para el estado `aprobado`, no al revés— y si la generación
 * falla, la versión se queda en revisión con su error y se puede reintentar.
 *
 * **La versión anterior NO se jubila aquí**, sino al cerrar: si se jubilara ahora
 * y la generación fallara, el documento se quedaría sin ninguna versión vigente
 * por haber intentado aprobar la siguiente.
 */
final readonly class AprobarVersion
{
    public function __construct(private GenerarDocumento $generar) {}

    /**
     * @throws AprobacionNoPermitida
     */
    public function __invoke(DocumentoVersion $version, User $aprobador, ?string $nota = null): DocumentoVersion
    {
        if ($version->estaAprobada()) {
            throw AprobacionNoPermitida::yaAprobada($version);
        }

        if (! $version->estado->permite(EstadoDocumental::Aprobado)) {
            throw AprobacionNoPermitida::porTransicion($version, EstadoDocumental::Aprobado);
        }

        if (! $version->tieneFichero()) {
            throw AprobacionNoPermitida::sinPdf($version);
        }

        $firmada = Carbon::today();

        $version->fill([
            'aprobada_por_id' => $aprobador->id,
            'aprobada_en' => $firmada,
            'nota_aprobacion' => $nota,
            'fecha_proxima_revision' => $this->proximaRevision($version, $firmada),
        ])->save();

        /*
         * Se regenera para que el PDF salga con la firma en portada y con su
         * número en el pie. Va a la cola como cualquier otra generación: una SoA
         * de noventa y tres controles tarda, y el ciclo de petición no es sitio
         * para eso. Quien cierra la aprobación al terminar es el job.
         */
        $this->generar->encolar($version->documento, $aprobador, $version->parametros);

        return $version->refresh();
    }

    /**
     * Cuándo toca volver a mirarlo.
     *
     * Se cuenta desde la firma y no desde hoy —son el mismo día, pero la que
     * significa algo es la firma— y se congela en la versión: la pregunta es
     * «cuándo caduca ESTA revisión», y la contesta la fecha en que se aprobó.
     *
     * Nulo cuando el documento no declara periodicidad, que es una respuesta
     * legítima: una Declaración de Aplicabilidad se rehace cuando cambia el
     * alcance, no cuando pasa un año. Inventarle un plazo llenaría el calendario
     * de vencimientos que nadie ha decidido.
     */
    private function proximaRevision(DocumentoVersion $version, Carbon $firmada): ?Carbon
    {
        $meses = $version->documento->periodicidad_revision_meses;

        return $meses === null ? null : $firmada->copy()->addMonths($meses);
    }
}
