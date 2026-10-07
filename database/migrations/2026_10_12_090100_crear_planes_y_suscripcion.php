<?php

declare(strict_types=1);

use App\Domain\Plataforma\Enums\AccionPlataforma;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El plan y la suscripción, modelados y sin cobrar (punto 43).
 *
 * **La suscripción vive en `organizaciones` y no en una tabla aparte.** Cada
 * cliente tiene una y sólo una, y la plataforma tiene que leerlas todas para
 * listar a sus clientes. Una tabla `suscripciones` con `organizacion_id`
 * exigiría RLS —`RlsDeclaradaTest`—, y con RLS la plataforma no podría
 * listarlas sin `comoMantenimiento()`. La raíz del tenant ya está fuera de las
 * tres capas, y la suscripción es un atributo de la raíz.
 *
 * **El histórico sí va aparte (invariante 7)**, en `transiciones_suscripcion`,
 * inmutable por privilegios como las trazas. Lleva `organizacion_afectada_id`
 * y no `organizacion_id` por lo mismo que `eventos_plataforma`: es lo que hizo
 * la plataforma, no un dato propio del cliente.
 *
 * `plan_id` nulo es «sin plan»: sin límites y sin vencimiento. Es el estado de
 * toda organización anterior a esto, y el del uso interno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planes', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            // Nulo es «sin límite».
            $table->unsignedInteger('limite_cuentas')->nullable();
            $table->unsignedInteger('limite_sistemas')->nullable();
            $table->unsignedSmallInteger('dias_gracia')->default(15);
            $table->boolean('activo')->default(true);
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE planes ADD CONSTRAINT planes_codigo_check CHECK (codigo ~ '^[a-z0-9][a-z0-9-]*$')");
        DB::statement('ALTER TABLE planes ADD CONSTRAINT planes_limites_check CHECK ((limite_cuentas IS NULL OR limite_cuentas > 0) AND (limite_sistemas IS NULL OR limite_sistemas > 0))');

        Schema::table('organizaciones', function (Blueprint $table): void {
            $table->foreignId('plan_id')->nullable()->constrained('planes')->restrictOnDelete();
            $table->timestampTz('suscripcion_inicia_en')->nullable();
            // Nulo es «no vence». Sin `CHECK` contra el inicio, a propósito: un
            // contrato que ya venció se registra con su fecha pasada, y el
            // inicio es el día que se le puso el plan en Statera.
            $table->timestampTz('suscripcion_vence_en')->nullable();
            // El hueco para una pasarela de pago futura: el id de su cliente o
            // de su suscripción. Nada lo lee todavía.
            $table->string('suscripcion_referencia_externa')->nullable();
        });

        Schema::create('transiciones_suscripcion', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_afectada_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('plan_anterior_id')->nullable()->constrained('planes')->restrictOnDelete();
            $table->foreignId('plan_nuevo_id')->nullable()->constrained('planes')->restrictOnDelete();
            $table->timestampTz('vence_en_anterior')->nullable();
            $table->timestampTz('vence_en_nuevo')->nullable();
            $table->text('motivo')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['organizacion_afectada_id', 'created_at']);
        });

        DB::statement('REVOKE UPDATE, DELETE, TRUNCATE ON transiciones_suscripcion FROM statera_app');

        $this->accionesDePlataforma(AccionPlataforma::cases());
    }

    public function down(): void
    {
        $this->accionesDePlataforma(array_filter(
            AccionPlataforma::cases(),
            static fn (AccionPlataforma $accion): bool => ! in_array($accion, [AccionPlataforma::SuscripcionCambiada, AccionPlataforma::PlanGuardado], true),
        ));

        DB::statement('GRANT UPDATE, DELETE, TRUNCATE ON transiciones_suscripcion TO statera_app');
        Schema::dropIfExists('transiciones_suscripcion');

        Schema::table('organizaciones', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn(['suscripcion_inicia_en', 'suscripcion_vence_en', 'suscripcion_referencia_externa']);
        });

        Schema::dropIfExists('planes');
    }

    /** @param  iterable<AccionPlataforma>  $acciones */
    private function accionesDePlataforma(iterable $acciones): void
    {
        $valores = [];

        foreach ($acciones as $accion) {
            $valores[] = "'{$accion->value}'";
        }

        DB::statement('ALTER TABLE eventos_plataforma DROP CONSTRAINT IF EXISTS eventos_plataforma_accion_check');
        DB::statement('ALTER TABLE eventos_plataforma ADD CONSTRAINT eventos_plataforma_accion_check CHECK (accion IN ('.implode(', ', $valores).'))');
    }
};
