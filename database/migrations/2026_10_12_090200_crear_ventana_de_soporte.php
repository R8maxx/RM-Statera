<?php

declare(strict_types=1);

use App\Domain\Plataforma\Enums\AccionPlataforma;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La ventana de soporte (punto 44): el cliente abre la puerta, por un tiempo, y
 * la plataforma entra a mirar.
 *
 * **En `organizaciones` y no en una tabla aparte**, por lo mismo que la
 * suscripción: la plataforma tiene que saber qué clientes tienen la puerta
 * abierta sin cruzar RLS. El histórico —quién la abrió, cuándo se cerró— queda
 * en la traza del tenant, porque `Organizacion::booted()` registra cada cambio
 * de estas dos columnas.
 *
 * La traza del tenant aprende dos verbos: entrar y salir de soporte. Son lo
 * que el cliente quiere poder leer en la suya sin pedírnoslo. El `down()`
 * restaura el `CHECK` con `NOT VALID`, como el de las sesiones: los eventos no
 * se pueden borrar.
 */
return new class extends Migration
{
    private const ANTERIORES = ['creado', 'actualizado', 'eliminado', 'rol_cambiado', 'inicio_sesion', 'cierre_sesion', 'intento_fallido'];

    private const NUEVAS = ['soporte_entrada', 'soporte_salida'];

    public function up(): void
    {
        Schema::table('organizaciones', function (Blueprint $table): void {
            $table->timestampTz('soporte_hasta')->nullable();
            $table->foreignId('soporte_abierto_por')->nullable()->constrained('users')->nullOnDelete();
        });

        $this->restringirTraza([...self::ANTERIORES, ...self::NUEVAS], validar: true);
        $this->accionesDePlataforma(AccionPlataforma::cases());
    }

    public function down(): void
    {
        $this->accionesDePlataforma(array_filter(
            AccionPlataforma::cases(),
            static fn (AccionPlataforma $accion): bool => ! in_array($accion, [AccionPlataforma::SoporteEntrada, AccionPlataforma::SoporteSalida], true),
        ));
        $this->restringirTraza(self::ANTERIORES, validar: false);

        Schema::table('organizaciones', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('soporte_abierto_por');
            $table->dropColumn('soporte_hasta');
        });
    }

    /** @param  list<string>  $acciones */
    private function restringirTraza(array $acciones, bool $validar): void
    {
        $lista = implode(', ', array_map(static fn (string $accion): string => "'{$accion}'", $acciones));

        DB::statement('ALTER TABLE eventos_auditoria DROP CONSTRAINT IF EXISTS eventos_auditoria_accion_check');
        DB::statement("ALTER TABLE eventos_auditoria ADD CONSTRAINT eventos_auditoria_accion_check CHECK (accion IN ({$lista}))".($validar ? '' : ' NOT VALID'));
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
