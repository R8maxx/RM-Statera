<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las pruebas de un plan de continuidad: § 4.11 y `op.cont.3`.
 *
 * **Un plan sin pruebas es papel.** `op.cont.3` no pide tener un plan escrito,
 * pide comprobarlo, y esta tabla es el registro de esas comprobaciones: qué se
 * probó, cuándo, con qué resultado y —por servicio— si el tiempo real de
 * recuperación se quedó dentro de lo que el propio BIA prometía.
 *
 * **`documento_id` lleva `restrictOnDelete`, no `cascadeOnDelete`.** Un plan de
 * continuidad con pruebas registradas encima es la evidencia de `op.cont.3`;
 * borrar el plan borraría también el historial de que se comprobó, así que la
 * base se niega a hacerlo. Quien de verdad necesite deshacerse de un plan
 * probado tiene que borrar antes las pruebas, a mano y sabiendo lo que hace.
 *
 * **Los servicios de la prueba son una pivote propia**
 * (`prueba_continuidad_servicio`), no `plan_continuidad_servicio` reutilizada:
 * un simulacro puede cubrir sólo una parte de los servicios que el plan
 * declara, y lleva encima el RTO y el RPO alcanzados —cifras de la prueba, no
 * del plan—.
 *
 * **`resultado` no gasta rojo.** Una prueba fallida es la prueba funcionando:
 * es exactamente lo que `op.cont.3` quiere descubrir mientras todavía es un
 * simulacro y no una caída real. Lo que incumpliría de verdad es no probar
 * nunca, y ese incumplimiento no lo pinta un color, lo pinta la ausencia de
 * filas.
 *
 * Dos `CHECK` acoplan el estado con lo que ese estado exige, en las dos
 * direcciones, igual que `estado = 'aprobado'` en `bia_servicios` o
 * `estado = 'cerrado'` en `incidentes`:
 *
 * - `realizada` exige fecha de realización y resultado.
 * - `cancelada` exige un motivo no vacío.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TIPOS = ['sobremesa', 'simulacro', 'tecnica', 'completa'];

    /** @var list<string> */
    private const ESTADOS = ['planificada', 'realizada', 'cancelada'];

    /** @var list<string> */
    private const RESULTADOS = ['superada', 'parcial', 'fallida'];

    public function up(): void
    {
        $tipos = $this->lista(self::TIPOS);
        $estados = $this->lista(self::ESTADOS);
        $resultados = $this->lista(self::RESULTADOS);

        Schema::create('pruebas_continuidad', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            $table->string('codigo');
            $table->string('titulo');

            // El plan. Ver el punto 2 de la cabecera: no se borra un plan con
            // pruebas encima.
            $table->foreignId('documento_id')->constrained('documentos')->restrictOnDelete();

            $table->string('tipo');
            $table->string('estado')->default('planificada');

            $table->date('fecha_prevista');
            $table->date('fecha_realizacion')->nullable();

            $table->string('resultado')->nullable();
            $table->text('conclusiones')->nullable();
            $table->text('motivo_cancelacion')->nullable();

            $table->foreignId('evidencia_id')->nullable()->constrained('evidencias')->nullOnDelete();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['organizacion_id', 'codigo']);
            $table->index(['organizacion_id', 'estado']);
            $table->index(['organizacion_id', 'fecha_prevista']);
        });

        DB::statement("ALTER TABLE pruebas_continuidad ADD CONSTRAINT pruebas_continuidad_tipo_check CHECK (tipo IN ({$tipos}))");
        DB::statement("ALTER TABLE pruebas_continuidad ADD CONSTRAINT pruebas_continuidad_estado_check CHECK (estado IN ({$estados}))");
        DB::statement("ALTER TABLE pruebas_continuidad ADD CONSTRAINT pruebas_continuidad_resultado_check CHECK (resultado IS NULL OR resultado IN ({$resultados}))");

        // En las dos direcciones: realizada sin fecha ni resultado es
        // contradictoria, y una fecha o un resultado puestos sin haber pasado a
        // realizada se inventarían un cierre que nadie decidió.
        DB::statement(<<<'SQL'
            ALTER TABLE pruebas_continuidad ADD CONSTRAINT pruebas_continuidad_realizada_check CHECK (
                (estado = 'realizada') = (fecha_realizacion IS NOT NULL AND resultado IS NOT NULL)
            )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE pruebas_continuidad ADD CONSTRAINT pruebas_continuidad_cancelada_check CHECK (
                (estado = 'cancelada') = (motivo_cancelacion IS NOT NULL AND length(trim(motivo_cancelacion)) > 0)
            )
        SQL);

        /*
         * Los servicios que cubre la prueba, con el RTO y el RPO que se
         * alcanzaron de verdad. Ver el punto 3 de la cabecera: no es
         * `plan_continuidad_servicio` reutilizada.
         */
        Schema::create('prueba_continuidad_servicio', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('prueba_continuidad_id')->constrained('pruebas_continuidad')->cascadeOnDelete();
            $table->foreignId('activo_id')->constrained('activos')->cascadeOnDelete();

            $table->unsignedInteger('rto_alcanzado_horas')->nullable();
            $table->unsignedInteger('rpo_alcanzado_horas')->nullable();

            $table->timestamps();

            $table->unique(['prueba_continuidad_id', 'activo_id']);
            $table->index(['organizacion_id', 'activo_id']);
        });

        /*
         * El histórico (invariante 7), mismo patrón que
         * `incidente_transiciones` y `bia_servicio_transiciones`.
         */
        Schema::create('prueba_continuidad_transiciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('prueba_continuidad_id')->constrained('pruebas_continuidad')->cascadeOnDelete();

            $table->string('estado_anterior')->nullable();
            $table->string('estado_nuevo');

            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('nota')->nullable();

            $table->timestamps();

            $table->index(['prueba_continuidad_id', 'created_at']);
        });

        DB::statement("ALTER TABLE prueba_continuidad_transiciones ADD CONSTRAINT prueba_continuidad_transiciones_anterior_check CHECK (estado_anterior IS NULL OR estado_anterior IN ({$estados}))");
        DB::statement("ALTER TABLE prueba_continuidad_transiciones ADD CONSTRAINT prueba_continuidad_transiciones_nuevo_check CHECK (estado_nuevo IN ({$estados}))");
    }

    public function down(): void
    {
        Schema::dropIfExists('prueba_continuidad_transiciones');
        Schema::dropIfExists('prueba_continuidad_servicio');
        Schema::dropIfExists('pruebas_continuidad');
    }

    /**
     * @param  list<string>  $valores
     */
    private function lista(array $valores): string
    {
        return implode(', ', array_map(
            static fn (string $valor): string => "'".str_replace("'", "''", $valor)."'",
            $valores,
        ));
    }
};
