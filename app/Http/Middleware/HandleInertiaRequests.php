<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Organizacion\ContextoOrganizacion;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Lo que toda página recibe sin pedirlo.
 *
 * Se ejecuta después de `EstablecerContextoOrganizacion`, así que la
 * organización activa ya está fijada y las tres capas de aislamiento están
 * sincronizadas cuando se resuelven estos props.
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $usuario = $request->user();
        $contexto = app(ContextoOrganizacion::class);

        return [
            ...parent::share($request),

            'auth' => [
                'usuario' => $usuario === null ? null : [
                    'id' => $usuario->id,
                    'nombre' => $usuario->name,
                    'email' => $usuario->email,
                    'dosFactores' => $usuario->two_factor_confirmed_at !== null,
                ],
                // Los permisos viajan como lista plana: el frontend solo decide
                // qué pinta, nunca qué autoriza. La autorización es del servidor.
                'permisos' => $usuario?->getAllPermissions()->pluck('name')->values()->all() ?? [],
            ],

            // Sin contexto no hay datos propios visibles, y la interfaz tiene que
            // poder decirlo en lugar de mostrar tablas vacías sin explicación.
            'organizacion' => $contexto->hayContexto() && $usuario?->organizacion !== null
                ? [
                    'id' => $usuario->organizacion->id,
                    'nombre' => $usuario->organizacion->nombre,
                ]
                : null,
        ];
    }
}
