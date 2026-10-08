<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Marca\PiezaDeMarca;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Plataforma\Enums\CapacidadPlataforma;
use App\Domain\Plataforma\Enums\EstadoSuscripcion;
use App\Domain\Plataforma\Soporte\SesionDeSoporte;
use App\Models\User;
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
                    // La ruta es fija y el sufijo de versión es el ULID del
                    // fichero: sin él el navegador serviría de su caché la foto
                    // vieja y cambiarla no se vería. Nulo cuando no hay foto, y
                    // entonces el chrome cae al círculo de iniciales.
                    'foto' => $usuario->urlFoto(),
                    'dosFactores' => $usuario->two_factor_confirmed_at !== null,
                    // El de la cuenta. La plantilla lo aplica antes del primer
                    // pintado, pero al entrar la navegación es de Inertia y la
                    // plantilla no se vuelve a pintar: el layout lo recoge de aquí.
                    'tema' => $usuario->tema->value,
                    'plataforma' => $usuario->esPlataforma(),
                ],
                // Los permisos viajan como lista plana: el frontend solo decide
                // qué pinta, nunca qué autoriza. La autorización es del servidor.
                //
                // Quien administra la plataforma recibe además sus capacidades
                // con el prefijo `plataforma.` (punto 48). No son permisos de la
                // base: son la llave con la que `lib/navegacion.ts` le pinta su
                // grupo. Quien autoriza es `CapacidadDePlataforma`.
                'permisos' => $usuario?->esPlataforma() === true
                    ? $this->permisosDePlataforma($usuario, $contexto)
                    : ($usuario?->getAllPermissions()->pluck('name')->values()->all() ?? []),
            ],

            // Sin contexto no hay datos propios visibles, y la interfaz tiene que
            // poder decirlo en lugar de mostrar tablas vacías sin explicación.
            'organizacion' => $this->organizacionActiva($contexto),

            // La franja de aviso del layout (punto 43). Sólo cuando hay algo que
            // decir: en gracia o en sólo lectura. Vigente no viaja.
            'suscripcion' => $this->suscripcion($contexto),

            // La franja del soporte (punto 44): dónde está y hasta cuándo.
            'soporte' => SesionDeSoporte::activo($usuario, $contexto) ? $this->soporte($contexto) : null,
        ];
    }

    /**
     * La organización del contexto, y no la de la cuenta: para quien entra como
     * soporte (punto 44) son distintas, y lo que hay que pintar es dónde está.
     *
     * @return ?array{id: int, nombre: string, logo: ?string}
     */
    private function organizacionActiva(ContextoOrganizacion $contexto): ?array
    {
        $organizacion = $contexto->hayContexto() ? Organizacion::query()->find($contexto->id()) : null;

        return $organizacion === null ? null : [
            'id' => $organizacion->id,
            'nombre' => $organizacion->nombre,
            // El logo del cliente en el chrome. Nulo mientras no lo suba
            // nadie, y entonces el sidebar sale como salía.
            'logo' => $organizacion->urlMarca(PiezaDeMarca::Logo),
        ];
    }

    /**
     * Siempre sus capacidades de plataforma (punto 48); en su propia
     * organización (punto 45), además los de su rol; dentro de otra como
     * soporte, los `.ver`, que son los que `Gate::before` le concede. Así el
     * lateral le enseña lo que puede abrir y nada más.
     *
     * @return list<string>
     */
    private function permisosDePlataforma(User $usuario, ContextoOrganizacion $contexto): array
    {
        $capacidades = array_map(
            static fn (CapacidadPlataforma $capacidad): string => $capacidad->permiso(),
            $usuario->perfil_plataforma?->capacidades() ?? [],
        );

        if (! $contexto->hayContexto()) {
            return $capacidades;
        }

        if (! SesionDeSoporte::activo($usuario, $contexto)) {
            return [...$capacidades, ...$usuario->getAllPermissions()->pluck('name')->values()->all()];
        }

        return [
            ...$capacidades,
            ...array_map(
                static fn (Permiso $permiso): string => $permiso->value,
                array_values(array_filter(Permiso::cases(), static fn (Permiso $permiso): bool => ! $permiso->esDeEscritura())),
            ),
        ];
    }

    /**
     * @return ?array{organizacion: string, hasta: ?string}
     */
    private function soporte(ContextoOrganizacion $contexto): ?array
    {
        $organizacion = Organizacion::query()->find($contexto->id());

        return $organizacion === null ? null : [
            'organizacion' => $organizacion->nombre,
            'hasta' => $organizacion->soporte_hasta?->toIso8601String(),
        ];
    }

    /**
     * @return ?array{estado: string, etiqueta: string, plan: ?string, venceEn: ?string, graciaHasta: ?string}
     */
    private function suscripcion(ContextoOrganizacion $contexto): ?array
    {
        if (! $contexto->hayContexto()) {
            return null;
        }

        $organizacion = Organizacion::query()->with('plan')->find($contexto->id());
        $estado = $organizacion?->estadoSuscripcion();

        if ($organizacion === null || $estado === null || $estado === EstadoSuscripcion::Vigente) {
            return null;
        }

        return [
            'estado' => $estado->value,
            'etiqueta' => $estado->etiqueta(),
            'plan' => $organizacion->plan?->nombre,
            'venceEn' => $organizacion->suscripcion_vence_en?->toIso8601String(),
            'graciaHasta' => EstadoSuscripcion::finDeGracia($organizacion)?->toIso8601String(),
        ];
    }
}
