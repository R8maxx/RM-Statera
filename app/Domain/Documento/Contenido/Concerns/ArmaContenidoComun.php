<?php

declare(strict_types=1);

namespace App\Domain\Documento\Contenido\Concerns;

use App\Domain\Documento\Contenido\FilaRequisito;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use Illuminate\Support\Carbon;

/**
 * Lo que toda entrega lleva, sea una declaración calculada o un texto redactado.
 *
 * Estaba dentro de `DocumentoCalculado`, que era su único sitio posible
 * mientras los dos únicos documentos fueran declaraciones. Con los documentos
 * redactados dejó de serlo, y **las limitaciones son el motivo de fondo**: son lo
 * que el documento declara que no puede afirmar, y tener dos copias de esa lista
 * es cómo se acaba con una política que declara una limitación que la SoA ya no
 * tiene, o al revés.
 */
trait ArmaContenidoComun
{
    /**
     * Los datos de cabecera comunes a cualquier documento.
     *
     * @return array<string, mixed>
     */
    protected function portadaBase(Documento $documento, DocumentoVersion $version): array
    {
        $organizacion = $documento->organizacion;
        $sistema = $documento->sistema;

        return [
            // `organizacion_id` es NOT NULL; `sistema_id` sí falta en los tipos
            // de ámbito organizativo, que son todos los redactados.
            //
            // La RAZÓN SOCIAL y no `nombre`: un documento entregable lo firma
            // una persona jurídica, no una marca. `nombreLegal()` cae a `nombre`
            // mientras nadie la haya rellenado, que es el estado de toda
            // organización anterior a la ficha — así ningún documento cambió de
            // texto por migrar. Es el único punto donde `Organizacion` toca la
            // portada, así que de aquí se propaga solo a la ficha de la primera
            // página y a la cabecera de todas las demás.
            'organizacion' => $organizacion->nombreLegal(),
            'cif' => $organizacion->cif,
            'sistemaCodigo' => $sistema?->codigo,
            'sistemaNombre' => $sistema?->nombre,
            'marco' => $sistema?->marco->nombre,
            'marcoVersion' => $sistema?->marco->version,
            'documentoCodigo' => $documento->codigo,
            'clasificacion' => $documento->clasificacion->etiqueta(),
            'responsable' => $documento->responsable?->name,

            /*
             * La etiqueta PREVISTA, no la actual. Esta generación puede ser la
             * que dispara la firma, y entonces el número todavía no está puesto:
             * con la actual, la portada del documento que se entrega diría
             * «Borrador».
             */
            'version' => $version->etiquetaPrevista(),

            /*
             * «Borrador» dejó de ser «sin número» y pasó a ser «sin firma».
             * Aprobar regenera el PDF para que la firma salga impresa, y durante
             * esa generación la fila sigue sin numerar: mirando el número, el
             * documento entregado se marcaría a sí mismo como borrador.
             */
            'esBorrador' => ! $version->tieneFirma(),

            // La firma, que es lo que el auditor busca en la portada.
            'aprobadaPor' => $version->aprobadaPor?->name,
            'aprobadaEn' => $version->aprobada_en?->format('d/m/Y'),
            'proximaRevision' => $version->fecha_proxima_revision?->format('d/m/Y'),

            'fecha' => Carbon::now()->format('d/m/Y'),
        ];
    }

    /**
     * Las entregas anteriores, con su huella.
     *
     * @return list<array<string, mixed>>
     */
    protected function historialDe(Documento $documento): array
    {
        return $documento->versionesEmitidas()
            ->with(['generadaPor', 'aprobadaPor'])
            ->get()
            ->map(fn (DocumentoVersion $version): array => [
                'numero' => $version->numero,
                'emitida' => $version->emitida_en?->format('d/m/Y'),
                'quien' => $version->generadaPor?->name,
                'aprobadaPor' => $version->aprobadaPor?->name,
                'motivo' => $version->motivo,
                'huella' => $version->hash_sha256,
            ])
            ->values()
            ->all();
    }

