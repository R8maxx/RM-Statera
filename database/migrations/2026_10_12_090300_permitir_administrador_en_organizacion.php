<?php

declare(strict_types=1);

use App\Domain\Plataforma\Enums\AccionPlataforma;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Quien administra la plataforma puede ser además usuario de una organización
 * (punto 45).
 *
 * El punto 41 lo prohibía con un `CHECK`: o eras de la plataforma o de un
 * cliente. César pidió contar con que un administrador trabaje también en
 * alguna organización con su rol, con la misma cuenta. El `CHECK` se va, y lo
 * que lo sustituye es de dominio: dentro de su organización trabaja con su rol;
 * en cualquier otra sólo entra como soporte, en lectura.
 *
 * Y la traza de la plataforma aprende dos verbos: promover una cuenta de
 * cliente a administradora y reenviar una invitación desde la plataforma.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_plataforma_sin_organizacion_check');

        $this->accionesDePlataforma(AccionPlataforma::cases());
    }

    public function down(): void
    {
        $this->accionesDePlataforma(array_filter(
            AccionPlataforma::cases(),
            static fn (AccionPlataforma $accion): bool => ! in_array($accion, [AccionPlataforma::AdministradorPromovido, AccionPlataforma::InvitacionReenviada], true),
        ));

        // `NOT VALID`: puede haber ya administradores con organización, y no se
        // les quita a nadie. Sólo vuelve a impedir los nuevos.
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_plataforma_sin_organizacion_check CHECK (NOT (es_plataforma AND organizacion_id IS NOT NULL)) NOT VALID');
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
