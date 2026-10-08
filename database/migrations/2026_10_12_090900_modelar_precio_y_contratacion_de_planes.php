<?php

declare(strict_types=1);

use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Enums\OrigenCambioSuscripcion;
use App\Domain\Plataforma\Enums\PeriodoFacturacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El precio, el periodo y la contratación por la propia organización
 * (punto 51).
 *
 * Hasta aquí el plan lo cambiaba sólo la plataforma y no tenía precio. Ahora
 * la organización elige y cambia de plan por su cuenta, y el día que haya
 * pasarela se cobrará lo que toque al confirmar. **Todavía no se cobra**: el
 * importe se calcula, se enseña y queda en el histórico, y nada más.
 *
 * - **`planes.contratable`**: qué planes puede elegir la organización. Uno sin
 *   límites, como «Ilimitado», lo asigna sólo la plataforma. Es bandera y no
 *   regla («todo plan sin límites es de la plataforma») para que lo decida la
 *   plataforma plan a plan. Un plan contratable tiene precio: no se le puede
 *   ofrecer a nadie algo sin decirle cuánto cuesta.
 * - **El precio en céntimos**, entero, por lo mismo que nunca se guarda dinero
 *   en coma flotante. Sin IVA: el impuesto es de la factura, no del plan.
 * - **`organizaciones.suscripcion_periodo`**: mensual o anual. Nulo sin plan, y
 *   nulo en un plan que la plataforma puso a mano sin periodo.
 * - **El histórico gana el periodo, el importe y el origen.** El importe lleva
 *   signo: negativo es saldo a favor de la organización, al bajar de plan.
 *
 * `CHECK` construidos desde los enums, como en el resto del repositorio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planes', function (Blueprint $table): void {
            $table->unsignedInteger('precio_mensual_centimos')->nullable();
            $table->unsignedSmallInteger('descuento_anual')->default(0);
            $table->boolean('contratable')->default(false);
        });

        DB::statement('ALTER TABLE planes ADD CONSTRAINT planes_descuento_anual_check CHECK (descuento_anual BETWEEN 0 AND 90)');
        DB::statement('ALTER TABLE planes ADD CONSTRAINT planes_contratable_con_precio_check CHECK (NOT contratable OR precio_mensual_centimos IS NOT NULL)');

        Schema::table('organizaciones', function (Blueprint $table): void {
            $table->string('suscripcion_periodo')->nullable();
        });

        $periodos = $this->lista(array_map(static fn (PeriodoFacturacion $periodo): string => $periodo->value, PeriodoFacturacion::cases()));
        $origenes = $this->lista(array_map(static fn (OrigenCambioSuscripcion $origen): string => $origen->value, OrigenCambioSuscripcion::cases()));

        DB::statement("ALTER TABLE organizaciones ADD CONSTRAINT organizaciones_suscripcion_periodo_check CHECK (suscripcion_periodo IN ({$periodos}))");

        Schema::table('transiciones_suscripcion', function (Blueprint $table): void {
            $table->string('periodo_anterior')->nullable();
            $table->string('periodo_nuevo')->nullable();
            $table->integer('importe_centimos')->nullable();
            $table->string('origen')->default(OrigenCambioSuscripcion::Plataforma->value);
        });

        DB::statement("ALTER TABLE transiciones_suscripcion ADD CONSTRAINT transiciones_suscripcion_periodos_check CHECK (periodo_anterior IN ({$periodos}) AND periodo_nuevo IN ({$periodos}))");
        DB::statement("ALTER TABLE transiciones_suscripcion ADD CONSTRAINT transiciones_suscripcion_origen_check CHECK (origen IN ({$origenes}))");

        $this->accionesDePlataforma(AccionPlataforma::cases());
    }

    public function down(): void
    {
        $this->accionesDePlataforma(array_filter(
            AccionPlataforma::cases(),
            static fn (AccionPlataforma $accion): bool => $accion !== AccionPlataforma::PlanContratado,
        ));

        Schema::table('transiciones_suscripcion', function (Blueprint $table): void {
            $table->dropColumn(['periodo_anterior', 'periodo_nuevo', 'importe_centimos', 'origen']);
        });

        Schema::table('organizaciones', function (Blueprint $table): void {
            $table->dropColumn('suscripcion_periodo');
        });

        Schema::table('planes', function (Blueprint $table): void {
            $table->dropColumn(['precio_mensual_centimos', 'descuento_anual', 'contratable']);
        });
    }

    /** @param  list<string>  $valores */
    private function lista(array $valores): string
    {
        return implode(', ', array_map(static fn (string $valor): string => "'{$valor}'", $valores));
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
