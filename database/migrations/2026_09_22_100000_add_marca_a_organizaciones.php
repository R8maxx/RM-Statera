<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El logo de la organización cliente.
 *
 * **Dos piezas y no una**, como el propio logotipo de Statera tiene variante
 * `completo` y variante `simbolo`: el horizontal para la portada del PDF y el
 * desplegable de organización, y el cuadrado para la cabecera de cada página,
 * que mide 8 pt de alto y donde un logo con el nombre dentro no se lee.
 *
 * Las dos **opcionales, y degradan a lo que ya había**: sin logo la portada sale
 * como siempre, sin símbolo la cabecera sigue siendo «Statera · organización» en
 * texto. Nadie se queda peor de lo que estaba por no subir nada.
 *
 * **Sólo la ruta.** Ni `mime`, ni `tamano`, ni huella: lo que se guarda es un
 * PNG normalizado o un SVG saneado, y la extensión del propio fichero distingue
 * los dos casos, que es lo único que hay que saber para servirlo. Mismo reparto
 * que `users.foto_ruta`, y por el mismo motivo: esto es un atributo de la
 * organización, no un adjunto que documente nada.
 *
 * Viven en el disco `adjuntos`, bajo `marca/{organizacion}/`, porque es el único
 * de los tres sin Object Lock y un logo se sustituye — el argumento está escrito
 * en `BorrarAdjunto`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizaciones', function (Blueprint $table): void {
            $table->string('logo_ruta')->nullable()->after('provincia');
            $table->string('simbolo_ruta')->nullable()->after('logo_ruta');
        });
    }

    public function down(): void
    {
        Schema::table('organizaciones', function (Blueprint $table): void {
            $table->dropColumn(['logo_ruta', 'simbolo_ruta']);
        });
    }
};
