<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El documento tal y como se entrega: editable de arriba abajo.
 *
 * Hasta aquí el documento era una consulta con once huecos de texto alrededor.
 * Esto lo convierte en un documento de verdad —párrafos, tablas y cajas que se
 * editan— sin renunciar a que lo produzca el registro: `cuerpo` nace
 * materializado desde `implantaciones` y puede volver a materializarse cuando
 * quien lo mantiene lo pida.
 *
 * **Tabla aparte y no una columna en `documentos`.** Un cuerpo con noventa y
 * tres filas ronda los trescientos kilobytes, y `DocumentoRecurso::consulta()`
 * lista documentos: una columna `jsonb` de ese tamaño se cargaría en cada
 * listado de la tabla para no enseñarse nunca. Aquí se carga cuando se abre el
 * documento, que es cuando hace falta.
 *
 * **Por qué `generado` además de `cuerpo`.** `cuerpo` es lo que se entrega;
 * `generado` es lo que produjo Statera la última vez que se materializó. No está
 * para rehacer nada —recalcular vuelve a consultar el registro, que es más
 * honesto que restaurar una copia vieja— sino para poder contestar la única
 * pregunta que importa cuando un documento se puede editar entero: **«¿en qué se
 * diferencia lo que entregas de lo que yo generé?»**. Sin esa respuesta, un
 * documento editado a mano y uno generado son indistinguibles, y ahí es donde se
 * cae la confianza en la herramienta.
 *
 * `editado_en` nulo significa «nadie lo ha tocado», y de eso depende qué frase
 * imprime la portada y si las limitaciones llevan la declaración de edición
 * manual. Es una fecha y no un booleano porque «cuándo» es la primera pregunta
 * que hace un auditor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_cuerpos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            // Uno por documento: el cuerpo ES el documento, no una de sus partes.
            $table->foreignId('documento_id')->unique()->constrained('documentos')->cascadeOnDelete();

            // JSON de ProseMirror. No es HTML a propósito: en este árbol no
            // existe ningún nodo de marcado crudo, así que no hay nada que
            // sanear al guardar —lo que no está en `EsquemaCuerpo` no se puede
            // escribir, y lo que no está en él no se pinta—.
            $table->jsonb('cuerpo');
            $table->jsonb('generado');

            $table->timestampTz('generado_en');
            $table->timestampTz('editado_en')->nullable();

            $table->foreignId('editado_por_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['organizacion_id', 'documento_id']);
        });

        /*
         * Un cuerpo tiene que ser un objeto JSON con su `content`, no un escalar
         * ni una lista. `jsonb` acepta `4`, `"hola"` y `null` como documentos
         * válidos, y cualquiera de los tres reventaría el renderizador con un
         * error que no dice nada de la causa. El tipo de dato no es la
         * restricción: esto sí.
         *
         * Va con `jsonb_exists()` y no con el operador `?`, que es lo natural en
         * SQL: PDO ve el `?` de `jsonb ? 'type'` como un marcador de parámetro y
         * la sentencia se cae con «number of bound variables does not match».
         */
        foreach (['cuerpo', 'generado'] as $columna) {
            DB::statement(
                "ALTER TABLE documento_cuerpos ADD CONSTRAINT documento_cuerpos_{$columna}_check ".
                "CHECK (jsonb_typeof({$columna}) = 'object' AND jsonb_exists({$columna}, 'type'))"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_cuerpos');
    }
};
