<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Segundo factor obligatorio
    |--------------------------------------------------------------------------
    |
    | Requisito no funcional del § 6 de la especificación: segundo factor
    | obligatorio para los roles con permisos de escritura. La herramienta entra
    | en el alcance del propio SGSI y contiene el inventario, las
    | vulnerabilidades y las evidencias de la organización.
    |
    | Va por defecto ACTIVADO. Se apaga sólo en desarrollo, donde no hay una
    | aplicación de autenticación con la que confirmar el alta, y apagarlo tiene
    | que ser una decisión escrita en el entorno, no un olvido en el código.
    |
    */

    'exigir_dos_factores' => env('EXIGIR_DOS_FACTORES', true),

    /*
    |--------------------------------------------------------------------------
    | Bloqueo por inactividad
    |--------------------------------------------------------------------------
    |
    | Otro requisito del § 6: registro de sesiones y bloqueo por inactividad.
    | Pasados estos minutos sin ninguna petición, la sesión se cierra y hay que
    | volver a entrar —con el segundo factor, si la cuenta lo tiene—. Cero lo
    | apaga, y apagarlo tiene que ser una decisión escrita en el entorno.
    |
    */

    'inactividad_minutos' => (int) env('INACTIVIDAD_MINUTOS', 30),

];
