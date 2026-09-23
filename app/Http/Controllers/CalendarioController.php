<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Aviso\CalendarioVencimientos;
use App\Domain\Aviso\FiltrosVencimiento;
use App\Domain\Aviso\Fuente;
use App\Domain\Aviso\RejillaMes;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Http\Resources\Definicion\Filtro;
use App\Http\Resources\Definicion\Opcion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El calendario de obligaciones: § 4.16.
 *
 * **Vivía en `TareaController` y se mudó aquí**, y el motivo es el mismo por el
 * que `CalendarioVencimientos` nunca estuvo en `Domain/Tarea/`: esto enseña
 * vencimientos y no tareas. Con tres fuentes la confusión era tolerable; con
 * siete —de seis módulos distintos— colgarlo del plan de acción decía que es una
 * vista del plan, y no lo es.
 *
 * La pieza que lo delataba era el permiso: `/tareas/calendario` iba tras
 * `can:tareas.ver` y ya enseñaba evidencias y documentos. Ahora tiene el suyo,
 * `calendario.ver`, y además **cada fuente se filtra por el permiso de su
 * módulo** —eso lo resuelve `FiltrosVencimiento`—, porque si no una sola llave
 * abriría seis registros.
 *
 * El mes vive en la URL para que se pueda enlazar y compartir, y lo que no se
 * entienda es el mes de hoy: un 500 en una dirección que alguien guarda es peor
 * que enseñar otro mes.
 */
class CalendarioController extends Controller
{
    public function index(Request $request, CalendarioVencimientos $calendario): Response
    {
        $rejilla = RejillaMes::de($request->string('mes')->toString());
        $filtros = FiltrosVencimiento::desde($request);

        return Inertia::render('calendario/Index', [
            'rejilla' => $rejilla,
            // Se consulta por los extremos de la REJILLA y no por los del mes:
            // las casillas de relleno son días de verdad y lo que caiga en ellas
            // también hay que atenderlo.
            'vencimientos' => $calendario->entre(
                Carbon::parse($rejilla->primerDia),
                Carbon::parse($rejilla->ultimoDia),
                $filtros,
            ),
            'filtros' => $this->filtros($request),
            'filtrosAplicados' => $this->aplicados($request),
            /*
             * Lo que el filtro de responsable deja fuera **por no tener uno**.
             *
             * Excluirlas es lo correcto —dejarlas intactas sería el filtro
             * mintiendo—, pero desaparecer sin explicación es lo que convierte un
             * filtro en algo que nadie vuelve a usar. La pantalla lo dice.
             */
            'excluidasPorResponsable' => array_map(
                static fn (Fuente $fuente): string => $fuente->etiqueta(),
                $filtros->excluidasPorResponsable(),
            ),
        ]);
    }

    /**
     * Los tres filtros del calendario.
     *
     * Se declaran con la misma clase que los de una tabla —`Filtro`— para que
     * `BarraFiltros` los pinte sin enterarse de que aquí no hay tabla. Lo que no
     * comparten es la declaración de `TareaRecurso`: ver `FiltrosVencimiento`.
     *
     * **Sólo se ofrecen las fuentes que quien mira puede ver**, y lo decide el
     * servidor. Esconderlas con un `v-if` dejaría el filtro fuera de la lista y
     * la consulta dentro.
     *
     * @return list<Filtro>
     */
    private function filtros(Request $request): array
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return [
            Filtro::multiSelect('fuente', 'Qué', array_map(
                static fn (Fuente $fuente): Opcion => new Opcion($fuente->value, $fuente->etiqueta(), $fuente->icono()),
                Fuente::visiblesPara($usuario),
            ))->sinColumna(),
            Filtro::select('responsable_id', 'Responsable', fn (): array => User::query()
                ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                ->orderBy('name')
                ->get()
                ->map(fn (User $usuario): Opcion => new Opcion((string) $usuario->id, $usuario->name))
                ->all())->sinColumna()->resolver(),
            Filtro::porScope('vencidos', 'Sólo lo vencido', 'vencidas')->sinColumna(),
        ];
    }

    /**
     * Lo que se aplicó de verdad, para los chips.
     *
     * @return array<string, string|list<string>>
     */
    private function aplicados(Request $request): array
    {
        /** @var array<string, mixed> $recibidos */
        $recibidos = $request->array('filter');
        $aplicados = [];

        foreach (['fuente', 'responsable_id', 'vencidos'] as $clave) {
            $valor = $recibidos[$clave] ?? null;

            if ($valor === null || $valor === '' || $valor === []) {
                continue;
            }

            $aplicados[$clave] = is_array($valor)
                ? array_values(array_map(strval(...), $valor))
                : (string) $valor;
        }

        return $aplicados;
    }
}
