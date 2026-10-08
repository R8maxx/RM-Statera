<?php

declare(strict_types=1);

namespace App\Http\Controllers\Plataforma;

use App\Domain\Plataforma\CuadroDeMando;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La casa de quien administra la plataforma (punto 53): lo que requiere
 * atención hoy, cada cifra con su enlace a la lista que la contesta.
 */
class CuadroDeMandoController extends Controller
{
    public function index(CuadroDeMando $cuadro): Response
    {
        return Inertia::render('plataforma/Inicio', ['cifras' => $cuadro->cifras()]);
    }
}
