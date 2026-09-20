<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Personas: § 4.8, la cláusula 5.3 de ISO y `mp.per.2/3/4` del ENS.
 *
 * Es **lo que muerde hoy**: en categoría básica ya son exigibles los deberes y
 * obligaciones (`mp.per.2`), la concienciación (`mp.per.3`) y la formación
 * (`mp.per.4`), y hasta aquí no había dónde registrarlas. Y con él se cierra el
 * 5.3 —roles y autoridades—, que era uno de los cinco huecos con requisito en el
 * catálogo, implantación esperando y ningún sitio donde escribirse.
 *
 * ### `personas` NO es `users`, y ésa es la decisión que da forma al módulo
 *
 * `users` son **cuentas de Statera**: quien entra, mira y cierra tareas. `personas`
 * es el **registro de plantilla**: quien firma un acuerdo de confidencialidad,
 * asiste a la formación y puede ser designado responsable de seguridad, tenga o no
 * cuenta —y la mayoría no la tiene—.
 *
 * Por eso **los responsables de activos, tareas, evidencias, riesgos y todo lo
 * demás siguen apuntando a `users`** y no se migran: asignar una tarea a quien no
 * puede entrar a cerrarla no sirve de nada. El puente entre los dos mundos es
 * `personas.user_id`, nullable y único, y sirve para cruzarlos —«¿quién de la
 * plantilla tiene cuenta?»— sin fundirlos.
 *
 * ### Lo que se deriva y no se guarda
 *
 * **`activa` no es columna: es `fecha_baja IS NULL`.** Mismo criterio que `vigente`
 * en el análisis del contexto —donde es `estado = 'aprobado'`— y que el ámbito y el
 * signo de una cuestión del DAFO: guardarlo sería el mismo dato en dos sitios que
 * pueden discrepar, y reincorporar a alguien sería cambiar un campo y no acordarse
 * de dos.
 *
 * Lo mismo con una designación: **vigente es `hasta IS NULL`**.
 *
 * Los `CHECK` se construyen desde constantes **de esta migración** y no desde los
 * enums, que es el patrón de `tareas`, `auditorias`, `no_conformidades`,
 * `objetivos_seguridad` y `mejoras`.
 */
