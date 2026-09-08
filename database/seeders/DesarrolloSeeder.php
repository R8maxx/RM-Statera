<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Domain\Implantacion\GeneradorImplantaciones;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\Sistema\Models\Sistema;
use App\Domain\Sistema\Models\ValoracionDimension;
use App\Models\User;
use Illuminate\Database\Seeder;

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

        User::query()->firstOrCreate(
            ['email' => 'responsable@statera.test'],
            [
                'name' => 'Responsable de seguridad',
                'password' => 'contrasena-de-desarrollo',
                'organizacion_id' => $organizacion->id,
            ],
        );

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
    }
}
