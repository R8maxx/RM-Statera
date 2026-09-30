<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una evidencia renovada apunta a la que la sustituye.
 *
 * Renovar no es editar: el fichero de una evidencia no se reemplaza (Object
 * Lock), así que la renovación es **otra evidencia** con los mismos vínculos. La
 * anterior se queda —probó lo que probó durante su periodo, y el auditor puede
 * preguntar por él—, pero deja de contar como caducada o por caducar: sin esta
 * columna, renovar a tiempo seguiría encendiendo el rojo del panel el día que
 * venciera la vieja.
 *
 * **Única**: una evidencia renueva a una sola, y la cadena se lee hacia atrás
 * sin ambigüedad.
 *
 * **`ON DELETE SET NULL`**: borrar la renovación devuelve la anterior a su
 * estado real, que es exactamente lo que vuelve a ser cierto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evidencias', function (Blueprint $table): void {
            $table->foreignId('renovada_por_id')->nullable()->after('responsable_id')
                ->unique()->constrained('evidencias')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('evidencias', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('renovada_por_id');
        });
    }
};
