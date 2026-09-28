<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Copias de seguridad con restauración probada
    |--------------------------------------------------------------------------
    |
    | Requisito no funcional del § 6: «backups cifrados con restauración probada
    | y periodicidad documentada». La herramienta entra en el alcance de su
    | propio SGSI (invariante 8), y una copia que nadie ha restaurado es una
    | promesa, no una copia. Por eso hay dos comandos y los dos van programados:
    | `copias:hacer` cada noche y `copias:verificar` cada semana, que restaura la
    | última en una base aparte y la compara con lo que se copió.
    |
    */

    /*
    | Dónde van. Es un bucket propio y no un prefijo de los otros: en producción
    | tiene que vivir en otra cuenta o en otra región, porque una copia que cae
    | con lo que copia no sirve de nada.
    */
    'disco' => 'copias',

    /*
    | La clave con la que se cifra el volcado: 32 bytes en base64, generados con
    | `php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'`. **No es
    | `APP_KEY`** a propósito: la clave de la aplicación se rota por motivos que
    | no tienen nada que ver con las copias, y rotarla dejaría ilegibles todas
    | las anteriores. Ésta se guarda fuera del servidor, con la copia de la
    | propia clave de la organización, porque sin ella la copia no se restaura.
    */
    'clave' => env('COPIAS_CLAVE'),

    /*
    | Las conexiones. `pgsql_copias` es un rol de sólo lectura con BYPASSRLS: la
    | copia tiene que ver a todas las organizaciones, y un volcado filtrado por
    | RLS saldría vacío sin fallar. `pgsql_verificacion` es el mismo rol contra
    | una base efímera, `statera_verificacion`, donde se restaura para probar.
    */
    'conexion' => 'pgsql_copias',
    'conexion_verificacion' => 'pgsql_verificacion',

    /*
    | Los ficheros. Se copian los objetos de los tres discos, sin borrar nunca
    | nada del destino: un objeto que desaparece del origen es justo lo que una
    | copia tiene que poder devolver.
    */
    'discos' => ['evidencias', 'documentos', 'adjuntos'],

    /*
    | Cuántos días se guardan los volcados. La última copia se conserva siempre,
    | aunque sea más vieja, porque borrar la única que hay es peor que guardar
    | una de más.
    */
    'conservar_dias' => (int) env('COPIAS_CONSERVAR_DIAS', 30),

];
