<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El repositorio de pruebas, y la razón de ser del producto.
 *
 * Invariante 6: **evidencia ↔ requisito es N:M**. Una captura del panel del IdP
 * prueba `A.8.5` de ISO y `op.acc.5`, `op.acc.6` y `mp.info.2` del ENS a la vez.
 * Hoy eso son dos hojas de cálculo que nadie sincroniza, y por eso el mismo
 * trabajo se hace dos veces o un marco se queda atrás sin que nadie lo note.
 *
 * Sobre el fichero: no se guarda en la base. Se guarda en el disco `evidencias`
 * —bucket privado con versionado y Object Lock— y aquí queda su ruta y su
 * **SHA-256**, que es lo que permite demostrarle a un auditor que el fichero que
 * se le enseña es el que se obtuvo aquel día.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidencias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();

            $table->string('titulo');
            $table->string('tipo');
            $table->text('descripcion')->nullable();

            // Fichero almacenado. El disco se guarda con la fila porque puede
            // cambiar entre entornos y una ruta sin su disco no localiza nada.
            $table->string('disco')->nullable();
            $table->string('ruta')->nullable();
            $table->string('nombre_fichero')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('tamano')->nullable();
            $table->char('hash_sha256', 64)->nullable();

            // O una URL, para lo que vive fuera: un panel de un proveedor, un
            // registro de un SaaS. No todo lo que prueba algo es un fichero.
            $table->string('url_externa')->nullable();

            $table->date('fecha_obtencion');
            $table->date('fecha_caducidad')->nullable();
            $table->string('periodicidad_renovacion')->nullable();

            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['organizacion_id', 'fecha_caducidad']);
            $table->index(['organizacion_id', 'tipo']);
        });

        DB::statement("ALTER TABLE evidencias ADD CONSTRAINT evidencias_tipo_check CHECK (tipo IN ('captura', 'log', 'informe', 'contrato', 'registro', 'certificado'))");
        DB::statement("ALTER TABLE evidencias ADD CONSTRAINT evidencias_periodicidad_check CHECK (periodicidad_renovacion IS NULL OR periodicidad_renovacion IN ('mensual', 'trimestral', 'semestral', 'anual', 'bienal'))");

        // O fichero o URL, y exactamente uno. Una evidencia sin ninguno de los
        // dos no prueba nada, y con los dos no se sabe cuál es la prueba.
        DB::statement('ALTER TABLE evidencias ADD CONSTRAINT evidencias_origen_check CHECK (num_nonnulls(ruta, url_externa) = 1)');

        // Un fichero sin su huella no se puede demostrar íntegro, que es la
        // única razón por la que se guarda una huella.
        DB::statement('ALTER TABLE evidencias ADD CONSTRAINT evidencias_huella_check CHECK (ruta IS NULL OR (disco IS NOT NULL AND hash_sha256 IS NOT NULL))');

        DB::statement('ALTER TABLE evidencias ADD CONSTRAINT evidencias_caducidad_check CHECK (fecha_caducidad IS NULL OR fecha_caducidad >= fecha_obtencion)');

        Schema::create('evidencia_implantacion', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('evidencia_id')->constrained('evidencias')->cascadeOnDelete();
            $table->foreignId('implantacion_id')->constrained('implantaciones')->cascadeOnDelete();

            // Por qué esta prueba vale para este requisito. Con una evidencia
            // que cubre cuatro medidas de dos marcos, el matiz de cada vínculo
            // es distinto y no cabe en la descripción de la evidencia.
            $table->text('nota')->nullable();

            $table->foreignId('vinculada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['evidencia_id', 'implantacion_id']);
            $table->index(['organizacion_id', 'implantacion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidencia_implantacion');
        Schema::dropIfExists('evidencias');
    }
};
