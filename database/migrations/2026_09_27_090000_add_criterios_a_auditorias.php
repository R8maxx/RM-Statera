<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que la cláusula 9.2.2 pide definir de cada auditoría y la tabla no tenía.
 *
 * El programa de auditoría tiene que fijar, para cada una, **los criterios y el
 * alcance**, y el informe tiene que poder decir **cómo se hizo y quién la hizo**.
 * El alcance estaba desde la primera migración; los criterios, el método y el
 * equipo no, y el informe de auditoría interna (§ 4.18) los imprime.
 *
 * - **`criterios`**: contra qué se audita — la norma, el Anexo II, la política
 *   propia. Sin ellos un «no conforme» no dice con respecto a qué.
 * - **`metodo`**: entrevistas, revisión documental, muestreo y con qué criterio
 *   de muestra. Es lo que da sentido a `fuera_de_muestra`.
 * - **`equipo`**: el resto de personas que auditaron. `auditor` se queda como
 *   quien firma y responde; el equipo es texto libre, como `auditor`, porque el
 *   auditor interno no tiene por qué ser usuario de Statera.
 *
 * **El trigger de inmutabilidad no se toca, y no hace falta.** `auditoria_inmutable()`
 * compara el registro entero con las columnas del cierre neutralizadas —no
 * enumera las editables—, así que tres columnas nuevas quedan blindadas desde el
 * cierre sin cambiarle una línea. Es exactamente para lo que se escribió así; lo
 * comprueba `InformeAuditoriaTest`, no esta cabecera.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditorias', function (Blueprint $table): void {
            $table->text('criterios')->nullable()->after('alcance');
            $table->text('metodo')->nullable()->after('criterios');
            $table->text('equipo')->nullable()->after('auditor');
        });
    }

    public function down(): void
    {
        Schema::table('auditorias', function (Blueprint $table): void {
            $table->dropColumn(['criterios', 'metodo', 'equipo']);
        });
    }
};
