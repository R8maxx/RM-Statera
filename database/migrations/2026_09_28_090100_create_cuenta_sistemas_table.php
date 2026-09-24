<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El alcance de una cuenta: los sistemas que ve.
 *
 * El § 4.19 describe al auditor externo con «acceso limitado al alcance
 * auditado», y en Statera el alcance es un sistema, que es la unidad de
 * certificación: un SGSI de ISO o un sistema del ENS.
 *
 * **Sin filas, la cuenta no tiene alcance acotado.** Es lo que tienen el
 * responsable de seguridad y el técnico, y la razón de que el auditor no pueda
 * quedarse sin filas la hace cumplir `AlcanceDeCuenta`, que exige al menos un
 * sistema para ese rol. Un permiso no lo expresa: `auditorias.ver` es de toda
 * la organización.
 *
 * Y `invitacion_tokens`, aparte de `password_reset_tokens`: una invitación dura
 * días y un restablecimiento una hora. Compartir la tabla haría que pedir una
 * contraseña nueva pisara una invitación pendiente. No lleva `organizacion_id`
 * por el mismo motivo que la de Fortify: es infraestructura de autenticación, y
 * se consulta antes de saber de qué organización es nadie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuenta_sistemas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sistema_id')->constrained('sistemas')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'sistema_id']);
            $table->index(['organizacion_id', 'user_id']);
        });

        Schema::create('invitacion_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitacion_tokens');
        Schema::dropIfExists('cuenta_sistemas');
    }
};