return new class extends Migration
{
    /** Los cinco roles del Anexo II y de la CCN-STIC 801. */
    private const ROLES = [
        'responsable_informacion',
        'responsable_servicio',
        'responsable_seguridad',
        'responsable_sistema',
        'administrador_seguridad',
    ];

    /**
     * Los roles de los que hay **uno solo** por sistema a la vez.
     *
     * Responsable de la información y responsable del servicio pueden ser varios
     * —uno por cada información tratada y por cada servicio prestado—, así que
     * exigirles unicidad sería inventarse una restricción que la guía no pone.
     */
    private const ROLES_UNICOS = "'responsable_seguridad', 'responsable_sistema', 'administrador_seguridad'";

    /** @var list<string> */
    private const TIPOS_FORMACION = ['formacion', 'concienciacion'];

    /** @var list<string> */
    private const TIPOS_PASO = ['alta', 'baja'];

    public function up(): void
    {
        $roles = $this->lista(self::ROLES);
        $rolesUnicos = self::ROLES_UNICOS;
        $tiposFormacion = $this->lista(self::TIPOS_FORMACION);
        $tiposPaso = $this->lista(self::TIPOS_PASO);

        Schema::create('personas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            $table->string('codigo');
            $table->string('nombre');
            $table->string('puesto')->nullable();
            $table->string('email')->nullable();

            /*
             * El puente con la cuenta de Statera, si la tiene.
             *
             * **Único y nullable**: una cuenta pertenece como mucho a una persona, y
             * en PostgreSQL los nulos son distintos entre sí, así que deja pasar
             * todas las personas sin cuenta que haga falta — que son la mayoría.
             *
             * `nullOnDelete` y no cascada: borrar una cuenta no puede llevarse por
             * delante el registro de quién firmó el acuerdo de confidencialidad.
             */
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();

            $table->date('fecha_alta');
            $table->date('fecha_baja')->nullable();

            $table->text('notas')->nullable();

            $table->timestamps();

            $table->unique(['organizacion_id', 'codigo']);
            $table->index(['organizacion_id', 'fecha_baja']);
        });

        DB::statement('ALTER TABLE personas ADD CONSTRAINT personas_codigo_check CHECK (length(trim(codigo)) > 0)');
        DB::statement('ALTER TABLE personas ADD CONSTRAINT personas_nombre_check CHECK (length(trim(nombre)) > 0)');

        // Irse antes de entrar no es una baja, es un dedazo.
        DB::statement('ALTER TABLE personas ADD CONSTRAINT personas_baja_coherente_check CHECK (fecha_baja IS NULL OR fecha_baja >= fecha_alta)');

        /*
         * Las designaciones de rol ENS: la cláusula 5.3 y la CCN-STIC 801.
         *
         * **Con vigencia, y no se borran.** «¿Desde cuándo es responsable de
         * seguridad?» es literalmente la pregunta del auditor (invariante 7), y una
         * tabla que sólo guardara el nombramiento de hoy no la contesta. Vigente es
         * `hasta IS NULL`.
         *
         * **Por sistema y no por organización**, que es donde la incompatibilidad
         * significa algo: una persona puede ser responsable de seguridad de un
         * sistema y responsable del sistema de otro sin incompatibilidad ninguna.
         *
         * La incompatibilidad —seguridad y sistema en la misma persona— **no cabe
         * en un `CHECK`**: es una condición entre filas y un `CHECK` sólo ve una.
         * Vive en `DesignarRol`, que es el precedente exacto de
         * `RegistrarDependencia` con los ciclos del grafo de activos.
         */
        Schema::create('designaciones_rol', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();

            // `restrictOnDelete`: borrar un sistema con roles designados dejaría el
            // nombramiento apuntando al vacío, y un nombramiento es un hecho.
            $table->foreignId('sistema_id')->constrained('sistemas')->restrictOnDelete();

            $table->string('rol');
            $table->date('desde');
            $table->date('hasta')->nullable();

            $table->foreignId('designada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('nota')->nullable();

            $table->timestamps();

            $table->index(['organizacion_id', 'sistema_id', 'rol']);
            $table->index(['organizacion_id', 'persona_id']);
        });

        DB::statement("ALTER TABLE designaciones_rol ADD CONSTRAINT designaciones_rol_rol_check CHECK (rol IN ({$roles}))");
        DB::statement('ALTER TABLE designaciones_rol ADD CONSTRAINT designaciones_rol_vigencia_check CHECK (hasta IS NULL OR hasta >= desde)');

        /*
         * Un solo titular vigente por sistema **para los tres roles que son
         * singulares**. Índice único parcial, como el borrador de un documento y el
         * análisis del contexto vigente. Ver la constante: información y servicio
         * pueden ser varios.
         */
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX designaciones_rol_titular_unico
                ON designaciones_rol (sistema_id, rol)
                WHERE hasta IS NULL AND rol IN ({$rolesUnicos})
        SQL);

        /*
         * La formación y la concienciación: `mp.per.3` y `mp.per.4`.
         *
         * **La hoja de firmas apunta a `evidencias`** con una foránea simple y no
         * con una pivote: el fichero escaneado es exactamente lo que ese repositorio
         * ya sabe guardar —con su hash, su caducidad y su disco S3— y montar un
         * segundo almacén para esto sería duplicar la parte cara del § 4.6.
         */
        Schema::create('acciones_formativas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            $table->string('codigo');
            $table->string('titulo');
            $table->string('tipo')->default('formacion');
            $table->date('fecha');
            $table->decimal('duracion_horas', 5, 2)->nullable();
            $table->text('contenido')->nullable();

            $table->foreignId('evidencia_id')->nullable()->constrained('evidencias')->nullOnDelete();

            $table->timestamps();

            $table->unique(['organizacion_id', 'codigo']);
            $table->index(['organizacion_id', 'fecha']);
        });

        DB::statement("ALTER TABLE acciones_formativas ADD CONSTRAINT acciones_formativas_tipo_check CHECK (tipo IN ({$tiposFormacion}))");
        DB::statement('ALTER TABLE acciones_formativas ADD CONSTRAINT acciones_formativas_codigo_check CHECK (length(trim(codigo)) > 0)');
        DB::statement('ALTER TABLE acciones_formativas ADD CONSTRAINT acciones_formativas_titulo_check CHECK (length(trim(titulo)) > 0)');
        DB::statement('ALTER TABLE acciones_formativas ADD CONSTRAINT acciones_formativas_duracion_check CHECK (duracion_horas IS NULL OR duracion_horas > 0)');

        /*
         * El registro de asistencia, que es lo que `mp.per.4` pide poder enseñar.
         *
         * **`asistio` es una columna y no la ausencia de fila**: convocar a alguien
         * que no fue es un hecho distinto de no haberlo convocado, y el segundo es
         * el que un auditor pregunta. Sin la columna, las dos cosas se verían igual.
         */
        Schema::create('asistencias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('accion_formativa_id')->constrained('acciones_formativas')->cascadeOnDelete();
            $table->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();

            $table->boolean('asistio')->default(true);
            $table->timestamp('registrada_en')->useCurrent();

            $table->unique(['accion_formativa_id', 'persona_id']);
            $table->index(['organizacion_id', 'persona_id']);
        });

        /*
         * Los acuerdos de confidencialidad: `mp.per.2`.
         *
         * **Varios por persona a propósito**: un acuerdo se renueva, y el anterior
         * sigue siendo la prueba de qué firmó esa persona en 2024. Sin unicidad.
         */
        Schema::create('acuerdos_confidencialidad', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();

            $table->date('fecha_firma');
            // Nula es «sin vencimiento», que es lo normal en un acuerdo de
            // confidencialidad: no es lo mismo que «no lo hemos mirado».
            $table->date('vigente_hasta')->nullable();
            $table->text('nota')->nullable();

            $table->foreignId('evidencia_id')->nullable()->constrained('evidencias')->nullOnDelete();

            $table->timestamps();

            $table->index(['organizacion_id', 'persona_id']);
        });

        DB::statement('ALTER TABLE acuerdos_confidencialidad ADD CONSTRAINT acuerdos_vigencia_check CHECK (vigente_hasta IS NULL OR vigente_hasta >= fecha_firma)');

        /*
         * La checklist de alta y de baja, con el patrón de `subtareas`.
         *
         * El orden lo decide quien redacta la lista —los pasos de un alta son un
         * procedimiento y no se ordenan solos por fecha ni por título— y la lista se
         * guarda entera en una sola ruta.
         */
        Schema::create('pasos_persona', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();

            $table->string('tipo');
            $table->string('titulo');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestampTz('hecho_en')->nullable();

            $table->timestamps();

            $table->index(['persona_id', 'tipo', 'orden']);
        });

        DB::statement("ALTER TABLE pasos_persona ADD CONSTRAINT pasos_persona_tipo_check CHECK (tipo IN ({$tiposPaso}))");
        DB::statement('ALTER TABLE pasos_persona ADD CONSTRAINT pasos_persona_titulo_check CHECK (length(trim(titulo)) > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('pasos_persona');
        Schema::dropIfExists('acuerdos_confidencialidad');
        Schema::dropIfExists('asistencias');
        Schema::dropIfExists('acciones_formativas');
        Schema::dropIfExists('designaciones_rol');
        Schema::dropIfExists('personas');
    }

    /**
     * @param  list<string>  $valores
     */
    private function lista(array $valores): string
    {
        return implode(', ', array_map(static fn (string $valor): string => "'".$valor."'", $valores));
    }
};
