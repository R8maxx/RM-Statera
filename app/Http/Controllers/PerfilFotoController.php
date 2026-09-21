<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Usuario\BorrarFotoPerfil;
use App\Domain\Usuario\GuardarFotoPerfil;
use App\Http\Requests\GuardarFotoPerfilRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * La foto de perfil de quien ha entrado.
 *
 * **Ninguna de las tres rutas dice de quién es la foto**, y es la decisión que
 * sostiene el aislamiento de este controlador. `users` es el único modelo de
 * datos propios que no lleva `PerteneceAOrganizacion`, ni scope global, ni RLS
 * —la autenticación tiene que poder encontrar a alguien antes de saber de qué
 * organización es—, así que una ruta `/perfil/foto/{usuario}` habría que
 * acotarla a mano y **ningún test de aislamiento avisaría si se olvidara**. Sin
 * parámetro no hay nada que acotar.
 *
 * Tampoco llevan permiso ni segundo factor, igual que el resto de `/perfil` y
 * que el acuse de lectura de un documento: se escribe sobre uno mismo.
 */
class PerfilFotoController extends Controller
{
    /**
     * Sirve la foto como **redirect a una URL firmada de cinco minutos**, igual
     * que la descarga de un adjunto o de una evidencia: nunca un enlace al
     * bucket, que es privado, ni un `streamDownload` que haría pasar la imagen
     * por PHP en cada pantalla.
     *
     * Cinco minutos bastan aunque la pestaña se quede abierta ocho horas: la
     * URL que pinta el `<img>` es la de esta ruta y es estable, así que un
     * navegador que tenga que volver a pedirla vuelve por aquí y se lleva una
     * firma nueva.
     */
    public function show(Request $request): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        if ($usuario->foto_ruta === null) {
            throw new NotFoundHttpException;
        }

        return redirect()->away(
            Storage::disk('adjuntos')->temporaryUrl($usuario->foto_ruta, now()->addMinutes(5)),
        );
    }

    public function store(GuardarFotoPerfilRequest $request, GuardarFotoPerfil $guardar): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        $guardar($usuario, $request->file('foto'));

        Inertia::flash('exito', 'La foto está cambiada.');

        return to_route('perfil');
    }

    public function destroy(Request $request, BorrarFotoPerfil $borrar): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        $borrar($usuario);

        Inertia::flash('exito', 'La foto ya no está.');

        return to_route('perfil');
    }
}
