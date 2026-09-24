<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Autorizacion\EscrituraPropia;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Tarea\Models\Tarea;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rechaza la escritura sobre una tarea o una implantación ajena (§ 4.19).
 *
 * **Un middleware y no el `authorize()` de cada `FormRequest`**, porque la mitad
 * de las rutas de escritura de estos dos módulos no tienen `FormRequest`
 * —borrar una tarea, desvincular una evidencia— y la regla tiene que valer igual
 * en todas. Va en el grupo entero de escritura: una ruta nueva la hereda sin que
 * nadie se acuerde.
 *
 * Mira el registro **principal** de la ruta: la tarea si la hay, y si no la
 * implantación. En `/tareas/{tarea}/implantaciones/{implantacion}` lo que se
 * escribe es la tarea —el vínculo es suyo—, así que la implantación puede ser
 * de otro.
 *
 * Las acciones masivas no tienen registro en la ruta y filtran en su
 * controlador. Responde 403 y no 404: la tarea existe, se ve, y la pregunta es
 * de quién es.
 */
class EscribeLoSuyo
{
    public function __construct(private readonly EscrituraPropia $escritura) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var ?User $usuario */
        $usuario = $request->user();
        $registro = $request->route('tarea') ?? $request->route('implantacion');

        if ($usuario !== null
            && ($registro instanceof Tarea || $registro instanceof Implantacion)
            && ! $this->escritura->puedeEscribir($usuario, $registro)) {
            abort(403, 'Está a cargo de otra persona. Puedes verla, pero sólo la mueve quien la tiene asignada o el responsable de seguridad.');
        }

        return $next($request);
    }
}
