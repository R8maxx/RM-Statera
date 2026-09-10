<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El inventario de activos, su grafo de dependencias y su pertenencia al alcance
 * de los sistemas.
 *
 * Tres decisiones que conviene leer antes de tocar el esquema:
 *
 * 1. **La valoración va en cinco columnas, no en filas de
 *    `valoracion_dimensiones`.** Aquella tabla es la entrada del motor de
 *    categorización: lleva justificación por dimensión y su cambio recalcula las
 *    implantaciones. La del activo no hace nada de eso, y además la propagación
 *    por el grafo es un `GREATEST` sobre columnas dentro de una CTE recursiva —
 *    con filas habría que pivotar dentro de la recursiva.
 *
 * 2. **`activo_sistema` es N:M.** El mismo servidor está en el alcance del SGSI
 *    de ISO y del sistema del ENS a la vez. Duplicar el activo para que cupiera
 *    en los dos sería volver a las hojas de cálculo duplicadas.
 *
 * 3. **Falta `proveedor_id`, y falta a propósito.** El módulo de proveedores
 *    (§ 4.9) todavía no existe y no se declara una clave foránea contra una
 *    tabla que no está. Se añade con ese módulo.
 *
 * 4. **`identificador` no es único.** Es el nº de serie, el ARN, el hostname o
 *    la IP: el identificador ESTABLE del activo, frente al `codigo`, que lo pone
 *    la organización. No lleva restricción de unicidad porque un número de serie
 *    y un ARN no comparten espacio de nombres, y una IP se reasigna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->string('codigo');
            $table->string('nombre');
            $table->text('descripcion')->nullable();

            // Tipología MAGERIT. Es la que usa el ENS y la que el auditor espera
            // ver, así que no se inventa una propia.
            $table->string('tipo');

            // La etiqueta operativa con la que la gente busca de verdad:
            // «Portátil», «EC2», «RDS», «Router». MAGERIT se queda para el
            // análisis de riesgos; esto es para encontrar el activo.
            $table->string('subtipo')->nullable();

            $table->string('marca_modelo')->nullable();
            $table->text('especificaciones')->nullable();

            // Un sistema operativo fuera de soporte es `op.exp.4` y no lo ve
            // nadie hasta que hay un incidente. La fecha se propone desde
            // `config/obsolescencia.php` y se puede escribir a mano.
            $table->string('sistema_operativo')->nullable();
            $table->date('fin_soporte_so')->nullable();

            $table->string('identificador')->nullable();

            // El propietario responde del activo; el custodio lo usa. Cuando un
            // portátil cambia de manos sólo cambia el custodio: el activo
            // conserva su código y la etiqueta pegada en la carcasa sigue siendo
            // válida.
            $table->foreignId('propietario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('custodio_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('departamento')->nullable();

            $table->string('ubicacion')->nullable();
            $table->date('fin_garantia')->nullable();
            $table->string('estado_ciclo_vida')->default('en_produccion');

            // La etiqueta que se le pone al dato (`mp.info.2`). NO sustituye al
            // nivel del Anexo I: la clasificación se decide, el nivel se deriva
            // de valorar el perjuicio. Conviven y contestan preguntas distintas.
            $table->string('clasificacion')->default('no_aplica');

            // Los dos con cuatro valores, y «por confirmar» distinto de «no» a
            // propósito: la ausencia de dato no es ausencia de copia, y
            // colapsarlos convierte una duda en un incumplimiento falso.
            $table->string('cifrado')->default('no_aplica');
            $table->string('copia_seguridad')->default('no_aplica');

            // Valoración PROPIA en las cinco dimensiones del Anexo I. La efectiva
            // —el máximo con la de todo lo que depende de este activo— se
            // calcula, no se almacena: guardarla sería abrir la puerta a que se
            // desincronice del grafo que la justifica.
            foreach (['c', 'i', 'd', 'a', 't'] as $dimension) {
                $table->string("valor_{$dimension}")->default('na');
            }

            $table->date('fecha_alta')->nullable();

            // Cuándo se revisó por última vez, y cuándo se imprimió su etiqueta.
            // La segunda distingue «no lleva porque no es físico» de «lleva y
            // está pendiente de pegar».
            $table->date('ultima_revision')->nullable();
            $table->timestamp('etiquetado_en')->nullable();

            // La baja del activo. `mp.si.5` del ENS exige registrar el borrado o
            // la destrucción del soporte, y es justo lo que nadie apunta.
            $table->date('fecha_baja')->nullable();
            $table->timestamp('borrado_seguro_en')->nullable();
            $table->text('nota_baja')->nullable();

            // Donde va lo que no cabe en ningún campo, y lo primero que se lee.
            $table->text('observaciones')->nullable();

            $table->timestamps();

            $table->unique(['organizacion_id', 'codigo']);
            $table->index(['organizacion_id', 'tipo']);
            $table->index(['organizacion_id', 'estado_ciclo_vida']);
            $table->index(['organizacion_id', 'identificador']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE activos ADD CONSTRAINT activos_tipo_check CHECK (tipo IN (
                'servicios', 'datos', 'software', 'hardware', 'comunicaciones',
                'soportes', 'equipamiento_auxiliar', 'instalaciones', 'personal'
            ))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE activos ADD CONSTRAINT activos_estado_ciclo_vida_check CHECK (estado_ciclo_vida IN (
                'planificado', 'en_stock', 'en_produccion', 'en_mantenimiento',
                'en_reparacion', 'prestado', 'retirado', 'dado_de_baja'
            ))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE activos ADD CONSTRAINT activos_clasificacion_check CHECK (clasificacion IN (
                'publico', 'uso_interno', 'confidencial', 'restringido', 'no_aplica'
            ))
        SQL);

        foreach (['cifrado', 'copia_seguridad'] as $control) {
            DB::statement(
                "ALTER TABLE activos ADD CONSTRAINT activos_{$control}_check ".
                "CHECK ({$control} IN ('si', 'no', 'por_confirmar', 'no_aplica'))"
            );
        }

        foreach (['c', 'i', 'd', 'a', 't'] as $dimension) {
            DB::statement(
                "ALTER TABLE activos ADD CONSTRAINT activos_valor_{$dimension}_check ".
                "CHECK (valor_{$dimension} IN ('na', 'bajo', 'medio', 'alto'))"
            );
        }

        /*
         * El grafo dirigido: `activo_id` DEPENDE DE `depende_de_id`. Un servicio
         * depende de una aplicación, que depende de una instancia, que depende de
         * una base de datos.
         *
         * La dirección importa y es fácil de invertir al leerla: la valoración
         * sube por `depende_de_id` —si el servicio es alto, la base de datos que
         * lo sostiene hereda alto—, nunca al revés.
         */
        Schema::create('activo_dependencias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('activo_id')->constrained('activos')->cascadeOnDelete();
            $table->foreignId('depende_de_id')->constrained('activos')->cascadeOnDelete();

            // Qué clase de dependencia es: «se ejecuta sobre», «almacena en»,
            // «se comunica por». Sin ella el grafo dice que hay vínculo pero no
            // cuál, y el BIA necesita saberlo.
            $table->text('nota')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->unique(['activo_id', 'depende_de_id']);
            $table->index(['organizacion_id', 'depende_de_id']);
        });

        // Cubre sólo el ciclo trivial de longitud 1. Los ciclos largos los
        // rechaza RegistrarDependencia antes de escribir, porque detectarlos
        // exige recorrer el grafo y eso no cabe en un CHECK.
        DB::statement('ALTER TABLE activo_dependencias ADD CONSTRAINT activo_dependencias_sin_bucle_check CHECK (activo_id <> depende_de_id)');

        Schema::create('activo_sistema', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('activo_id')->constrained('activos')->cascadeOnDelete();
            $table->foreignId('sistema_id')->constrained('sistemas')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['activo_id', 'sistema_id']);
            $table->index(['organizacion_id', 'sistema_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activo_sistema');
        Schema::dropIfExists('activo_dependencias');
        Schema::dropIfExists('activos');
    }
};
