<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La base de la URL que codifican los códigos QR de las etiquetas.
 *
 * Escanear la pegatina de un portátil tiene que abrir su ficha, no soltar un
 * `AVANZA|AV-PC-0001` que hay que buscar a mano. La columna existe porque la
 * URL pública de la organización no tiene por qué ser la de `APP_URL` —detrás
 * de un proxy, o con un dominio propio por cliente— y una etiqueta impresa dura
 * años: apuntar a la URL equivocada obliga a reimprimir el parque entero.
 *
 * Nula significa «usa `config('app.url')`», que es lo correcto en desarrollo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizaciones', function (Blueprint $table): void {
            $table->string('url_base_etiquetas')->nullable()->after('sector');
        });
    }

    public function down(): void
    {
        Schema::table('organizaciones', function (Blueprint $table): void {
            $table->dropColumn('url_base_etiquetas');
        });
    }
};
