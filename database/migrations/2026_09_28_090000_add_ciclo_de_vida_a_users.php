<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El ciclo de vida de una cuenta (§ 4.19): invitada, activa, desactivada y,
 * para el auditor externo, caducada.
 *
 * **El estado no se guarda: se deriva de cuatro fechas.** Una columna `estado`
 * tendría que ponerse de acuerdo con `acceso_hasta` todos los días a las cero
 * horas, y caducar es una fecha y no un estado — la misma decisión que tomó la
 * conformidad del § 4.17.
 *
 * **Desactivar no borra.** La fila es autora y responsable en todo el
 * histórico: borrarla dejaría a nulo el `usuario_id` de la traza, que es
 * justo lo contrario de lo que se pide.
 *
 * `activada_en` se rellena con `created_at` en las cuentas que ya existían:
 * todas se crearon por seeder con contraseña, así que ya están activas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('invitada_en')->nullable();
            $table->timestamp('activada_en')->nullable();
            $table->timestamp('desactivada_en')->nullable();
            $table->string('motivo_desactivacion', 500)->nullable();
            $table->date('acceso_hasta')->nullable();
            $table->timestamp('ultimo_acceso_en')->nullable();
        });

        DB::statement('UPDATE users SET activada_en = created_at WHERE activada_en IS NULL');

        DB::statement('ALTER TABLE users ADD CONSTRAINT users_motivo_desactivacion_check CHECK (motivo_desactivacion IS NULL OR desactivada_en IS NOT NULL)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_motivo_desactivacion_check');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'invitada_en',
                'activada_en',
                'desactivada_en',
                'motivo_desactivacion',
                'acceso_hasta',
                'ultimo_acceso_en',
            ]);
        });
    }
};
