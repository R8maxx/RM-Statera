<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Por qué faltó quien estaba convocado: `asistencias` gana la justificación.
 *
 * Hasta aquí la fila distinguía convocar de asistir, y ése era el dato que el
 * auditor pregunta. La pregunta siguiente es «¿y los que faltaron?», y la
 * respuesta no cabía: un certificado médico y un olvido se veían igual.
 *
 * - **`ausencia` nula significa «sin indicar»**, no «injustificada». Que nadie
 *   lo haya dicho todavía no es lo mismo que decir que no había motivo.
 * - **El `CHECK` de valores va escrito a mano**, por la regla de
 *   `.ai/rules/migraciones.md`: construido desde el enum, `migrate:fresh`
 *   aceptaría un caso nuevo sin migración y ningún test se pondría rojo.
 * - **Quien asistió no lleva ni ausencia ni motivo**, y lo impone la base: una
 *   asistencia con «justificada» dentro es un dato que se contradice solo.
 * - **El motivo sólo en la justificada**, pero **no se exige**: lo pide el
 *   `FormRequest` al guardar. En la base puede faltar porque la supresión de
 *   una persona (punto 36) vacía el motivo y conserva que la ausencia estaba
 *   justificada, que es histórico del SGSI y no dato personal.
 * - **`motivo_ausencia` es `text` porque va cifrado** con el cast `encrypted`:
 *   «baja médica» es un dato de salud, y el texto cifrado no cabe en un
 *   `varchar` corto.
 *
 * La RLS de `asistencias` ya cubre las columnas: es la fila entera la que se
 * filtra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asistencias', function (Blueprint $table): void {
            $table->string('ausencia', 20)->nullable();
            $table->text('motivo_ausencia')->nullable();
        });

        DB::statement("ALTER TABLE asistencias ADD CONSTRAINT asistencias_ausencia_check CHECK (ausencia IN ('justificada', 'injustificada'))");
        DB::statement('ALTER TABLE asistencias ADD CONSTRAINT asistencias_ausencia_solo_si_falto CHECK (asistio = false OR (ausencia IS NULL AND motivo_ausencia IS NULL))');
        DB::statement("ALTER TABLE asistencias ADD CONSTRAINT asistencias_motivo_solo_si_justificada CHECK (motivo_ausencia IS NULL OR ausencia = 'justificada')");
    }

    public function down(): void
    {
        Schema::table('asistencias', function (Blueprint $table): void {
            $table->dropColumn(['ausencia', 'motivo_ausencia']);
        });
    }
};
