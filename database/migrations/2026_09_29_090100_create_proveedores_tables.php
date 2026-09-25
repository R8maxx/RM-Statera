<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Proveedores y terceros: § 4.9, A.5.19 a A.5.23 y `op.ext`, `op.nub`.
 *
 * Seis tablas, una columna en `activos` y tres en `organizaciones`, y cuatro
 * decisiones que no se ven leyendo el esquema.
 *
 * 1. **El estado lo decide la evaluación**, no un desplegable. Registrar una
 *    evaluación apta homologa, una apta con condiciones deja condicionado y una
 *    no apta rechaza. Lo único que se mueve a mano es retirar y reactivar, que es
 *    una decisión de negocio y no de seguridad. Cada cambio va a
 *    `proveedor_transiciones` (invariante 7).
 *
 * 2. **La criticidad tiene un mínimo derivado.** Se calcula con la valoración más
 *    alta de los activos que presta —`activos.proveedor_id`— y se guarda en
 *    `criticidad_derivada`. `criticidad_declarada` puede subirla libremente y
 *    bajarla sólo con `justificacion_criticidad`, que lo exige el dominio —el
 *    `CHECK` no conoce la derivada de otra tabla—. Sin activos no hay derivada y
 *    hay que declararla: una gestoría o la limpieza con acceso físico no prestan
 *    ningún activo del inventario y siguen siendo terceros.
 *
 * 3. **`proxima_evaluacion` se guarda, y es una copia.** Se deriva de la última
 *    evaluación y de los meses que la organización fija por criticidad, y la
 *    recalcula `RecalcularReevaluacion` cada vez que cambia alguna de las tres
 *    cosas. Se guarda porque el calendario la consulta por rango, como
 *    `bia_servicios.fecha_revision`; derivarla en SQL obligaría a repetir la
 *    política de la organización en una expresión.
 *
 * 4. **Una evaluación registrada no se edita.** Es lo que se comprobó un día
 *    concreto, con lo que decía el contrato ese día: corregirla después es
 *    reescribir lo que se firmó. Se registra otra.
 *
 * Los literales de los `CHECK` van escritos a mano y no desde los enums, como en
 * el resto del repositorio: con `cases()`, una base recién migrada admitiría un
 * valor nuevo aunque faltara su migración.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const ESTADOS = ['en_evaluacion', 'homologado', 'condicionado', 'rechazado', 'retirado'];

    /** @var list<string> */
    private const CRITICIDADES = ['baja', 'media', 'alta'];

    /** @var list<string> */
    private const MODELOS_NUBE = ['iaas', 'paas', 'saas'];

    /** @var list<string> */
    private const UBICACIONES = ['ue_eee', 'tercer_pais', 'desconocida'];

    /** @var list<string> */
    private const RESULTADOS = ['apto', 'apto_con_condiciones', 'no_apto'];

    /** @var list<string> */
    private const RESULTADOS_CLAUSULA = ['cumple', 'no_cumple', 'no_aplica'];

    /** @var list<string> */
    private const TIPOS_CERTIFICACION = ['iso27001', 'ens', 'otra'];

    /** @var list<string> */
    private const CATEGORIAS = ['basica', 'media', 'alta'];

    public function up(): void
    {
        /*
         * La política de reevaluación, en la ficha de la organización: meses por
         * criticidad. Es una decisión de cada cliente, como su metodología de
         * riesgos, y no una constante. Los valores por defecto —12, 24 y 36— son
         * una práctica habitual y no salen de ninguna norma: ni ISO ni el ENS
         * fijan un plazo.
         */
        Schema::table('organizaciones', function (Blueprint $table): void {
            $table->unsignedSmallInteger('reevaluacion_proveedor_alta_meses')->default(12);
            $table->unsignedSmallInteger('reevaluacion_proveedor_media_meses')->default(24);
            $table->unsignedSmallInteger('reevaluacion_proveedor_baja_meses')->default(36);
        });

        DB::statement('ALTER TABLE organizaciones ADD CONSTRAINT organizaciones_reevaluacion_proveedor_check CHECK (
            reevaluacion_proveedor_alta_meses BETWEEN 1 AND 120
            AND reevaluacion_proveedor_media_meses BETWEEN 1 AND 120
            AND reevaluacion_proveedor_baja_meses BETWEEN 1 AND 120
        )');

        Schema::create('proveedores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            $table->string('codigo');
            $table->string('nombre');
            $table->string('cif', 32)->nullable();
            $table->text('servicio_prestado');

            $table->string('estado')->default('en_evaluacion');

            $table->string('criticidad_derivada')->nullable();
            $table->string('criticidad_declarada')->nullable();
            $table->text('justificacion_criticidad')->nullable();

            // `op.nub.1` es exigible en categoría básica: si es nube, cuál.
            $table->boolean('es_nube')->default(false);
            $table->string('modelo_nube')->nullable();

            $table->string('ubicacion_datos')->default('desconocida');
            $table->string('ubicacion_detalle')->nullable();
            $table->boolean('es_subencargado_rgpd')->default(false);

            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notas')->nullable();

            // Ver el punto 3 de la cabecera: una copia recalculada.
            $table->date('proxima_evaluacion')->nullable();

            $table->timestamps();

            $table->unique(['organizacion_id', 'codigo']);
            $table->index(['organizacion_id', 'estado']);
            $table->index(['organizacion_id', 'proxima_evaluacion']);
        });

        $this->check('proveedores', 'estado', self::ESTADOS);
        $this->check('proveedores', 'ubicacion_datos', self::UBICACIONES);
        DB::statement('ALTER TABLE proveedores ADD CONSTRAINT proveedores_criticidad_derivada_check CHECK (criticidad_derivada IS NULL OR criticidad_derivada IN ('.$this->lista(self::CRITICIDADES).'))');
        DB::statement('ALTER TABLE proveedores ADD CONSTRAINT proveedores_criticidad_declarada_check CHECK (criticidad_declarada IS NULL OR criticidad_declarada IN ('.$this->lista(self::CRITICIDADES).'))');
        // Sin activos no hay derivada, y entonces tiene que haber declarada.
        DB::statement('ALTER TABLE proveedores ADD CONSTRAINT proveedores_criticidad_check CHECK (criticidad_derivada IS NOT NULL OR criticidad_declarada IS NOT NULL)');
        DB::statement('ALTER TABLE proveedores ADD CONSTRAINT proveedores_modelo_nube_check CHECK (
            (es_nube AND modelo_nube IN ('.$this->lista(self::MODELOS_NUBE).')) OR (NOT es_nube AND modelo_nube IS NULL)
        )');
        DB::statement('ALTER TABLE proveedores ADD CONSTRAINT proveedores_codigo_check CHECK (length(trim(codigo)) > 0)');

        Schema::create('proveedor_transiciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('proveedor_id')->constrained('proveedores')->cascadeOnDelete();
            $table->string('estado_anterior')->nullable();
            $table->string('estado_nuevo');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('nota')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organizacion_id', 'proveedor_id']);
        });

        $this->check('proveedor_transiciones', 'estado_nuevo', self::ESTADOS);

        Schema::create('proveedor_evaluaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('proveedor_id')->constrained('proveedores')->cascadeOnDelete();

            $table->date('fecha');
            $table->string('resultado');

            // La criticidad con la que se evaluó, congelada: es la que decidió
            // cuándo tocaba la siguiente.
            $table->string('criticidad');

            $table->text('conclusiones')->nullable();
            $table->foreignId('evaluada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organizacion_id', 'proveedor_id', 'fecha']);
        });

        $this->check('proveedor_evaluaciones', 'resultado', self::RESULTADOS);
        $this->check('proveedor_evaluaciones', 'criticidad', self::CRITICIDADES);
        // Una evaluación que no es apta sin decir por qué no sirve para nada.
        DB::statement("ALTER TABLE proveedor_evaluaciones ADD CONSTRAINT proveedor_evaluaciones_conclusiones_check CHECK (resultado = 'apto' OR length(trim(coalesce(conclusiones, ''))) > 0)");

        Schema::create('proveedor_evaluacion_clausulas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('evaluacion_id')->constrained('proveedor_evaluaciones')->cascadeOnDelete();
            $table->foreignId('clausula_id')->constrained('clausulas_contractuales')->restrictOnDelete();
            $table->string('resultado');
            $table->text('nota')->nullable();

            $table->unique(['evaluacion_id', 'clausula_id']);
            $table->index(['organizacion_id', 'evaluacion_id']);
        });

        $this->check('proveedor_evaluacion_clausulas', 'resultado', self::RESULTADOS_CLAUSULA);

        Schema::create('proveedor_certificaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('proveedor_id')->constrained('proveedores')->cascadeOnDelete();

            $table->string('tipo');
            $table->string('categoria_ens')->nullable();
            $table->string('descripcion')->nullable();
            $table->string('entidad_emisora')->nullable();
            $table->date('emitida_en')->nullable();
            $table->date('caduca_en')->nullable();

            // El certificado se guarda como evidencia, que es donde ya viven los
            // ficheros con caducidad, y así puede probar A.5.19 además.
            $table->foreignId('evidencia_id')->nullable()->constrained('evidencias')->nullOnDelete();

            $table->timestamps();

            $table->index(['organizacion_id', 'proveedor_id']);
            $table->index(['organizacion_id', 'caduca_en']);
        });

        $this->check('proveedor_certificaciones', 'tipo', self::TIPOS_CERTIFICACION);
        DB::statement('ALTER TABLE proveedor_certificaciones ADD CONSTRAINT proveedor_certificaciones_categoria_check CHECK (
            (tipo = \'ens\' AND categoria_ens IN ('.$this->lista(self::CATEGORIAS).')) OR (tipo <> \'ens\' AND categoria_ens IS NULL)
        )');
        DB::statement("ALTER TABLE proveedor_certificaciones ADD CONSTRAINT proveedor_certificaciones_descripcion_check CHECK (tipo <> 'otra' OR length(trim(coalesce(descripcion, ''))) > 0)");
        DB::statement('ALTER TABLE proveedor_certificaciones ADD CONSTRAINT proveedor_certificaciones_fechas_check CHECK (emitida_en IS NULL OR caduca_en IS NULL OR caduca_en >= emitida_en)');

        Schema::create('proveedor_tarea', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('proveedor_id')->constrained('proveedores')->cascadeOnDelete();
            $table->foreignId('tarea_id')->constrained('tareas')->cascadeOnDelete();
            $table->foreignId('vinculada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['proveedor_id', 'tarea_id']);
            $table->index(['organizacion_id', 'tarea_id']);
        });

        /*
         * El enganche que se aplazó a propósito desde el § 4.2: la columna no
         * existía porque no había tabla a la que apuntar. `nullOnDelete`: un
         * activo sobrevive a que el proveedor desaparezca.
         */
        Schema::table('activos', function (Blueprint $table): void {
            $table->foreignId('proveedor_id')->nullable()->after('ubicacion')->constrained('proveedores')->nullOnDelete();
            $table->index(['organizacion_id', 'proveedor_id']);
        });
    }

    public function down(): void
    {
        Schema::table('activos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('proveedor_id');
        });

        Schema::dropIfExists('proveedor_tarea');
        Schema::dropIfExists('proveedor_certificaciones');
        Schema::dropIfExists('proveedor_evaluacion_clausulas');
        Schema::dropIfExists('proveedor_evaluaciones');
        Schema::dropIfExists('proveedor_transiciones');
        Schema::dropIfExists('proveedores');

        DB::statement('ALTER TABLE organizaciones DROP CONSTRAINT IF EXISTS organizaciones_reevaluacion_proveedor_check');

        Schema::table('organizaciones', function (Blueprint $table): void {
            $table->dropColumn([
                'reevaluacion_proveedor_alta_meses',
                'reevaluacion_proveedor_media_meses',
                'reevaluacion_proveedor_baja_meses',
            ]);
        });
    }

    /** @param  list<string>  $valores */
    private function check(string $tabla, string $columna, array $valores): void
    {
        DB::statement("ALTER TABLE {$tabla} ADD CONSTRAINT {$tabla}_{$columna}_check CHECK ({$columna} IN ({$this->lista($valores)}))");
    }

    /** @param  list<string>  $valores */
    private function lista(array $valores): string
    {
        return implode(', ', array_map(static fn (string $valor): string => "'{$valor}'", $valores));
    }
};
