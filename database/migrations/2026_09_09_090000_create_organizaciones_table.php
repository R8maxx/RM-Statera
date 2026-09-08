<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La raíz del tenant.
 *
 * Es la única tabla de datos propios que NO lleva `organizacion_id`, porque es
 * la organización. Todo lo demás cuelga de aquí, desde la primera migración y
 * aunque durante meses sólo haya una fila (invariante 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizaciones', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('cif')->nullable()->unique();
            $table->string('sector')->nullable();

            // Determinan si el ENS le aplica por obligación legal o por contrato:
            // un proveedor del sector público lo hereda de su cliente.
            $table->boolean('sujeto_obligado_ens')->default(false);
            $table->boolean('proveedor_sector_publico')->default(false);

            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            // Nullable a propósito: un usuario sin organización no ve nada, que
            // es el comportamiento correcto y no un caso a arreglar.
            $table->foreignId('organizacion_id')
                ->nullable()
                ->after('id')
                ->constrained('organizaciones')
                ->nullOnDelete();
        });

        DB::statement('ALTER TABLE organizaciones ADD CONSTRAINT organizaciones_nombre_no_vacio_check CHECK (length(trim(nombre)) > 0)');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('organizacion_id');
        });

        Schema::dropIfExists('organizaciones');
    }
};
