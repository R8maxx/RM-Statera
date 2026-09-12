<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Documento\Contenido\RegistroGeneradores;
use App\Domain\Documento\Cuerpo\GuardarCuerpo;
use App\Domain\Documento\Cuerpo\ResolverCuerpo;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Render\GeometriaPagina;
use App\Http\Requests\GuardarCuerpoDocumentoRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El documento entero, editable.
 *
 * Pantalla a página completa y no una pestaña de la ficha, por lo mismo que los
 * textos: la ficha lleva un `usePoll` cada tres segundos mientras se genera un
 * borrador, y un editor largo conviviendo con recargas parciales es pedir un
 * conflicto de estado sucio.
 *
 * **Estrenar el cuerpo es idempotente y pasa aquí.** Los documentos creados
 * antes de que esto existiera no tienen fila; la primera visita al editor —o la
 * primera generación— se la crea desde el esqueleto de fábrica materializado, y
 * arrastra la narrativa que la organización ya tuviera escrita.
 */
class DocumentoCuerpoController extends Controller
{
    public function edit(
        Documento $documento,
        ResolverCuerpo $resolver,
        RegistroGeneradores $generadores,
    ): Response {
        $version = $documento->borrador()->first() ?? $documento->versiones()->latest('id')->first();

        abort_if($version === null, 404);

        $contenido = $generadores->para($documento->tipo)->construir($documento, $version, []);
        $fila = $resolver->fila($documento, $contenido);
        $portada = $contenido->portada;

        return Inertia::render('documentos/Editar', [
            'documento' => [
                'id' => $documento->id,
                'codigo' => $documento->codigo,
                'titulo' => $documento->titulo,
                'tipoEtiqueta' => $documento->tipo->etiqueta(),
            ],
            'cuerpo' => $fila->cuerpo,
            'editadoEn' => $fila->editado_en?->toIso8601String(),
            'actualizadoEn' => $fila->updated_at?->toIso8601String(),

            /*
             * La hoja del editor se dibuja con las medidas con las que Gotenberg
             * imprime, nunca con medidas escritas a mano en el CSS: si mañana un
             * tipo de documento pasa a vertical, el editor cambia solo. El día
             * que no cambie, quien redacta vería una hoja apaisada y el PDF
             * saldría vertical sin que nada avisara.
             */
            'geometria' => GeometriaPagina::paraElEditor(),

            /*
             * Lo que va en los márgenes. Chromium renderiza la cabecera y el pie
             * de verdad en un contexto aparte —`documentos.cabecera` y
             * `documentos.pie`, con su propio CSS y sin heredar nada—, así que
             * esto es una **representación**, no una copia: sirve para que quien
             * escribe sepa que ese hueco está ocupado. Lo de verdad se comprueba
             * en el PDF. Por eso tampoco viaja el número de página: en el editor
             * no se sabe, y una cifra inventada en un documento es peor que un
             * hueco.
             */
            /*
             * El borrador que hay en disco, para «Ver el PDF».
             *
             * Va como cierre y suelto —no dentro de `documento`— porque es lo
             * único que recarga el poll mientras Gotenberg trabaja: una recarga
             * parcial pide claves de primer nivel, y sin el cierre se volvería a
             * resolver el cuerpo entero cada tres segundos.
             */
            'borrador' => fn (): ?array => $this->borrador($documento),

            'margenes' => [
                'organizacion' => (string) ($portada['organizacion'] ?? ''),
                'codigo' => $documento->codigo,
                'titulo' => $documento->titulo,
                'clasificacion' => $documento->clasificacion->sello(),
                'version' => $version->etiqueta(),
                'fecha' => (string) ($portada['fecha'] ?? ''),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function borrador(Documento $documento): ?array
    {
        $borrador = $documento->versiones()->whereNull('numero')->first();

        if ($borrador === null) {
            return null;
        }

        return [
            'id' => $borrador->id,
            'etiqueta' => $borrador->etiqueta(),
            'estado' => $borrador->estado_generacion->value,
            'enCurso' => $borrador->estado_generacion->enCurso(),
            'visible' => $borrador->tieneFichero(),
            'error' => $borrador->error,
        ];
    }

    public function update(
        GuardarCuerpoDocumentoRequest $request,
        Documento $documento,
        ResolverCuerpo $resolver,
        GuardarCuerpo $guardar,
        RegistroGeneradores $generadores,
    ): RedirectResponse {
        $version = $documento->borrador()->first() ?? $documento->versiones()->latest('id')->first();

        abort_if($version === null, 404);

        $contenido = $generadores->para($documento->tipo)->construir($documento, $version, []);
        $fila = $resolver->fila($documento, $contenido);

        /*
         * Gana el último, como en el resto del producto, pero se avisa. Dos
         * personas editando el mismo documento la víspera de una auditoría es lo
         * normal, y perder media hora de redacción sin enterarse es lo que hace
         * que la gente vuelva al Word.
         */
        $esperado = $request->string('actualizado_en')->toString();
        $pisado = $esperado !== ''
            && $fila->updated_at !== null
            && $fila->updated_at->toIso8601String() !== $esperado;

        if (! $guardar($fila, $request->cuerpo(), $request->user())) {
            return back()->withErrors(['cuerpo' => 'Lo que se ha enviado no es un documento que se pueda guardar.']);
        }

        Inertia::flash(
            $pisado ? 'aviso' : 'exito',
            $pisado
                ? 'Guardado, pero alguien había cambiado el documento mientras lo editabas: tu versión es la que queda.'
                : 'Documento guardado.',
        );

        /*
         * Se vuelve al editor, no a la ficha. Guardar a media redacción es lo
         * normal —y es obligatorio antes de «Ver el PDF», que imprime lo
         * guardado y no lo que hay en pantalla—: echar de la pantalla en cada
         * guardado obliga a volver a entrar y a buscar dónde se estaba.
         */
        return back();
    }
}
