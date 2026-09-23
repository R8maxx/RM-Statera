<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Por qué se retiró un compromiso, en su propia columna.
 *
 * **No estaba, y `RetirarCompromiso` escribía el motivo en `notas`.** `notas` es un
 * campo del formulario, editable por quien lleva el registro y buscable desde la
 * tabla: retirar con motivo **borraba lo que hubiera escrito allí**, sin avisar y
 * sin forma de recuperarlo.
 *
 * Y el motivo tiene que estar en alguna parte, porque retirar es una decisión y no
 * un cambio de estado. Es la misma distinción que el producto ya hace entre `hecha`
 * y `descartada` en una tarea: descartar exige motivo porque alguien decidió que no
 * se haría, y aquí alguien decide que esto ha dejado de aplicar. El auditor
 * pregunta por lo segundo tanto como por lo primero — «dejasteis de presentar el
 * INES, ¿por qué?» es exactamente la pregunta.
 *
 * Columna aparte y no reutilizar `notas`: son dos textos con dos dueños y dos
 * momentos. Uno lo escribe quien mantiene el compromiso mientras vive; el otro, una
 * sola vez, quien lo cierra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compromisos', function (Blueprint $table): void {
            $table->text('motivo_retirada')->nullable()->after('notas');
        });
    }

    public function down(): void
    {
        Schema::table('compromisos', function (Blueprint $table): void {
            $table->dropColumn('motivo_retirada');
        });
    }
};
