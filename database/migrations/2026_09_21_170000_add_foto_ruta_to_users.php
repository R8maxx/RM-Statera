<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La foto de perfil de una cuenta.
 *
 * **Una columna y no una tabla, ni un adjunto.** Un adjunto lleva título, nota,
 * quién lo subió y N:M con su anfitrión porque documenta un registro; una foto
 * de perfil es un atributo de la cuenta y no documenta nada. Tampoco lleva
 * `disco`, `mime` ni `tamano`: `GuardarFotoPerfil` normaliza todo lo que entra a
 * un WebP de 256×256 en el disco `adjuntos`, así que esos tres campos serían
 * siempre el mismo valor.
 *
 * **En el disco `adjuntos` y no en el de evidencias**, que es el único de los
 * tres sin Object Lock. No es una elección de comodidad: la cara de alguien es
 * un dato personal, y bajo Object Lock en modo compliance una foto subida por
 * error no se podría borrar nunca. Quien ejerce su derecho de supresión no
 * acepta «la fila ya no está» — el mismo argumento que ya está escrito en
 * `BorrarAdjunto`.
 *
 * `users` se queda como está en todo lo demás: sin `PerteneceAOrganizacion`,
 * sin scope global y sin RLS, porque la autenticación tiene que poder encontrar
 * a alguien antes de saber de qué organización es. De ahí que la ruta que sirve
 * la foto sea `/perfil/foto`, **sin parámetro de usuario**: si se pudiera pedir
 * la de otro, habría que acotarla a mano y no hay ninguna capa que avise si se
 * olvida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('foto_ruta')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('foto_ruta');
        });
    }
};
