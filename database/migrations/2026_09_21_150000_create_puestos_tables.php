<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Puestos y asignaciones: la estructura de la organización.
 *
 * Hasta aquí `personas.puesto` era **texto libre**, así que «Analista»,
 * «analista» y «Analista de sistemas» eran tres puestos distintos para cualquier
 * recuento, no se podía decir de quién depende quién, y no había dónde escribir
 * la caracterización que pide `mp.per.1`.
 *
 * ### La jerarquía vive en el PUESTO y no en la persona
 *
 * `puestos.reporta_a_id`. El organigrama de personas sale de cruzarlo con quién
 * ocupa cada puesto, así que es **un solo árbol con dos lecturas** y no dos
 * árboles que puedan discrepar. Y lo que es más práctico: que alguien entre, se
 * vaya o cambie de sitio **no toca el organigrama**, que es lo que hace que un
 * organigrama de personas se quede desactualizado a las dos semanas.
 *
 * ### La asignación lleva vigencia y no se borra
 *
 * Es el patrón literal de `designaciones_rol`, y por el mismo motivo: «¿desde
 * cuándo ocupa ese puesto?» es la pregunta del auditor (invariante 7), y una
 * columna `puesto_id` en `personas` sólo sabe contestar por el presente.
 * Vigente es `hasta IS NULL`.
 *
 * El índice único parcial es **sobre `persona_id` a secas**: una persona ocupa
 * como mucho un puesto a la vez, que es lo que la columna de texto ya decía. Al
 * revés no: varias personas ocupan «Técnico de sistemas» al mismo tiempo, así
 * que `puesto_id` no lleva unicidad ninguna.
 *
 * ### El ciclo no cabe en un `CHECK`
 *
 * `reporta_a_id` forma un grafo, y **contra un grafo con un ciclo una CTE
 * recursiva no devuelve un resultado raro: no termina**. El `CHECK` de aquí abajo
 * sólo tapa el bucle de un salto —un puesto que se reporta a sí mismo—; uno de
 * tres se cuela igual, porque un `CHECK` sólo ve una fila. La comprobación de
 * verdad vive en `AsignarSuperior`, que es el precedente exacto de
 * `RegistrarDependencia` con los ciclos del grafo de activos, y está en el
 * dominio y no en el `FormRequest` porque vale igual para un importador.
 *
 * Los `CHECK` se construyen desde constantes de esta migración y no desde los
 * enums, que es el patrón del resto del repositorio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('puestos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            $table->string('codigo');
            $table->string('titulo');

            /*
             * De quién depende. **Nullable y `nullOnDelete`**: la raíz del
             * organigrama no depende de nadie, y borrar un puesto intermedio no
             * puede llevarse por delante la rama que colgaba de él — se queda
             * colgando de la raíz, que es visible y se arregla, en vez de
             * desaparecer.
             */
            $table->foreignId('reporta_a_id')->nullable()->constrained('puestos')->nullOnDelete();

            /*
             * Las tres mitades de la ficha de puesto, y las tres son texto:
             * `mp.per.1` pide «caracterización», no un formulario con campos
             * contados. `competencias` es la que el auditor mira.
             */
            $table->text('mision')->nullable();
            $table->text('funciones')->nullable();
            $table->text('competencias')->nullable();

            $table->timestamps();

            $table->unique(['organizacion_id', 'codigo']);
            $table->index(['organizacion_id', 'reporta_a_id']);
        });

        DB::statement('ALTER TABLE puestos ADD CONSTRAINT puestos_codigo_check CHECK (length(trim(codigo)) > 0)');
        DB::statement('ALTER TABLE puestos ADD CONSTRAINT puestos_titulo_check CHECK (length(trim(titulo)) > 0)');

        // Tapa el bucle de un salto y nada más: los de dos o más son una
        // condición entre filas y los rechaza `AsignarSuperior`.
        DB::statement('ALTER TABLE puestos ADD CONSTRAINT puestos_reporte_propio_check CHECK (reporta_a_id IS DISTINCT FROM id)');

        Schema::create('asignaciones_puesto', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            $table->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();

            /*
             * `restrictOnDelete` como `designaciones_rol.sistema_id`: borrar un
             * puesto que alguien ocupó no puede borrar el registro de que lo
             * ocupó. Se vacía primero, que es una decisión de quien lo hace.
             */
            $table->foreignId('puesto_id')->constrained('puestos')->restrictOnDelete();

            $table->date('desde');
            $table->date('hasta')->nullable();

            $table->foreignId('asignada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('nota')->nullable();

            $table->timestamps();

            $table->index(['organizacion_id', 'persona_id']);
            $table->index(['organizacion_id', 'puesto_id']);
        });

        DB::statement('ALTER TABLE asignaciones_puesto ADD CONSTRAINT asignaciones_puesto_vigencia_check CHECK (hasta IS NULL OR hasta >= desde)');

        /*
         * Un puesto vigente por persona. Parcial, como
         * `designaciones_rol_titular_unico`: lo cerrado no estorba, y en
         * PostgreSQL el índice sólo cubre las filas que cumplen el `WHERE`.
         */
        DB::statement('CREATE UNIQUE INDEX asignaciones_puesto_vigente_unico ON asignaciones_puesto (persona_id) WHERE hasta IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('asignaciones_puesto');
        Schema::dropIfExists('puestos');
    }
};
