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
