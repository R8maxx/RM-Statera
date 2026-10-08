<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Si había otra persona de Administración al pedir un rescate (punto 52).
 *
 * Se fija al pedir y no se vuelve a mirar al ejecutar. Lo encontró la revisión
 * de seguridad: si se miraba al ejecutar, quien pedía podía bajar de perfil o
 * retirar a la otra persona, quedarse sola y ejecutar su propia solicitud «sin
 * segunda persona». Las solicitudes que ya existían se quedan exigiéndola.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_plataforma', function (Blueprint $table): void {
            $table->boolean('requiere_segunda_persona')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_plataforma', function (Blueprint $table): void {
            $table->dropColumn('requiere_segunda_persona');
        });
    }
};
