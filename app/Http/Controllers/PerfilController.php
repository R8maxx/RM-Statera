<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passkeys\Passkey;

/**
 * La cuenta de quien ha entrado: datos, contraseña, segundo factor y passkeys.
 *
 * Todo el backend es de Fortify y del paquete de passkeys, que ya registran sus
 * rutas (`user/two-factor-*`, `user/passkeys*`). Aquí sólo se pinta el estado y
 * se decide qué se enseña.
 *
 * **El secreto, el QR y los códigos de recuperación no viajan con la pantalla.**
 * Viven en `dosFactores()`, que va detrás de `password.confirm`: son los datos
 * con los que alguien que se siente delante de una sesión abierta se lleva el
 * segundo factor puesto. Fortify protege así sus propios endpoints y esta
 * pantalla no puede ser la puerta de atrás.
 */
class PerfilController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('perfil/Index', $this->estado($request->user()));
    }

    /**
     * Lo mismo, con el secreto del segundo factor a la vista.
     *
     * Es una navegación y no una petición de fondo a propósito: si hay que
     * reconfirmar la contraseña, el middleware manda a la pantalla de
     * confirmación y vuelve aquí después, que es el recorrido que la persona
     * entiende.
     */
    public function dosFactores(Request $request): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return Inertia::render('perfil/Index', [
            ...$this->estado($usuario),
            'secreto' => $usuario->two_factor_secret === null ? null : [
                // El SVG lo genera Fortify; se pinta con `v-html` porque es eso,
                // un SVG, y no texto.
                'qr' => $usuario->twoFactorQrCodeSvg(),
                // La clave en texto, para quien no puede escanear.
                'clave' => decrypt($usuario->two_factor_secret),
                'codigos' => $usuario->recoveryCodes(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function estado(?User $usuario): array
    {
        return [
            'usuario' => [
                'nombre' => $usuario?->name,
                'email' => $usuario?->email,
            ],
            'dosFactores' => [
                'confirmado' => (bool) $usuario?->dosFactoresConfirmado(),
                'pendiente' => (bool) $usuario?->dosFactoresPendiente(),
            ],
            'passkeys' => $usuario === null ? [] : $usuario->passkeys()
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Passkey $passkey): array => [
                    'id' => $passkey->id,
                    'nombre' => $passkey->name,
                    // Qué autenticador es (Touch ID, una llave física…), si el
                    // catálogo de AAGUID lo reconoce.
                    'autenticador' => $passkey->authenticator,
                    'ultimoUso' => $passkey->last_used_at?->toIso8601String(),
                    'alta' => $passkey->created_at?->toIso8601String(),
                ])
                ->all(),
            // Sin secreto: esta clave sólo la rellena `dosFactores()`.
            'secreto' => null,
        ];
    }
}
