<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Fin de soporte del software base
|--------------------------------------------------------------------------
|
| Cuándo deja de recibir parches cada sistema operativo y cada motor de base de
| datos. Sirve para proponer `fin_soporte_so` al dar de alta un activo y para el
| indicador de soporte vencido, que es `op.exp.4` del ENS y `A.8.8` de ISO.
|
| VA EN CONFIGURACIÓN Y NO EN UNA TABLA, y el motivo importa: esto son hechos
| del mundo, no datos de una organización. El fin de soporte de Ubuntu 24.04 es
| el mismo para todos los clientes, así que meterlo en una tabla con
| `organizacion_id` sería duplicarlo por tenant y dejar que se desincronice. Y
| no es catálogo normativo —invariante 3—: son quince filas que se actualizan
| cuando sale una versión nueva, no un anexo que haya que versionar y diffear.
|
| Las fechas son las de soporte ESTÁNDAR, no las de soporte extendido de pago
| (Ubuntu Pro, Extended Support de RDS). Pagar por parches es una decisión que
| se toma después de ver que la fecha ha pasado, no antes: si el inventario ya
| contara el soporte extendido, la fecha nunca vencería y el aviso no saltaría.
|
| Un valor que no esté en estas listas NO es un error: se escribe la fecha a
| mano en el activo, o se deja en blanco. Lo que no puede pasar es que un
| sistema desconocido cuente como obsoleto — eso convertiría cada macOS del
| parque en un falso positivo.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Sistemas operativos
    |--------------------------------------------------------------------------
    |
    | `null` significa «versión continua, sin fecha de fin»: macOS y Amazon Linux
    | van por versión mayor y la organización actualiza en cadencia. No es lo
    | mismo que no saberlo.
    |
    */

    'sistemas_operativos' => [
        'Ubuntu 20.04 LTS' => '2025-05-31',
        'Ubuntu 22.04 LTS' => '2027-04-01',
        'Ubuntu 24.04 LTS' => '2029-05-31',
        'Ubuntu 26.04 LTS' => '2031-04-30',
        'Debian 12' => '2028-06-30',
        'Windows 10' => '2025-10-14',
        'Windows 11 Home' => null,
        'Windows 11 Pro' => null,
        'Windows Server 2019' => '2029-01-09',
        'Windows Server 2022' => '2031-10-14',
        'Amazon Linux 2023' => null,
        'macOS' => null,
        'Linux (distribución por confirmar)' => null,
        'Otro / no aplica' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Motores de base de datos
    |--------------------------------------------------------------------------
    |
    | Fechas de fin de soporte estándar en AWS RDS, que es donde caducan antes.
    |
    */

    'motores' => [
        'MySQL 8.0' => '2026-07-31',
        'MySQL 8.4' => '2029-07-31',
        'PostgreSQL 15' => '2027-11-12',
        'PostgreSQL 16' => '2028-11-09',
        'PostgreSQL 17' => '2029-11-08',
        'MariaDB 10.11' => '2028-02-29',
    ],

];
