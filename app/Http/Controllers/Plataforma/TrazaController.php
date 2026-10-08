<?php

declare(strict_types=1);

namespace App\Http\Controllers\Plataforma;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Http\Resources\EventoPlataformaRecurso;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La traza de la plataforma, consultable (punto 50).
 */
class TrazaController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request, EventoPlataformaRecurso $recurso): Response
    {
        return Inertia::render('plataforma/traza/Index', $this->tabla($recurso, $request));
    }
}