    /**
     * Las limitaciones comunes. Se imprimen, no se esconden.
     *
     * Un auditor respeta una limitación declarada y suspende una inventada: es
     * más barato decir lo que la herramienta no hace que dejar que lo descubra
     * él.
     *
     * @param  list<FilaRequisito>  $filas  vacío en un documento redactado
     * @return list<string>
     */
    protected function limitacionesBase(DocumentoVersion $version, array $filas = []): array
    {
        $limitaciones = [
            /*
             * Reescrita cuando llegó el flujo de aprobación, y por el mismo
             * motivo por el que se reescribieron las dos de riesgos al llegar el
             * § 4.3: la frase anterior decía que la herramienta «no implementa un
             * flujo de aprobación», y eso pasó a ser **falso en el PDF que se le
             * entrega al auditor**, que es peor que una limitación ausente.
             *
             * No se borra: se precisa qué es lo que sigue sin hacer. Registrar
             * quién firmó no es lo mismo que comprobar que quien firmó tenía
             * potestad para hacerlo, y ninguna de las dos cosas es una firma
             * electrónica.
             */
            'La aprobación que figura en este documento es la que consta en Statera: usuario, fecha '
            .'y nota. **La herramienta no comprueba que quien firma tenga potestad para aprobar** '
            .'—sólo que tiene el permiso correspondiente— y **no incorpora firma electrónica '
            .'cualificada**, de modo que este registro acredita la trazabilidad de la aprobación, no '
            .'su validez jurídica.',

            /*
             * Con la zona horaria escrita. La aplicación trabaja en UTC y quien
             * lee el documento no tiene por qué: sin la marca, un documento
             * generado a las 00:30 en España aparece fechado el día anterior, y
             * una fecha que no cuadra con el registro es un hallazgo barato de
             * encontrar.
             */
            /*
             * «Las filas de este documento» y no «los requisitos registrados»:
             * el plan de adecuación lista sólo lo pendiente, así que la frase
             * anterior decía «sobre 51 requisitos registrados» habiendo 52
             * exigibles. Una cifra falsa, y precisamente en el apartado donde el
             * documento declara lo que no puede afirmar. El denominador completo
             * lo imprime el resumen, que es su sitio.
             */
            'Datos extraídos el '.Carbon::now()->format('d/m/Y \a \l\a\s H:i T')
            .($filas === [] ? '.' : ' sobre las '.count($filas).' filas que recoge este documento.'),
        ];

        if ($version->documento->exigeAcuse()) {
            /*
             * Reescrita con el § 4.8 dentro. Decía «mientras el módulo de
             * personas no exista», y eso pasó a ser falso en el PDF entregado.
             * No se borra: se precisa cuál es la limitación **de verdad**, que
             * no es que falte el registro de plantilla —ya está— sino que quien
             * no tiene cuenta de Statera no tiene forma de acusar recibo.
             */
            $limitaciones[] = 'El acuse de lectura registra a los **usuarios de Statera** que han '
                .'declarado haber leído esta versión. La organización mantiene además su registro '
                .'de personas, con el que esta cobertura puede contrastarse; pero **quien no tiene '
                .'cuenta en la herramienta no puede acusar recibo aquí**, así que la cobertura del '
                .'acuse **no acredita por sí sola el cumplimiento de la cláusula 7.3**.';
        }

        /*
         * «Borrador» es ahora «sin firma», no «sin número». Aprobar regenera el
         * PDF para que la firma salga en portada, y esa generación corre con la
         * fila todavía sin numerar: mirando el número, el documento entregado se
         * declararía borrador a sí mismo.
         */
        if (! $version->tieneFirma()) {
            array_unshift(
                $limitaciones,
                '**Borrador.** Este PDF se regenera cada vez que se pide y no constituye una '
                .'entrega: sólo las versiones aprobadas quedan registradas de forma inmutable '
                .'con su huella SHA-256.'
            );
        }

        return $limitaciones;
    }
}
