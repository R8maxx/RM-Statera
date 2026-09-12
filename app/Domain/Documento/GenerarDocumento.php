<?php

declare(strict_types=1);

namespace App\Domain\Documento;

use App\Domain\Documento\Contenido\ContenidoDocumento;
use App\Domain\Documento\Contenido\RegistroGeneradores;
use App\Domain\Documento\Cuerpo\HtmlDocumento;
use App\Domain\Documento\Enums\EstadoGeneracion;
use App\Domain\Documento\Excepciones\GeneracionFallida;
use App\Domain\Documento\Jobs\GenerarDocumentoJob;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Documento\Render\AssetsDocumento;
use App\Domain\Documento\Render\ClienteGotenberg;
use App\Domain\Documento\Render\SolicitudPdf;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * El servicio común: renderiza, llama a Gotenberg, calcula el hash, almacena y
 * registra la versión.
 *
 * Es lo que hace que el sexto tipo de documento cueste una clase y una
 * plantilla. Aquí no se decide QUÉ pone el documento —de eso responde su
 * `GeneradorDocumento`—, sólo cómo se convierte en un fichero que se puede
 * enseñar dentro de dos años.
 */
final readonly class GenerarDocumento
{
    public const DISCO = 'documentos';

    public function __construct(
        private RegistroGeneradores $generadores,
        private ClienteGotenberg $gotenberg,
        private AssetsDocumento $assets,
        private HtmlDocumento $html,
    ) {}

    /**
     * Deja el borrador listo para que lo genere la cola.
     *
     * **Nunca en el ciclo de petición**: una SoA de noventa y tres controles con
     * sus evidencias tarda, y bloquear la petición web con eso es cómo se
     * consigue que alguien recargue y lance tres generaciones.
     *
     * Reutiliza el borrador si ya lo hay, porque el índice único parcial deja
     * uno como mucho por documento: pulsar «Generar» dos veces no acumula
     * basura, regenera.
     *
     * @param  array<string, mixed>  $parametros
     */
    public function encolar(Documento $documento, ?User $solicitante = null, array $parametros = []): DocumentoVersion
    {
        $version = $documento->borrador()->first() ?? new DocumentoVersion(['documento_id' => $documento->id]);

        $version->fill([
            'documento_id' => $documento->id,
            'estado_generacion' => EstadoGeneracion::Encolada->value,
            'parametros' => $parametros,
            'generada_por_id' => $solicitante?->id,
            // El fichero anterior deja de ser el vigente en cuanto se pide otro.
            // Se sueltan las columnas para que el CHECK de coherencia no deje
            // una fila «encolada» con ruta y huella de la generación pasada.
            'disco' => null,
            'ruta' => null,
            'nombre_fichero' => null,
            'mime' => null,
            'tamano' => null,
            'hash_sha256' => null,
            'error' => null,
        ]);

        $version->save();

        GenerarDocumentoJob::dispatch($version->id, $documento->organizacion_id);

        return $version;
    }

    /**
     * Produce el PDF y lo registra. Lo llama el job, no un controlador.
     *
     * El primer `UPDATE` es condicional a propósito: si otro trabajador ya se
     * llevó este borrador, afecta a cero filas y aquí no se hace nada. Es la
     * segunda barrera contra la doble generación, después de `ShouldBeUnique`;
     * la primera se puede perder si Redis pierde el candado, ésta no.
     */
    public function ejecutar(DocumentoVersion $version): void
    {
        $tomado = DB::table('documento_versiones')
            ->where('id', $version->id)
            ->where('estado_generacion', EstadoGeneracion::Encolada->value)
            ->update(['estado_generacion' => EstadoGeneracion::Generando->value, 'updated_at' => now()]);

        if ($tomado === 0) {
            return;
        }

        $version->refresh();
        $documento = $version->documento;

        $contenido = $this->generadores->para($documento->tipo)->construir(
            $documento,
            $version,
            $version->parametros,
        );

        // Se resuelve UNA vez y se usa dos: para el PDF y para la instantánea.
        // Resolverlo dos veces abriría la puerta a que el fichero entregado y
        // lo que se congela no fueran el mismo documento.
        $cuerpo = $this->html->cuerpo($documento, $contenido);

        $pdf = $this->gotenberg->pdf($this->solicitud($documento, $version, $contenido, $cuerpo));

        $this->almacenar($version, $documento, $contenido, $cuerpo, $pdf);
    }

    /**
     * @param  array<string, mixed>  $cuerpo
     *
     * @throws GeneracionFallida
     */
    private function almacenar(
        DocumentoVersion $version,
        Documento $documento,
        ContenidoDocumento $contenido,
        array $cuerpo,
        string $pdf,
    ): void {
        $huella = hash('sha256', $pdf);

        /*
         * Nombre con ULID: regenerar un borrador escribe una clave NUEVA en vez
         * de sobreescribir la anterior. En producción eso es lo que permite que
         * el Object Lock se aplique al prefijo `emitidas/` sin bloquear lo único
         * que tiene que poder reescribirse.
         */
        $ruta = sprintf(
            '%d/%d/borradores/%s.pdf',
            $documento->organizacion_id,
            $documento->id,
            Str::ulid()->toBase32(),
        );

        $disco = Storage::disk(self::DISCO);
        $disco->put($ruta, $pdf);

        // El disco va con `throw => true`, pero un `put` que escriba de menos no
        // lanza: se comprueba. Una versión «generada» con el fichero truncado
        // aparecería semanas después, delante del auditor.
        if ($disco->size($ruta) !== strlen($pdf)) {
            throw GeneracionFallida::porAlmacenamiento($ruta);
        }

        $totales = $contenido->totales();

        $version->update([
            'estado_generacion' => EstadoGeneracion::Generada->value,
            'disco' => self::DISCO,
            'ruta' => $ruta,
            'nombre_fichero' => $this->nombreDescarga($documento, $version),
            'mime' => 'application/pdf',
            'tamano' => strlen($pdf),
            'hash_sha256' => $huella,
            /*
             * El cuerpo entero, además de lo que ya se congelaba. Es lo que
             * convierte la instantánea en un documento reconstruible: sin él,
             * una versión emitida sabría qué datos tenía pero no cómo estaba
             * redactada, y un documento que se puede editar entero necesita las
             * dos cosas para poder contestar «¿qué cambió entre la v3 y la v4?».
             */
            'instantanea' => [...$contenido->paraInstantanea(), 'cuerpo' => $cuerpo],
            'total_requisitos' => $totales['requisitos'],
            'total_excluidos' => $totales['excluidos'],
            'total_implantados' => $totales['implantados'],
            'error' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $cuerpo
     */
    private function solicitud(Documento $documento, DocumentoVersion $version, ContenidoDocumento $contenido, array $cuerpo): SolicitudPdf
    {
        $portada = $contenido->portada;

        return new SolicitudPdf(
            html: $this->html->conCuerpo($contenido, $cuerpo),

            cabecera: View::make('documentos.cabecera', [
                'organizacion' => $portada['organizacion'] ?? '',
                'codigo' => $documento->codigo,
                'titulo' => $documento->titulo,
            ])->render(),

            pie: View::make('documentos.pie', [
                'clasificacion' => $documento->clasificacion->sello(),
                'version' => $version->etiqueta(),
                'fecha' => $portada['fecha'] ?? '',
            ])->render(),

            assets: $this->assets->todos(),

            metadatos: [
                'Title' => $documento->titulo.' — '.$documento->codigo,
                'Author' => (string) ($portada['organizacion'] ?? ''),
                'Subject' => $contenido->subtitulo,
                'Creator' => 'Statera',
            ],
        );
    }

    /**
     * El nombre con el que se descarga.
     *
     * Con el código y la versión dentro, porque el fichero acaba en el correo de
     * alguien y «documento.pdf» no se distingue del de la semana pasada.
     */
    private function nombreDescarga(Documento $documento, DocumentoVersion $version): string
    {
        return Str::slug($documento->codigo.'-'.$version->etiqueta()).'.pdf';
    }
}
