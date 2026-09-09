<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Autorizacion\Enums\Rol;
use App\Domain\Autorizacion\SembrarRoles;
use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Domain\Evidencia\Enums\PeriodicidadRenovacion;
use App\Domain\Evidencia\Enums\TipoEvidencia;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Evidencia\VincularEvidencia;
use App\Domain\Implantacion\GeneradorImplantaciones;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Sistema\Models\ValoracionDimension;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Datos con los que arrancar en local.
 *
 * Todo sintético: ni un dato real de ningún cliente. Necesita el catálogo ya
 * importado (`php artisan catalogo:importar`); sin marcos no hay nada que
 * generar.
 */
class DesarrolloSeeder extends Seeder
{
    public function run(): void
    {
        $organizacion = Organizacion::query()->firstOrCreate(
            ['cif' => 'B00000000'],
            [
                'nombre' => 'Organización de pruebas',
                'sector' => 'Servicios digitales',
                'sujeto_obligado_ens' => false,
                'proveedor_sector_publico' => true,
                'activa' => true,
            ],
        );

        // Sin contexto no se escribe ni una fila de datos propios: la política de
        // RLS deniega por defecto y el scope de Eloquent tampoco deja pasar.
        app(ContextoOrganizacion::class)->establecer($organizacion);

        // Los roles se siembran antes que los usuarios: sin ellos, asignar uno
        // falla, y con `teams = true` cada organización tiene los suyos.
        app(SembrarRoles::class)->paraOrganizacion($organizacion);

        $this->usuario(
            $organizacion,
            'responsable@statera.test',
            'Responsable de seguridad',
            Rol::ResponsableSeguridad,
        );

        // Los otros dos existen para poder comprobar de verdad qué ve cada rol:
        // un permiso que nadie ejerce no está probado.
        $this->usuario($organizacion, 'tecnico@statera.test', 'Técnica de sistemas', Rol::Tecnico);
        $this->usuario($organizacion, 'auditor@statera.test', 'Auditor externo', Rol::Auditor);

        $marco = Marco::query()->where('codigo', 'ENS-RD311-2022')->first();

        if ($marco === null) {
            $this->command->warn('Catálogo sin importar: ejecuta `php artisan catalogo:importar` y repite el seeder.');

            return;
        }

        $sistema = Sistema::query()->firstOrCreate(
            ['codigo' => 'SIS-0001'],
            [
                'marco_id' => $marco->id,
                'nombre' => 'Plataforma de servicios internos',
                'descripcion' => 'Sistema de ejemplo para desarrollo.',
                'alcance_declarado' => 'Servicios internos alojados en la nube corporativa.',
            ],
        );

        // Cinco dimensiones en bajo: categoría básica, que es el objetivo de la
        // fase actual.
        foreach (Dimension::cases() as $dimension) {
            ValoracionDimension::query()->updateOrCreate(
                ['sistema_id' => $sistema->id, 'dimension' => $dimension->value],
                ['nivel' => NivelDimension::Bajo->value],
            );
        }

        app(GeneradorImplantaciones::class)->generar($sistema->fresh());

        $this->command->info(sprintf(
            'Sistema %s: %d implantaciones.',
            $sistema->codigo,
            $sistema->implantaciones()->count(),
        ));

        $this->evidenciaDeEjemplo($sistema);
    }

    /** Alta idempotente de un usuario con su rol. Contraseña de desarrollo. */
    private function usuario(Organizacion $organizacion, string $email, string $nombre, Rol $rol): void
    {
        $usuario = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $nombre,
                'password' => 'contrasena-de-desarrollo',
                'organizacion_id' => $organizacion->id,
            ],
        );

        $usuario->syncRoles([$rol->value]);
    }

    /**
     * Una evidencia vinculada a dos medidas, para que el vínculo N:M se vea sin
     * tener que montarlo a mano.
     *
     * De enlace y no de fichero: el seeder no debe depender de que MinIO esté
     * levantado, y lo que se quiere enseñar es el vínculo, no la subida.
     */
    private function evidenciaDeEjemplo(Sistema $sistema): void
    {
        $evidencia = Evidencia::query()->firstOrCreate(
            ['titulo' => 'Configuración del segundo factor en el IdP'],
            [
                'tipo' => TipoEvidencia::Captura->value,
                'descripcion' => 'Captura sintética del panel de administración del proveedor de identidad.',
                'url_externa' => 'https://idp.interno.ejemplo/administracion/mfa',
                'fecha_obtencion' => Carbon::today()->subMonth(),
                'periodicidad_renovacion' => PeriodicidadRenovacion::Semestral->value,
            ],
        );

        // Las de control de acceso: es donde una captura del IdP prueba de
        // verdad algo, y suelen ser varias a la vez.
        $implantaciones = $sistema->implantaciones()
            ->whereHas('requisito', fn (Builder $consulta) => $consulta->where('codigo', 'like', 'op.acc.%'))
            ->limit(2)
            ->get();

        $vincular = app(VincularEvidencia::class);

        foreach ($implantaciones as $implantacion) {
            $vincular->vincular($evidencia, $implantacion, null, 'Prueba el mecanismo de autenticación.');
        }

        $this->command->info(sprintf(
            'Evidencia de ejemplo vinculada a %d requisitos.',
            $implantaciones->count(),
        ));
    }
}
