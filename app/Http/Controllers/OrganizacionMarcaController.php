<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Marca\BorrarPiezaDeMarca;
use App\Domain\Organizacion\Marca\GuardarPiezaDeMarca;
use App\Domain\Organizacion\Marca\PiezaDeMarca;
use App\Domain\Organizacion\Marca\SvgNoAdmitido;
use App\Domain\Organizacion\Models\Organizacion;
use App\Http\Requests\GuardarPiezaDeMarcaRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * El logo y el símbolo de la organización.
 *
 * **La ruta no dice de qué organización es**, igual que `/perfil/foto`: sale del
 * contexto. `organizaciones` es la raíz del tenant y no lleva scope global ni
 * RLS, así que un parámetro habría que acotarlo a mano y ningún test de
 * aislamiento avisaría si se olvidara.
 *
 * `{pieza}` sí es parámetro, pero se resuelve con el enum: un valor inventado
 * responde 404 sin llegar aquí.
 *
 * **Ver el logo no lleva permiso de gestión.** Lo pinta el sidebar de cualquiera
 * que haya entrado, así que la lectura va con la sesión y sólo la escritura con
 * `organizacion.gestionar`. Es el mismo reparto que el acuse de lectura: lo que
 * se sirve es de la propia organización de quien mira.
 */
class OrganizacionMarcaController extends Controller
{
    public function __construct(private readonly ContextoOrganizacion $contexto) {}

    /**
     * Redirect a URL firmada de cinco minutos, nunca un enlace al bucket.
     *
     * Cinco minutos bastan aunque la pestaña siga abierta: la URL que pinta el
     * `<img>` es la de esta ruta y es estable, así que el navegador que tenga
     * que volver a pedirla vuelve por aquí y se lleva una firma nueva.
     */
    public function show(PiezaDeMarca $pieza): RedirectResponse
    {
        $ruta = $this->actual()->getAttribute($pieza->columna());

        if (! is_string($ruta) || $ruta === '') {
            throw new NotFoundHttpException;
        }

        return redirect()->away(
            Storage::disk('adjuntos')->temporaryUrl($ruta, now()->addMinutes(5)),
        );
    }

    public function store(
        GuardarPiezaDeMarcaRequest $request,
        PiezaDeMarca $pieza,
        GuardarPiezaDeMarca $guardar,
    ): RedirectResponse {
        try {
            $guardar($this->actual(), $pieza, $request->file('pieza'));
        } catch (SvgNoAdmitido $e) {
            /*
             * El saneado falla por lo que trae el fichero, no por un error del
             * programa: quien lo sube tiene que leerlo al lado del campo y no
             * en una pantalla de error.
             */
            throw ValidationException::withMessages(['pieza' => $e->getMessage()]);
        }

        Inertia::flash('exito', "El {$pieza->etiqueta()} está guardado.");

        return to_route('organizacion.edit');
    }

    public function destroy(PiezaDeMarca $pieza, BorrarPiezaDeMarca $borrar): RedirectResponse
    {
        $borrar($this->actual(), $pieza);

        Inertia::flash('exito', "El {$pieza->etiqueta()} ya no está.");

        return to_route('organizacion.edit');
    }

    private function actual(): Organizacion
    {
        return Organizacion::query()->findOrFail($this->contexto->idObligatorio());
    }
}
