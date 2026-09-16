<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Auditorías: § 4.12 de la especificación y primera pieza de la fase 3.
 *
 * Es la cláusula 9.2 de ISO —auditoría interna— más el seguimiento de las
 * externas y de la autoevaluación del ENS, que es el flujo de conformidad de
 * categoría básica (§ 4.17). Tres tablas, y cuatro decisiones que se apartan de
 * lo que dibuja § 2.2:
 *
 * 1. **`sistema_id` es obligatorio y `marco_id` no existe.** § 2.2 dibuja lo
 *    contrario, pero ella misma define `sistemas` como «la unidad de alcance y
 *    de certificación»: el SGSI **es** un sistema, y una auditoría de
 *    certificación ISO es la auditoría de ese sistema. Sin sistema no hay
 *    checklist, que es el corazón del módulo — el mismo argumento que ya se
 *    escribió para el plan de adecuación. Y con el sistema puesto, `marco_id`
 *    sobra: `sistemas.marco_id` ya es `NOT NULL`, así que duplicarlo es el mismo
 *    dato en dos sitios que pueden desincronizarse, y obligaría a escribir la
 *    validación cruzada que los documentos necesitan.
 *
 * 2. **`auditoria_puntos` no está en § 2.2**, y hace falta por tres motivos. El
 *    denominador: «3 hallazgos» no dice nada y «3 hallazgos sobre 52 medidas
 *    revisadas» sí. La distinción entre **no revisado y conforme**, que es
 *    literalmente el argumento de `EstadoControl::PorConfirmar` — sin la tabla,
 *    la ausencia de hallazgo se lee como conformidad y una auditoría por muestreo
 *    miente. Y que sin ella, «checklists generadas desde el catálogo» —la mitad
 *    de § 4.12— no está implementado.
 *
 * 3. **Un punto no se puede marcar `no_aplica`.** La checklist se precarga desde
 *    lo aplicable, así que todo punto lo es por construcción: un auditor
 *    marcando «no aplica» estaría contradiciendo el motor de categorización
 *    desde un desplegable, y eso es lo que prohíbe el invariante 4. Lo que sí
 *    necesita expresar es **`fuera_de_muestra`**, que es otra cosa y se llama
 *    así.
 *
 * 4. **`hallazgos` cuelga del punto, no de la pareja (auditoría, requisito).**
 *    Con la pareja, nada impide un punto `conforme` con una `nc_mayor` encima del
 *    mismo requisito: el mismo hecho registrado dos veces y contradiciéndose.
 *    Colgándolo del punto, la contradicción deja de ser expresable. Es el
 *    precedente de las salvaguardas, que apuntan a `implantaciones` y no a
 *    `requisitos`.
 *
 * Y una que sí sigue la letra al revés de como la dibuja: **`requisito_id` del
 * hallazgo es nullable**. Una auditoría ISO produce hallazgos que no cuelgan de
 * ningún control —«el programa de auditoría interna no está definido», «la
 * dirección no ha revisado el SGSI»—, y con la columna obligatoria acabarían
 * colgados de un requisito arbitrario, que es el vicio que `OrigenTarea::Propia`
 * existe para evitar.
 *
 * Los `CHECK` se construyen desde constantes **de esta migración** y no desde los
 * enums, que es el patrón de `tareas`. El de `documentos` hace lo contrario y
 * está anotado como trampa: enumerando desde el enum, `migrate:fresh` incluye los
 * valores nuevos aunque falte la migración que los añade, así que ningún test se
 * pone rojo si se olvida.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TIPOS = ['interna', 'externa', 'autoevaluacion'];

    /** @var list<string> */
    private const ESTADOS = ['planificada', 'en_curso', 'cerrada'];

    /** @var list<string> */
    private const RESULTADOS = ['pendiente', 'conforme', 'no_conforme', 'observacion', 'fuera_de_muestra'];

    /** @var list<string> */
    private const TIPOS_HALLAZGO = ['nc_mayor', 'nc_menor', 'observacion', 'oportunidad_mejora'];

    public function up(): void
    {
        $tipos = $this->lista(self::TIPOS);
        $estados = $this->lista(self::ESTADOS);
        $resultados = $this->lista(self::RESULTADOS);
        $tiposHallazgo = $this->lista(self::TIPOS_HALLAZGO);

        Schema::create('auditorias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            // Obligatorio: ver el punto 1 de la cabecera.
            $table->foreignId('sistema_id')->constrained('sistemas')->cascadeOnDelete();

            $table->string('codigo');
            $table->string('tipo');
            $table->string('estado')->default('planificada');

            $table->text('alcance')->nullable();
            $table->date('fecha');

            /*
             * Texto libre y no una clave a `users`: el auditor de una externa no
             * tiene cuenta en Statera, y el de una interna puede no tenerla
             * tampoco. Forzar un usuario dejaría fuera el caso normal.
             */
            $table->string('auditor')->nullable();
            $table->string('entidad_certificadora')->nullable();

            $table->text('resultado')->nullable();
            $table->text('conclusiones')->nullable();

            // Cuándo se cerró, que es el hecho que se le enseña al auditor
            // siguiente, y no cuándo se tocó por última vez.
            $table->date('fecha_cierre')->nullable();
            $table->foreignId('cerrada_por_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Único dentro de la organización, no del mundo: dos clientes pueden
            // llamar igual a su auditoría de 2026 y es lo normal.
            $table->unique(['organizacion_id', 'codigo']);
            $table->index(['organizacion_id', 'estado']);
            $table->index(['organizacion_id', 'fecha']);
        });

        DB::statement("ALTER TABLE auditorias ADD CONSTRAINT auditorias_tipo_check CHECK (tipo IN ({$tipos}))");
        DB::statement("ALTER TABLE auditorias ADD CONSTRAINT auditorias_estado_check CHECK (estado IN ({$estados}))");
        DB::statement('ALTER TABLE auditorias ADD CONSTRAINT auditorias_codigo_check CHECK (length(trim(codigo)) > 0)');

        // Cerrada sin fecha de cierre no se puede fechar después sin
        // inventársela, y abierta con fecha de cierre es una contradicción.
        DB::statement("ALTER TABLE auditorias ADD CONSTRAINT auditorias_cierre_coherente_check CHECK ((estado = 'cerrada') = (fecha_cierre IS NOT NULL))");

        // La entidad certificadora es de las externas: una auditoría interna con
        // entidad acreditada detrás es un dato que contradice a su propio tipo.
        DB::statement("ALTER TABLE auditorias ADD CONSTRAINT auditorias_entidad_check CHECK (entidad_certificadora IS NULL OR tipo = 'externa')");

        Schema::create('auditoria_puntos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('auditoria_id')->constrained('auditorias')->cascadeOnDelete();

            /*
             * Apunta a la implantación y no al requisito: es la diferencia entre
             * «el ENS pide cifrado» y «lo tenemos puesto en este sistema», y es lo
             * que se audita. El requisito se llega por ella.
             */
            $table->foreignId('implantacion_id')->constrained('implantaciones')->cascadeOnDelete();

            $table->string('resultado')->default('pendiente');
            $table->text('nota')->nullable();

            /*
             * Congelados al cerrar. Sin esto, revalorar el sistema en octubre
             * cambiaría bajo los pies el denominador de la auditoría de marzo: el
             * motor marca medidas como no aplicables y la auditoría pasaría a
             * apuntar a implantaciones que ya no se exigen. Es el mismo motivo por
             * el que `riesgo_valoraciones` congela su escala y sus salvaguardas.
             *
             * Nulos mientras la auditoría está abierta: ahí lo que vale es lo que
             * diga el registro hoy.
             */
            $table->string('exigencia_congelada')->nullable();
            $table->string('estado_congelado')->nullable();

            $table->timestamps();

            // Una medida se revisa una vez por auditoría.
            $table->unique(['auditoria_id', 'implantacion_id']);
            $table->index(['organizacion_id', 'auditoria_id']);
        });

        DB::statement("ALTER TABLE auditoria_puntos ADD CONSTRAINT auditoria_puntos_resultado_check CHECK (resultado IN ({$resultados}))");

        Schema::create('hallazgos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('auditoria_id')->constrained('auditorias')->cascadeOnDelete();

            /*
             * Nulo cuando el hallazgo no cuelga de ninguna medida —«el programa de
             * auditoría no está definido»—. Cuando cuelga, va por el punto de la
             * checklist y no por el requisito, para que un punto «conforme» no
             * pueda tener una no conformidad encima.
             */
            $table->foreignId('auditoria_punto_id')->nullable()->constrained('auditoria_puntos')->cascadeOnDelete();

            $table->string('tipo');
            $table->text('descripcion');

            $table->timestamps();

            $table->index(['organizacion_id', 'auditoria_id']);
            $table->index(['organizacion_id', 'tipo']);
        });

        DB::statement("ALTER TABLE hallazgos ADD CONSTRAINT hallazgos_tipo_check CHECK (tipo IN ({$tiposHallazgo}))");
        DB::statement('ALTER TABLE hallazgos ADD CONSTRAINT hallazgos_descripcion_check CHECK (length(trim(descripcion)) > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hallazgos');
        Schema::dropIfExists('auditoria_puntos');
        Schema::dropIfExists('auditorias');
    }

    /**
     * @param  list<string>  $valores
     */
    private function lista(array $valores): string
    {
        return implode(', ', array_map(static fn (string $valor): string => "'".$valor."'", $valores));
    }
};
