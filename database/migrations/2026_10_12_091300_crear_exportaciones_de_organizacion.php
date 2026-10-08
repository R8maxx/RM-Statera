<?php

declare(strict_types=1);

use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Enums\EstadoExportacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las exportaciones de todos los datos de un cliente (punto 56): para el que se
 * va —portabilidad del RGPD y lo que suela pedir el contrato— y antes de
 * cualquier borrado definitivo.
 *
 * El fichero vive en el disco `adjuntos`, el único sin Object Lock, porque
 * caduca a los siete días y hay que poder borrarlo. Esta tabla es de la
 * plataforma: sin RLS y con `organizacion_afectada_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exportaciones_organizacion', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_afectada_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->foreignId('solicitada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado');
            $table->string('ruta')->nullable();
            $table->unsignedBigInteger('tamano')->nullable();
            $table->string('huella', 64)->nullable();
            $table->text('error')->nullable();
            $table->timestampTz('solicitada_en');
            $table->timestampTz('generada_en')->nullable();
            $table->timestampTz('caduca_en')->nullable();

            $table->index(['organizacion_afectada_id', 'solicitada_en']);
        });

        $estados = implode(', ', array_map(static fn (EstadoExportacion $estado): string => "'{$estado->value}'", EstadoExportacion::cases()));
        DB::statement("ALTER TABLE exportaciones_organizacion ADD CONSTRAINT exportaciones_organizacion_estado_check CHECK (estado IN ({$estados}))");

        $this->accionesDePlataforma(AccionPlataforma::cases());
    }

    public function down(): void
    {
        $this->accionesDePlataforma(array_filter(
            AccionPlataforma::cases(),
            static fn (AccionPlataforma $accion): bool => ! in_array($accion, [AccionPlataforma::ExportacionSolicitada, AccionPlataforma::ExportacionDescargada], true),
        ));

        Schema::dropIfExists('exportaciones_organizacion');
    }

    /** @param  iterable<AccionPlataforma>  $acciones */
    private function accionesDePlataforma(iterable $acciones): void
    {
        $valores = [];

        foreach ($acciones as $accion) {
            $valores[] = "'{$accion->value}'";
        }

        DB::statement('ALTER TABLE eventos_plataforma DROP CONSTRAINT IF EXISTS eventos_plataforma_accion_check');
        DB::statement('ALTER TABLE eventos_plataforma ADD CONSTRAINT eventos_plataforma_accion_check CHECK (accion IN ('.implode(', ', $valores).')) NOT VALID');
    }
};
