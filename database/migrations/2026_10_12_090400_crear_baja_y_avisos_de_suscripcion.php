<?php

declare(strict_types=1);

use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Enums\HitoSuscripcion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La baja de una organización y los avisos de vencimiento (punto 46).
 *
 * **Dar de baja no borra nada.** Es un estado con fecha y motivo, y se puede
 * deshacer. Lo que hay dentro —el inventario, las evidencias, la traza— es del
 * cliente y prueba lo que hizo, así que no se toca. `activa` existía desde la
 * primera migración sin lector; ahora la mueve la baja, y `baja_en` dice desde
 * cuándo.
 *
 * **`avisos_suscripcion` recuerda qué aviso se mandó para qué vencimiento**,
 * para no mandarlo dos veces. La clave incluye `vence_en`: al renovar, el
 * vencimiento cambia y los avisos vuelven a empezar. Lleva
 * `organizacion_afectada_id`, como el resto de datos de la plataforma, y no
 * `organizacion_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizaciones', function (Blueprint $table): void {
            $table->timestampTz('baja_en')->nullable();
            $table->text('motivo_baja')->nullable();
        });

        Schema::create('avisos_suscripcion', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_afectada_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->string('hito');
            $table->timestampTz('vence_en');
            $table->timestampTz('enviado_en')->useCurrent();

            $table->unique(['organizacion_afectada_id', 'hito', 'vence_en']);
        });

        $hitos = implode(', ', array_map(static fn (HitoSuscripcion $hito): string => "'{$hito->value}'", HitoSuscripcion::cases()));
        DB::statement("ALTER TABLE avisos_suscripcion ADD CONSTRAINT avisos_suscripcion_hito_check CHECK (hito IN ({$hitos}))");

        $this->accionesDePlataforma(AccionPlataforma::cases());
    }

    public function down(): void
    {
        $this->accionesDePlataforma(array_filter(
            AccionPlataforma::cases(),
            static fn (AccionPlataforma $accion): bool => ! in_array($accion, [AccionPlataforma::OrganizacionBaja, AccionPlataforma::OrganizacionReactivada], true),
        ));

        Schema::dropIfExists('avisos_suscripcion');

        Schema::table('organizaciones', function (Blueprint $table): void {
            $table->dropColumn(['baja_en', 'motivo_baja']);
        });
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
