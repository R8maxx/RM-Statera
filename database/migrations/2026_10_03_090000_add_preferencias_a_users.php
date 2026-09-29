<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que la cuenta recuerda de quien la usa, y cuándo cambió la contraseña.
 *
 * **Las preferencias son de la cuenta y no del navegador.** El tema vivía en
 * `localStorage`, así que cada portátil nuevo empezaba en «el del sistema» y
 * nadie sabía por qué. Tres columnas y no un JSONB: son tres, las lee el
 * servidor —el tema lo escribe la plantilla antes del primer pintado, la página
 * de inicio la decide la redirección de después de entrar— y un `CHECK` sobre
 * una columna se escribe, uno sobre una clave de JSON no se mantiene.
 *
 * `password_cambiada_en` nace a nulo en las cuentas que ya existían: no se sabe
 * cuándo se puso su contraseña, y rellenarla con `created_at` sería afirmar una
 * fecha que nadie registró. La pantalla dice «sin fecha registrada».
 *
 * Las listas van escritas aquí y no leídas de los enums: una migración es una
 * foto del esquema en su día, y leer el enum de hoy haría que ejecutarla dentro
 * de un año crease otra restricción distinta.
 */
return new class extends Migration
{
    private const TEMAS = ['claro', 'oscuro', 'sistema'];

    private const PAGINAS_INICIO = ['panel', 'tareas', 'calendario'];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('password_cambiada_en')->nullable();
            $table->string('tema', 20)->default('sistema');
            $table->string('pagina_inicio', 20)->default('panel');
            $table->boolean('avisos_por_correo')->default(true);
        });

        $this->check('tema', self::TEMAS);
        $this->check('pagina_inicio', self::PAGINAS_INICIO);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_tema_check');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_pagina_inicio_check');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['password_cambiada_en', 'tema', 'pagina_inicio', 'avisos_por_correo']);
        });
    }

    /** @param  list<string>  $valores */
    private function check(string $columna, array $valores): void
    {
        $lista = implode(', ', array_map(static fn (string $valor): string => "'{$valor}'", $valores));

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_{$columna}_check CHECK ({$columna} IN ({$lista}))");
    }
};
