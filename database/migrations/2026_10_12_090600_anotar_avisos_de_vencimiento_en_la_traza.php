<?php

declare(strict_types=1);

use App\Domain\Plataforma\Enums\AccionPlataforma;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Los avisos de vencimiento, en la traza de la plataforma.
 *
 * `avisos_suscripcion` ya recordaba qué hito se mandó, pero sólo para no
 * mandarlo dos veces: la ficha del cliente no lo enseñaba, y a la pregunta «¿le
 * avisamos de que vencía?» había que contestar mirando la base. Ahora
 * `suscripciones:avisar` deja además una línea en la traza, con el hito y a
 * quién fue, y la ficha la pinta con el resto de lo que ha hecho la plataforma.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->accionesDePlataforma(AccionPlataforma::cases());
    }

    public function down(): void
    {
        $this->accionesDePlataforma(array_filter(
            AccionPlataforma::cases(),
            static fn (AccionPlataforma $accion): bool => $accion !== AccionPlataforma::AvisoVencimientoEnviado,
        ));
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
