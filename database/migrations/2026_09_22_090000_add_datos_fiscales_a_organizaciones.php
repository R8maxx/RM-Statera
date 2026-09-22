<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La identificación legal de la organización.
 *
 * Hasta aquí `organizaciones` tenía `nombre`, `cif`, `sector` y dos banderas, y
 * la portada de todo documento entregado imprimía `nombre` — que es un nombre de
 * pantalla. **Una Declaración de Aplicabilidad la firma una persona jurídica**, y
 * eso es `razon_social`.
 *
 * **No entra `nombre_comercial`, y es deliberado.** `nombre` ya *es* el nombre
 * comercial: es lo que pintan el sidebar, los correos y la paleta de comandos.
 * Una columna aparte sería el mismo dato en dos sitios que pueden discrepar, que
 * es lo que el repositorio ya evita con `personas.activa`, con `vigente` en el
 * análisis del contexto y con el ámbito derivado de una cuestión del DAFO. Lo
 * que cambia es la etiqueta del campo en el formulario, no la columna.
 *
 * **Todas nullable**: una organización que ya existe no puede quedar inválida por
 * una migración, y la mayoría de estos datos no se saben el primer día.
 *
 * **Sin `telefono`, `email` ni `web`**: hoy no los leería nadie. Esta misma tabla
 * ya arrastra tres columnas que nadie lee —`sector`, `activa` y el método
 * `leAplicaElEns()`—, y no hace falta la cuarta. Entran el día que algo las
 * imprima.
 *
 * El domicilio se registra y **todavía no se imprime en ningún documento**: el
 * membrete completo —logo, razón social y domicilio— es trabajo aparte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizaciones', function (Blueprint $table): void {
            $table->string('razon_social')->nullable()->after('nombre');
            $table->string('domicilio')->nullable()->after('sector');
            $table->string('codigo_postal', 10)->nullable()->after('domicilio');
            $table->string('municipio')->nullable()->after('codigo_postal');
            $table->string('provincia')->nullable()->after('municipio');
        });
    }

    public function down(): void
    {
        Schema::table('organizaciones', function (Blueprint $table): void {
            $table->dropColumn(['razon_social', 'domicilio', 'codigo_postal', 'municipio', 'provincia']);
        });
    }
};
