<?php

declare(strict_types=1);

use App\Domain\Plataforma\Enums\AccionPlataforma;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La plataforma: quién administra Statera y qué deja escrito (punto 41).
 *
 * **El administrador vive en `users`**, con la misma entrada, el mismo segundo
 * factor y las mismas sesiones que cualquiera. Lo distingue una marca, y el
 * `CHECK` impide que una cuenta sea las dos cosas: administrar la plataforma y
 * pertenecer a un cliente. Es de un solo sentido porque `organizacion_id` ya es
 * `nullOnDelete` y una cuenta de organización puede quedarse sin ella.
 *
 * **Su traza no puede ir a `eventos_auditoria`**: esa tabla está bajo RLS y
 * cada fila tiene un tenant dueño. `eventos_plataforma` es la misma idea sin
 * tenant: inmutable por privilegios, como aquélla. La organización afectada se
 * llama `organizacion_afectada_id` y no `organizacion_id` a propósito: no es
 * una fila de datos propios de nadie, y con ese nombre `RlsDeclaradaTest`
 * exigiría una política que aquí no significa nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('es_plataforma')->default(false);
        });

        DB::statement('ALTER TABLE users ADD CONSTRAINT users_plataforma_sin_organizacion_check CHECK (NOT (es_plataforma AND organizacion_id IS NOT NULL))');

        Schema::create('eventos_plataforma', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('organizacion_afectada_id')->nullable()->constrained('organizaciones')->nullOnDelete();
            $table->string('accion');
            $table->jsonb('detalle')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('created_at');
            $table->index('organizacion_afectada_id');
        });

        $acciones = implode(', ', array_map(
            static fn (AccionPlataforma $accion): string => "'{$accion->value}'",
            AccionPlataforma::cases(),
        ));

        DB::statement("ALTER TABLE eventos_plataforma ADD CONSTRAINT eventos_plataforma_accion_check CHECK (accion IN ({$acciones}))");
        DB::statement('REVOKE UPDATE, DELETE, TRUNCATE ON eventos_plataforma FROM statera_app');
    }

    public function down(): void
    {
        DB::statement('GRANT UPDATE, DELETE, TRUNCATE ON eventos_plataforma TO statera_app');
        Schema::dropIfExists('eventos_plataforma');

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_plataforma_sin_organizacion_check');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('es_plataforma');
        });
    }
};
