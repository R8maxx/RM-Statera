<?php

declare(strict_types=1);

namespace App\Http\Controllers\Plataforma;

use App\Domain\Plataforma\SaludDelServicio;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La salud del servicio (punto 55): copias, colas y trabajos fallidos.
 */
class SaludController extends Controller
{
    public function index(SaludDelServicio $salud): Response
    {
        return Inertia::render('plataforma/salud/Index', $salud->resumen());
    }
}
