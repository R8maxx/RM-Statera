<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que la organización se ha comprometido a hacer cada tanto, y la prueba de
 * cada vez que lo hizo.
 *
 * **`obligaciones` es el catálogo y `compromisos` son los asumidos.** Separarlos
 * es lo mismo que separar `requisitos` de `implantaciones`, y por el mismo motivo:
 * lo que dice el BOE es global y compartido, y lo que hace cada cliente con ello
 * es suyo. Un compromiso puede además no salir de ningún catálogo —`obligacion_id`
 * nula—, porque una organización puede comprometerse a cosas que no le exige
 * nadie, y eso es una decisión suya que la herramienta registra y no discute.
 *
 * **`titulo` y `periodicidad_meses` se copian al asumir, no se leen por join.**
 * Mismo criterio que `mediciones.objetivo` y que las instantáneas de los
 * documentos: lo que la organización asumió en 2026 no puede repintarse porque el
 * catálogo cambie la redacción en 2028. `obligacion_id` sigue ahí para saber de
 * qué salió. Y el catálogo **sugiere** una periodicidad; el compromiso es de quien
 * lo asume, que puede ser más estricto que el mínimo legal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compromisos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            // Nula es «obligación propia»: alguien se comprometió a algo que no
            // está en el catálogo. `nullOnDelete` y no `cascade`: retirar una
            // obligación del catálogo no puede llevarse por delante el histórico
            // de haberla cumplido durante tres años.
            $table->foreignId('obligacion_id')->nullable()->constrained('obligaciones')->nullOnDelete();

            /*
             * De qué sistema. La renovación de conformidad del ENS es **de un
             * sistema concreto**; el informe INES es de la organización entera.
             * Nula es lo segundo, y por eso el índice único de más abajo tiene que
             * tratar los nulos como iguales.
             */
            $table->foreignId('sistema_id')->nullable()->constrained('sistemas')->nullOnDelete();

            $table->string('codigo')->comment('OBL-2026-01');
            $table->string('titulo');
            $table->text('descripcion')->nullable();

            $table->smallInteger('periodicidad_meses');

            /*
             * Desde cuándo corre el reloj: la última vez que esto se hizo antes de
             * que existiera el registro, o el día en que la organización asume el
             * compromiso.
             *
             * **Y no una columna `proxima_fecha`**, que es lo primero que apetece
             * poner. La próxima fecha es `max(cubre_hasta)` de los cumplimientos y,
             * si no hay ninguno, esto más la periodicidad: derivarla es una
             * expresión escrita una vez, y guardarla son dos sitios que se
             * desincronizan el día que alguien borre un cumplimiento.
             *
             * NOT NULL a propósito: así un compromiso recién asumido ya tiene fecha
             * desde el primer día y no hay un caso especial que contemplar en cada
             * consulta.
             */
            $table->date('computa_desde');

            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();

            // Retirar no es borrar: un compromiso que dejó de aplicar conserva la
            // prueba de que se cumplió mientras aplicaba, que es justo lo que un
            // auditor pide del periodo anterior.
            $table->boolean('activo')->default(true);
            $table->text('notas')->nullable();

            $table->timestamps();

            $table->unique(['organizacion_id', 'codigo']);
            $table->index(['organizacion_id', 'activo']);
            $table->index(['organizacion_id', 'responsable_id']);
        });

        DB::statement('ALTER TABLE compromisos ADD CONSTRAINT compromisos_codigo_check CHECK (length(trim(codigo)) > 0)');
        DB::statement('ALTER TABLE compromisos ADD CONSTRAINT compromisos_titulo_check CHECK (length(trim(titulo)) > 0)');
        DB::statement('ALTER TABLE compromisos ADD CONSTRAINT compromisos_periodicidad_check CHECK (periodicidad_meses BETWEEN 1 AND 120)');

        /*
         * La misma obligación del catálogo no se asume dos veces para el mismo
         * sistema. Dos piezas, y las dos hacen falta:
         *
         * **`NULLS NOT DISTINCT`**, que es DDL crudo de PostgreSQL 15 en
         * adelante. Por defecto PostgreSQL considera que dos nulos son distintos,
         * así que un índice único corriente dejaría asumir tres veces «presentar
         * el informe INES» —que no cuelga de ningún sistema— y el calendario
         * pintaría tres chips para un solo compromiso. Justo la mitad de los
         * casos, y la que nadie prueba.
         *
         * **`WHERE obligacion_id IS NOT NULL`**, que es lo que impide que la
         * regla muerda donde no debe. Sin el parcial, y con los nulos tratados
         * como iguales, una organización podría tener **un solo compromiso
         * propio**: el segundo chocaría con el primero por tener los dos
         * `obligacion_id` y `sistema_id` a nulo. Y el compromiso propio es
         * justamente el caso en el que varios son legítimos — no hay fila de
         * catálogo que duplicar, que es lo único que este índice existe para
         * evitar. Lo encontró el test de coherencia con el panel al sembrar dos.
         */
        DB::statement('CREATE UNIQUE INDEX compromisos_unicos ON compromisos (organizacion_id, obligacion_id, sistema_id) NULLS NOT DISTINCT WHERE obligacion_id IS NOT NULL');

        Schema::create('compromiso_cumplimientos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('compromiso_id')->constrained('compromisos')->cascadeOnDelete();

            // Cuándo se cumplió. No es `created_at`, que es cuándo se apuntó: la
            // fecha que le importa al auditor es la primera y la de la traza es la
            // segunda, y enseñar sólo una las confunde.
            $table->date('fecha');

            /*
             * Hasta cuándo cubre este cumplimiento. **Se congela** al registrarlo,
             * con la periodicidad vigente ENTONCES.
             *
             * Es la misma familia que `documento_versiones.fecha_proxima_revision`:
             * subir la cadencia de anual a semestral en marzo no puede repintar
             * como «fuera de plazo» un cumplimiento de enero que en enero estaba
             * al día.
             */
            $table->date('cubre_hasta');

            /*
             * Con qué registro del producto se demuestra. Tres claves foráneas
             * excluyentes y **no un `morphTo`**: el morph mete nombres de clase PHP
             * dentro de la base —el motivo exacto por el que se descartó
             * `spatie/laravel-medialibrary`— y renombrar un modelo rompería filas
             * históricas en silencio. Con foráneas reales hay integridad
             * referencial de verdad.
             */
            $table->foreignId('auditoria_id')->nullable()->constrained('auditorias')->nullOnDelete();
            $table->foreignId('revision_direccion_id')->nullable()->constrained('revisiones_direccion')->nullOnDelete();
            $table->foreignId('documento_id')->nullable()->constrained('documentos')->nullOnDelete();

            /*
             * La prueba, que es otro eje y por eso queda fuera del `CHECK` de
             * exclusión: el PDF del INES presentado o el certificado de
             * conformidad conviven con el registro que los originó. Precedente
             * literal: `acciones_formativas.evidencia_id`.
             */
            $table->foreignId('evidencia_id')->nullable()->constrained('evidencias')->nullOnDelete();

            $table->text('nota')->nullable();
            $table->foreignId('registrado_por_id')->nullable()->constrained('users')->nullOnDelete();

            // Sin `updated_at`: esto es histórico y no se edita. Corregir un
            // cumplimiento mal apuntado es borrarlo y registrar el bueno, que deja
            // rastro; editarlo en el sitio no lo dejaría.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['compromiso_id', 'fecha']);
            $table->index(['organizacion_id', 'fecha']);
        });

        DB::statement('ALTER TABLE compromiso_cumplimientos ADD CONSTRAINT compromiso_cumplimientos_cobertura_check CHECK (cubre_hasta > fecha)');
        DB::statement('ALTER TABLE compromiso_cumplimientos ADD CONSTRAINT compromiso_cumplimientos_referencia_check CHECK (num_nonnulls(auditoria_id, revision_direccion_id, documento_id) <= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('compromiso_cumplimientos');
        Schema::dropIfExists('compromisos');
    }
};
