<?php

declare(strict_types=1);

use App\Domain\Plataforma\Enums\AccionPlataforma;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La traza de la plataforma aprende a registrar el cambio de perfil y la
 * retirada de un administrador (punto 49). `NOT VALID`, como las demás
 * ampliaciones: los eventos no se pueden borrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->accionesDePlataforma(AccionPlataforma::cases());
    }

    public function down(): void
    {
        $this->accionesDePlataforma(array_filter(
            AccionPlataforma::cases(),
            static fn (AccionPlataforma $accion): bool => ! in_array($accion, [AccionPlataforma::AdministradorPerfilCambiado, AccionPlataforma::AdministradorRetirado], true),
        ));
    }

    /** @param  iterable<AccionPlataforma>  $acciones */
    private function accionesDePlataforma(iterable $acciones): void
    {
        $valores = [];

        foreach ($acciones as $accion) {
            $valores[] = "'{$accion->value}'";
        }

        DB::statement('ALTER TABLE eventos_plataforma DROP CONSTRAINT IF EXISTS eventos_plataforma_accion_check');
        DB::statement('ALTER TABLE eventos_plataforma ADD CONSTRAINT eventos_plataforma_accion_check CHECK (accion IN ('.implode(', ', $valores).')) NOT VALID');
    }
};
