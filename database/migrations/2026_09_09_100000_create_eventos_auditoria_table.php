<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Traza inmutable de toda operación sobre entidades de cumplimiento.
 *
 * Invariante 8: la herramienta entra en el alcance del propio SGSI. Contiene el
 * inventario, las vulnerabilidades y las evidencias, así que «quién cambió qué y
 * cuándo» no es telemetría, es un requisito no funcional del § 6 de la
 * especificación.
 *
 * Dos cosas que no son accidentales:
 *
 * 1. **La inmutabilidad la impone PostgreSQL**, no PHP. `REVOKE UPDATE, DELETE`
 *    sobre el rol de la aplicación. Un `throw` en un observador de Eloquent lo
 *    esquiva cualquier `DB::table()->update()`, y una traza que se puede
 *    reescribir no prueba nada ante un auditor.
 *
 * 2. **`valor_anterior` y `valor_nuevo` van en JSONB con índice GIN.** Lo que se
 *    pregunta de un log de auditoría es «cuándo cambió el responsable de esto» o
 *    «quién tocó alguna vez el campo X», y eso es una consulta por contenido del
 *    documento, no por columna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_auditoria', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            // Nulo cuando la operación la provoca un comando o un recálculo, no
            // una persona. Y `nullOnDelete` porque el evento sobrevive a la baja
            // del usuario: borrar la traza al borrar la cuenta sería justo lo
            // contrario de lo que se pide.
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();

            // La entidad va por nombre y no por relación polimórfica con clave
            // foránea: el evento tiene que seguir siendo legible cuando la fila
            // a la que se refiere ya no exista.
            $table->string('entidad');
            $table->unsignedBigInteger('entidad_id');
            $table->string('accion');

            $table->jsonb('valor_anterior')->nullable();
            $table->jsonb('valor_nuevo')->nullable();

            $table->string('ip', 45)->nullable();

            // Sin `updated_at`: es histórico, no se edita. Igual que
            // `implantacion_transiciones`.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entidad', 'entidad_id']);
            $table->index(['organizacion_id', 'created_at']);
        });

        DB::statement("ALTER TABLE eventos_auditoria ADD CONSTRAINT eventos_auditoria_accion_check CHECK (accion IN ('creado', 'actualizado', 'eliminado'))");

        // Lo que se pregunta a un log de auditoría es por contenido del
        // documento, no por columna: GIN es el índice de esa pregunta.
        DB::statement('CREATE INDEX eventos_auditoria_anterior_gin ON eventos_auditoria USING GIN (valor_anterior)');
        DB::statement('CREATE INDEX eventos_auditoria_nuevo_gin ON eventos_auditoria USING GIN (valor_nuevo)');

        // Primera barrera, y la que no se puede esquivar desde la aplicación.
        // `statera_app` es el rol con el que se conecta Statera: NOSUPERUSER y
        // NOBYPASSRLS, creado en docker/postgres/init.
        DB::statement('REVOKE UPDATE, DELETE, TRUNCATE ON eventos_auditoria FROM statera_app');
    }

    public function down(): void
    {
        // Devolver los privilegios antes de soltar la tabla: si la migración se
        // revierte, el rol tiene que quedar como estaba.
        DB::statement('GRANT UPDATE, DELETE, TRUNCATE ON eventos_auditoria TO statera_app');

        Schema::dropIfExists('eventos_auditoria');
    }
};
