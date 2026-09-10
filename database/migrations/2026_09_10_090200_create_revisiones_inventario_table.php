<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El registro de que el inventario se revisa, y de qué salió de cada revisión.
 *
 * No es burocracia: `A.5.9` de ISO y `op.exp.1` del ENS no piden un inventario,
 * piden un inventario **mantenido**, y la diferencia entre las dos cosas es
 * exactamente esta tabla. Un listado impecable sin constancia de revisión es un
 * listado que nadie sabe de cuándo es.
 *
 * `altas` y `bajas` se guardan como los contó quien revisó, y no se calculan
 * desde la traza de auditoría: son lo que esa persona firmó haber encontrado ese
 * día. Si más tarde alguien da de alta un activo con fecha anterior, la cifra
 * revisada no cambia — y no debe cambiar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revisiones_inventario', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->date('fecha');
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();

            // Qué se revisó: «parque de puestos», «infraestructura AWS». Una
            // revisión parcial es legítima; una que no dice su alcance, no.
            $table->string('alcance');

            $table->unsignedInteger('altas')->default(0);
            $table->unsignedInteger('bajas')->default(0);

            // Lo que se encontró mal y lo que se acordó hacer. Es la parte que
            // el auditor lee, y la que demuestra que la revisión sirvió para
            // algo más que para poner una fecha.
            $table->text('desviaciones')->nullable();
            $table->text('acciones')->nullable();

            $table->timestamps();

            $table->index(['organizacion_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revisiones_inventario');
    }
};
