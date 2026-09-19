<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Lo periódico
|--------------------------------------------------------------------------
|
| Temprano y una sola vez al día: el resumen de vencimientos se lee al empezar
| la jornada y decide qué se hace hoy. `onOneServer` porque con dos procesos
| corriendo el planificador, la organización recibiría el mismo correo dos
| veces, y un aviso duplicado se empieza a ignorar antes que uno que falta.
|
| El comando se salta las organizaciones que no tienen nada que avisar, así que
| un día tranquilo no genera ningún correo.
|
*/
Schedule::command('avisos:enviar')->dailyAt('07:00')->onOneServer();

/*
| El cierre de periodo de los indicadores calculados (§ 4.14, cláusula 9.1).
|
| **Diario y no mensual**, aunque el periodo más corto sea el mes: el comando
| mira qué periodo ha cerrado y sella sólo lo que falte, así que correrlo todos
| los días es idempotente y correrlo una vez al mes deja la serie con un agujero
| en cuanto el planificador se pierda un día. Es la misma disciplina que hace
| idempotente al importador del catálogo.
|
| Después del aviso: si el resumen de la mañana y la medición del periodo caen a
| la vez, el correo se manda con las cifras de ayer y el panel enseña las de hoy.
*/
Schedule::command('indicadores:medir')->dailyAt('07:30')->onOneServer();
