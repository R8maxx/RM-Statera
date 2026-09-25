<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El registro de vulnerabilidades: invariante 8, A.8.8 de ISO y `op.exp.4` del ENS.
 *
 * **Lo que faltaba no era una columna**: `riesgos.vulnerabilidad` es la condición
 * que hace creíble la amenaza en un escenario de MAGERIT, y esto es un hallazgo
 * técnico con severidad, activos afectados y plazo de remediación. Cuatro
 * tablas, cuatro columnas en `organizaciones` y cuatro decisiones.
 *
 * 1. **La severidad se deriva del CVSS cuando lo hay**, con los tramos de la
 *    especificación de FIRST (CVSS v3.1, § 5): 0 ninguna, 0,1–3,9 baja, 4,0–6,9
 *    media, 7,0–8,9 alta y 9,0–10 crítica. Lo impone un `CHECK`: con puntuación,
 *    la severidad no se elige. Sin puntuación —un aviso del fabricante, un
 *    hallazgo de auditoría— se declara.
 *
 * 2. **El plazo de remediación es política de la organización**: días por
 *    severidad en su ficha, como los meses de reevaluación de un proveedor. Ni
 *    ISO ni el ENS fijan un número, y los que se siembran —7, 30, 90 y 180— son
 *    práctica habitual y no norma. `fecha_limite` es una copia derivada, que se
 *    guarda porque el calendario la consulta por rango.
 *
 * 3. **Aceptar es de supervisión**: una vulnerabilidad que no se corrige es un
 *    riesgo que la organización asume, y lo firma quien puede, con motivo. Es la
 *    línea de `riesgos.aceptar`.
 *
 * 4. **Cerrar exige verificación escrita**: que el parche se aplicó no es lo
 *    mismo que comprobar que la vulnerabilidad ya no está. Es el paso de la
 *    10.2 que más se olvida, y aquí también.
 *
 * Los literales de los `CHECK` van escritos a mano, como en el resto del
 * repositorio.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const SEVERIDADES = ['informativa', 'baja', 'media', 'alta', 'critica'];

    /** @var list<string> */
    private const ESTADOS = ['abierta', 'en_remediacion', 'mitigada', 'cerrada', 'aceptada', 'falso_positivo'];

    /** @var list<string> */
    private const ORIGENES = ['escaneo', 'aviso', 'fabricante', 'auditoria', 'pentest', 'interna'];

    public function up(): void
    {
        Schema::table('organizaciones', function (Blueprint $table): void {
            $table->unsignedSmallInteger('plazo_vulnerabilidad_critica_dias')->default(7);
            $table->unsignedSmallInteger('plazo_vulnerabilidad_alta_dias')->default(30);
            $table->unsignedSmallInteger('plazo_vulnerabilidad_media_dias')->default(90);
            $table->unsignedSmallInteger('plazo_vulnerabilidad_baja_dias')->default(180);
        });

        DB::statement('ALTER TABLE organizaciones ADD CONSTRAINT organizaciones_plazo_vulnerabilidad_check CHECK (
            plazo_vulnerabilidad_critica_dias BETWEEN 1 AND 730
            AND plazo_vulnerabilidad_alta_dias BETWEEN 1 AND 730
            AND plazo_vulnerabilidad_media_dias BETWEEN 1 AND 730
            AND plazo_vulnerabilidad_baja_dias BETWEEN 1 AND 730
        )');

        Schema::create('vulnerabilidades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            $table->string('codigo');
            $table->string('titulo');
            $table->text('descripcion')->nullable();

            $table->string('cve', 32)->nullable();
            $table->decimal('cvss_puntuacion', 3, 1)->nullable();
            $table->string('cvss_vector')->nullable();
            $table->string('severidad');

            $table->string('origen');
            $table->date('fecha_deteccion');

            // Copia derivada: fecha de detección + los días de su severidad.
            $table->date('fecha_limite')->nullable();

            $table->string('estado')->default('abierta');
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();

            // De dónde viene el arreglo, cuando no depende de la organización.
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->foreignId('riesgo_id')->nullable()->constrained('riesgos')->nullOnDelete();
            $table->foreignId('incidente_id')->nullable()->constrained('incidentes')->nullOnDelete();

            $table->text('remediacion')->nullable();

            $table->text('motivo_aceptacion')->nullable();
            $table->foreignId('aceptada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aceptada_en')->nullable();

            $table->text('verificacion')->nullable();
            $table->foreignId('verificada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cerrada_en')->nullable();

            $table->timestamps();

            $table->unique(['organizacion_id', 'codigo']);
            $table->index(['organizacion_id', 'estado']);
            $table->index(['organizacion_id', 'fecha_limite']);
        });

        $this->check('vulnerabilidades', 'severidad', self::SEVERIDADES);
        $this->check('vulnerabilidades', 'estado', self::ESTADOS);
        $this->check('vulnerabilidades', 'origen', self::ORIGENES);

        DB::statement('ALTER TABLE vulnerabilidades ADD CONSTRAINT vulnerabilidades_codigo_check CHECK (length(trim(codigo)) > 0)');
        DB::statement('ALTER TABLE vulnerabilidades ADD CONSTRAINT vulnerabilidades_cvss_rango_check CHECK (cvss_puntuacion IS NULL OR cvss_puntuacion BETWEEN 0 AND 10)');

        // Ver el punto 1 de la cabecera: con puntuación, la severidad no se elige.
        DB::statement("ALTER TABLE vulnerabilidades ADD CONSTRAINT vulnerabilidades_cvss_severidad_check CHECK (
            cvss_puntuacion IS NULL OR severidad = CASE
                WHEN cvss_puntuacion >= 9.0 THEN 'critica'
                WHEN cvss_puntuacion >= 7.0 THEN 'alta'
                WHEN cvss_puntuacion >= 4.0 THEN 'media'
                WHEN cvss_puntuacion > 0 THEN 'baja'
                ELSE 'informativa'
            END
        )");

        // Aceptada lleva quién, cuándo y por qué; cerrada, la verificación.
        DB::statement("ALTER TABLE vulnerabilidades ADD CONSTRAINT vulnerabilidades_aceptada_check CHECK (
            estado <> 'aceptada' OR (aceptada_en IS NOT NULL AND length(trim(coalesce(motivo_aceptacion, ''))) > 0)
        )");
        DB::statement("ALTER TABLE vulnerabilidades ADD CONSTRAINT vulnerabilidades_cerrada_check CHECK (
            estado <> 'cerrada' OR (cerrada_en IS NOT NULL AND length(trim(coalesce(verificacion, ''))) > 0)
        )");

        Schema::create('vulnerabilidad_activo', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('vulnerabilidad_id')->constrained('vulnerabilidades')->cascadeOnDelete();
            $table->foreignId('activo_id')->constrained('activos')->cascadeOnDelete();

            $table->unique(['vulnerabilidad_id', 'activo_id']);
            $table->index(['organizacion_id', 'activo_id']);
        });

        Schema::create('vulnerabilidad_transiciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('vulnerabilidad_id')->constrained('vulnerabilidades')->cascadeOnDelete();
            $table->string('estado_anterior')->nullable();
            $table->string('estado_nuevo');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('nota')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organizacion_id', 'vulnerabilidad_id']);
        });

        $this->check('vulnerabilidad_transiciones', 'estado_nuevo', self::ESTADOS);

        Schema::create('vulnerabilidad_tarea', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('vulnerabilidad_id')->constrained('vulnerabilidades')->cascadeOnDelete();
            $table->foreignId('tarea_id')->constrained('tareas')->cascadeOnDelete();
            $table->foreignId('vinculada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['vulnerabilidad_id', 'tarea_id']);
            $table->index(['organizacion_id', 'tarea_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vulnerabilidad_tarea');
        Schema::dropIfExists('vulnerabilidad_transiciones');
        Schema::dropIfExists('vulnerabilidad_activo');
        Schema::dropIfExists('vulnerabilidades');

        DB::statement('ALTER TABLE organizaciones DROP CONSTRAINT IF EXISTS organizaciones_plazo_vulnerabilidad_check');

        Schema::table('organizaciones', function (Blueprint $table): void {
            $table->dropColumn([
                'plazo_vulnerabilidad_critica_dias',
                'plazo_vulnerabilidad_alta_dias',
                'plazo_vulnerabilidad_media_dias',
                'plazo_vulnerabilidad_baja_dias',
            ]);
        });
    }

    /** @param  list<string>  $valores */
    private function check(string $tabla, string $columna, array $valores): void
    {
        $lista = implode(', ', array_map(static fn (string $valor): string => "'{$valor}'", $valores));

        DB::statement("ALTER TABLE {$tabla} ADD CONSTRAINT {$tabla}_{$columna}_check CHECK ({$columna} IN ({$lista}))");
    }
};
