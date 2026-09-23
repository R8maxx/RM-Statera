<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El análisis de impacto en el negocio, por servicio: § 4.11 y `op.cont.*`.
 *
 * **Abre la continuidad**, y es el primer módulo de la fase que no toca ninguna
 * implantación de `op.cont.*` (invariante 4): calcula un umbral tolerable y lo
 * contrasta con lo que la organización se ha comprometido a cumplir, pero no
 * marca nada como implantado. Eso lo sigue decidiendo quien valora ese control.
 *
 * Tres decisiones que no se ven leyendo el esquema:
 *
 * 1. **Los cinco tramos de impacto van en cinco columnas**, `impacto_4h` …
 *    `impacto_1m`, y no en filas ni en JSONB. Mismo reparto que las dimensiones
 *    de un incidente o la valoración de un activo: son cinco horizontes fijos
 *    del MTPD (`4h/1d/3d/1s/1m`), no una lista abierta, y se consultan y se
 *    indexan como columnas.
 *
 * 2. **La monotonía la impone un `CHECK`, no sólo el formulario.** El impacto de
 *    no recuperar un servicio no puede *bajar* según pasa el tiempo —lo que a
 *    las cuatro horas es «alto» no se vuelve «medio» a la semana—, y esa regla
 *    vale también para un importador o para un seed. Se compara por posición en
 *    la escala ordenada, con los literales escritos a mano y no desde
 *    `NivelImpacto::cases()`, por la misma razón que el resto de `CHECK` del
 *    proyecto: una base recién migrada tiene que rechazar un valor nuevo aunque
 *    a alguien se le olvide su migración.
 *
 * 3. **El umbral tolerable (MTPD) no se guarda: se deriva.** Es el primer tramo
 *    cuyo impacto llega a `muy_alto`, y `UmbralTolerable` lo recorre en el
 *    dominio. Guardarlo aquí sería una copia que se desincroniza de los cinco
 *    tramos que la justifican — el mismo argumento que ya vale para la
 *    valoración efectiva de un activo.
 *
 * **`aprobado_por_id` no lleva `CHECK` que lo acople al estado**: a diferencia
 * de `fecha_aprobacion`, perderlo si el usuario se borra (`nullOnDelete`) no
 * puede tumbar la fila. Lo que sí queda acoplado en las dos direcciones es
 * `estado = 'aprobado'` con `fecha_aprobacion IS NOT NULL`.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const ESTADOS = ['borrador', 'aprobado', 'obsoleto'];

    /** @var list<string> */
    private const NIVELES = ['bajo', 'medio', 'alto', 'muy_alto'];

    /**
     * Los cinco tramos del MTPD, en orden creciente de horizonte. El orden
     * importa: es exactamente el que usa el `CHECK` de monotonía.
     *
     * @var list<string>
     */
    private const TRAMOS = ['impacto_4h', 'impacto_1d', 'impacto_3d', 'impacto_1s', 'impacto_1m'];

    public function up(): void
    {
        $estados = $this->lista(self::ESTADOS);
        $niveles = $this->lista(self::NIVELES);

        Schema::create('bia_servicios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            /*
             * El servicio. Es un `activo_id` y no una tabla propia de
             * «servicios»: un servicio ES un activo de `TipoActivo::Servicios`
             * en el inventario, y duplicarlo aquí sería volver a las hojas de
             * cálculo que el producto sustituye. Que sea un servicio de verdad
             * lo exige el dominio (`RegistrarBia`), no un `CHECK`: comprobar el
             * tipo de otra tabla desde una restricción de ésta no es portable ni
             * se puede indexar.
             */
            $table->foreignId('activo_id')->constrained('activos')->cascadeOnDelete();

            // Los cinco tramos del MTPD. Ver el punto 1 de la cabecera.
            foreach (self::TRAMOS as $tramo) {
                $table->string($tramo)->default('bajo');
            }

            $table->unsignedInteger('rto_horas');
            $table->unsignedInteger('rpo_horas');
            $table->text('justificacion')->nullable();

            $table->string('estado')->default('borrador');
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('aprobado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha_aprobacion')->nullable();
            $table->date('fecha_revision')->nullable();

            $table->timestamps();

            // Un servicio, un BIA. Uno nuevo sustituye al anterior con la
            // edición, no con una segunda fila que compita con la primera.
            $table->unique(['organizacion_id', 'activo_id']);
            $table->index(['organizacion_id', 'estado']);
            $table->index(['organizacion_id', 'fecha_revision']);
        });

        DB::statement("ALTER TABLE bia_servicios ADD CONSTRAINT bia_servicios_estado_check CHECK (estado IN ({$estados}))");

        foreach (self::TRAMOS as $tramo) {
            DB::statement("ALTER TABLE bia_servicios ADD CONSTRAINT bia_servicios_{$tramo}_check CHECK ({$tramo} IN ({$niveles}))");
        }

        DB::statement('ALTER TABLE bia_servicios ADD CONSTRAINT bia_servicios_rto_check CHECK (rto_horas > 0)');
        DB::statement('ALTER TABLE bia_servicios ADD CONSTRAINT bia_servicios_rpo_check CHECK (rpo_horas >= 0)');

        // En las dos direcciones, como el cierre de un incidente o de una
        // auditoría: aprobado sin fecha no se puede fechar después sin
        // inventársela, y una fecha de aprobación sin el estado es contradictoria.
        DB::statement("ALTER TABLE bia_servicios ADD CONSTRAINT bia_servicios_aprobacion_coherente_check CHECK ((estado = 'aprobado') = (fecha_aprobacion IS NOT NULL))");

        /*
         * La monotonía. Ver el punto 2 de la cabecera: el impacto no puede
         * bajar de un tramo al siguiente. Se compara por posición en la escala
         * ordenada, con los literales escritos a mano.
         */
        DB::statement(<<<'SQL'
            ALTER TABLE bia_servicios ADD CONSTRAINT bia_servicios_monotonia_check CHECK (
                array_position(ARRAY['bajo','medio','alto','muy_alto'], impacto_4h)
                    <= array_position(ARRAY['bajo','medio','alto','muy_alto'], impacto_1d)
                AND array_position(ARRAY['bajo','medio','alto','muy_alto'], impacto_1d)
                    <= array_position(ARRAY['bajo','medio','alto','muy_alto'], impacto_3d)
                AND array_position(ARRAY['bajo','medio','alto','muy_alto'], impacto_3d)
                    <= array_position(ARRAY['bajo','medio','alto','muy_alto'], impacto_1s)
                AND array_position(ARRAY['bajo','medio','alto','muy_alto'], impacto_1s)
                    <= array_position(ARRAY['bajo','medio','alto','muy_alto'], impacto_1m)
            )
        SQL);

        /*
         * El histórico (invariante 7). La pregunta del auditor no es «¿está
         * aprobado?», es «¿desde cuándo, y quién lo aprobó?».
         */
        Schema::create('bia_servicio_transiciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('bia_servicio_id')->constrained('bia_servicios')->cascadeOnDelete();

            $table->string('estado_anterior')->nullable();
            $table->string('estado_nuevo');

            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('nota')->nullable();

            $table->timestamps();

            $table->index(['bia_servicio_id', 'created_at']);
        });

        DB::statement("ALTER TABLE bia_servicio_transiciones ADD CONSTRAINT bia_servicio_transiciones_anterior_check CHECK (estado_anterior IS NULL OR estado_anterior IN ({$estados}))");
        DB::statement("ALTER TABLE bia_servicio_transiciones ADD CONSTRAINT bia_servicio_transiciones_nuevo_check CHECK (estado_nuevo IN ({$estados}))");
    }

    public function down(): void
    {
        Schema::dropIfExists('bia_servicio_transiciones');
        Schema::dropIfExists('bia_servicios');
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
