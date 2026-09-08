<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Implantacion\CambiarEstado;
use App\Domain\Implantacion\Enums\EstadoImplantacion;
use App\Domain\Implantacion\Excepciones\TransicionNoPermitida;
use App\Domain\Implantacion\Models\Implantacion;
use App\Http\Requests\CambiarEstadoImplantacionesRequest;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Http\Resources\ImplantacionRecurso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImplantacionController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request): Response
    {
        return Inertia::render('implantaciones/Index', $this->tabla(new ImplantacionRecurso, $request));
    }

    /**
     * Cambio de estado en bloque.
     *
     * Las que no admiten la transición no se tocan y se cuentan aparte: fallar
     * la operación entera porque una fila de doscientas no encajaba obliga al
     * usuario a adivinar cuál era.
     */
    public function cambiarEstado(
        CambiarEstadoImplantacionesRequest $request,
        CambiarEstado $cambiarEstado,
    ): RedirectResponse {
        $nuevo = EstadoImplantacion::from($request->string('estado')->toString());
        $nota = $request->string('nota')->toString() ?: null;

        // El scope global filtra por organización: los identificadores de otra
        // organización simplemente no aparecen.
        $implantaciones = Implantacion::query()
            ->whereIn('id', $request->array('implantaciones'))
            ->get();

        $cambiadas = 0;
        $rechazadas = 0;

        foreach ($implantaciones as $implantacion) {
            try {
                $cambiarEstado($implantacion, $nuevo, $request->user(), $nota);
                $cambiadas++;
            } catch (TransicionNoPermitida) {
                $rechazadas++;
            }
        }

        $mensaje = trans_choice(
            '{0}Ninguna implantación cambió de estado.|{1}1 implantación pasó a :estado.|[2,*]:count implantaciones pasaron a :estado.',
            $cambiadas,
            ['estado' => mb_strtolower($nuevo->etiqueta())],
        );

        if ($rechazadas > 0) {
            $mensaje .= " {$rechazadas} no admitían esa transición y se quedaron como estaban.";
        }

        Inertia::flash($cambiadas > 0 ? 'exito' : 'error', $mensaje);

        return back();
    }
}
