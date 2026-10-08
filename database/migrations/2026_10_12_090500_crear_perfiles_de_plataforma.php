<?php

declare(strict_types=1);

use App\Domain\Plataforma\Enums\PerfilPlataforma;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los perfiles de la plataforma (punto 48).
 *
 * Quien administraba la plataforma lo podía todo. Ahora tiene un perfil, y el
 * perfil decide qué puede. Todo administrador que ya existía pasa a
 * Administración, que es lo que tenía.
 *
 * El segundo `CHECK` ata la marca y el perfil: una cuenta es de la plataforma
 * si y sólo si tiene perfil. Así no hay administrador sin perfil, que no podría
 * hacer nada sin saber por qué, ni perfil suelto en una cuenta de cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('perfil_plataforma')->nullable();
        });

        DB::table('users')->where('es_plataforma', true)->update(['perfil_plataforma' => PerfilPlataforma::Administracion->value]);

        $perfiles = implode(', ', array_map(static fn (PerfilPlataforma $perfil): string => "'{$perfil->value}'", PerfilPlataforma::cases()));

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_perfil_plataforma_check CHECK (perfil_plataforma IN ({$perfiles}))");
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_plataforma_con_perfil_check CHECK (es_plataforma = (perfil_plataforma IS NOT NULL))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_plataforma_con_perfil_check');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_perfil_plataforma_check');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('perfil_plataforma');
        });
    }
};
