<?php

declare(strict_types=1);

use App\Domain\Plataforma\Enums\AccionPlataforma;
use App\Domain\Plataforma\Enums\EstadoSolicitud;
use App\Domain\Plataforma\Enums\TipoRescate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las solicitudes de rescate de cuentas, con dos personas (punto 52).
 *
 * Restablecer el segundo factor o designar un nuevo responsable toca la llave
 * de una cuenta ajena, así que se **pide** con la verificación escrita y lo
 * **ejecuta** otro administrador. Las dos transiciones —pedir y resolver—
 * quedan en la fila, con fecha y autor (invariante 7), y en la traza.
 *
 * Lleva `organizacion_afectada_id` y no `organizacion_id`, como el resto de
 * datos de la plataforma: la lee la plataforma entera y no tiene RLS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_plataforma', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizacion_afectada_id')->constrained('organizaciones')->cascadeOnDelete();
            $table->string('tipo');
            $table->foreignId('cuenta_id')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('datos')->nullable();
            $table->text('verificacion');
            $table->foreignId('solicitada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('solicitada_en');
            $table->string('estado')->default(EstadoSolicitud::Pendiente->value);
            $table->foreignId('resuelta_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resuelta_en')->nullable();
            $table->text('motivo_rechazo')->nullable();
            $table->boolean('sin_segunda_persona')->default(false);

            $table->index(['estado', 'solicitada_en']);
            $table->index('organizacion_afectada_id');
        });

        $tipos = implode(', ', array_map(static fn (TipoRescate $tipo): string => "'{$tipo->value}'", TipoRescate::cases()));
        // `caducada` no se guarda nunca: se deriva de la fecha.
        $estados = implode(', ', array_map(
            static fn (EstadoSolicitud $estado): string => "'{$estado->value}'",
            array_filter(EstadoSolicitud::cases(), static fn (EstadoSolicitud $estado): bool => $estado !== EstadoSolicitud::Caducada),
        ));

        DB::statement("ALTER TABLE solicitudes_plataforma ADD CONSTRAINT solicitudes_plataforma_tipo_check CHECK (tipo IN ({$tipos}))");
        DB::statement("ALTER TABLE solicitudes_plataforma ADD CONSTRAINT solicitudes_plataforma_estado_check CHECK (estado IN ({$estados}))");
        DB::statement('ALTER TABLE solicitudes_plataforma ADD CONSTRAINT solicitudes_plataforma_resuelta_check CHECK ((estado = \'pendiente\') = (resuelta_en IS NULL))');
        DB::statement('ALTER TABLE solicitudes_plataforma ADD CONSTRAINT solicitudes_plataforma_verificacion_check CHECK (length(trim(verificacion)) > 0)');

        $this->accionesDePlataforma(AccionPlataforma::cases());
    }

    public function down(): void
    {
        $this->accionesDePlataforma(array_filter(
            AccionPlataforma::cases(),
            static fn (AccionPlataforma $accion): bool => ! in_array($accion, [AccionPlataforma::RescateSolicitado, AccionPlataforma::RescateEjecutado, AccionPlataforma::RescateRechazado], true),
        ));

        Schema::dropIfExists('solicitudes_plataforma');
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
