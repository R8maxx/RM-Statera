<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Metrica\Models\Indicador;
use App\Domain\Objetivo\AbrirActuacion;
use App\Domain\Objetivo\CambiarEstadoObjetivo;
use App\Domain\Objetivo\CodigoObjetivo;
use App\Domain\Objetivo\Enums\EstadoObjetivo;
use App\Domain\Objetivo\Excepciones\TransicionDeObjetivoNoPermitida;
use App\Domain\Objetivo\Models\Objetivo;
use App\Domain\Objetivo\Models\ObjetivoTransicion;
use App\Domain\Objetivo\RegistrarObjetivo;
use App\Domain\Objetivo\RegistroObjetivos;
use App\Domain\Objetivo\VincularActuacion;
use App\Domain\Objetivo\VincularIndicador;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Tarea\Coste;
use App\Domain\Tarea\Enums\PrioridadTarea;
use App\Domain\Tarea\Models\Tarea;
use App\Domain\Tarea\Plazo;
use App\Http\Requests\AbrirActuacionRequest;
use App\Http\Requests\CambiarEstadoObjetivoRequest;
use App\Http\Requests\GuardarObjetivoRequest;
use App\Http\Requests\VincularActuacionRequest;
use App\Http\Requests\VincularIndicadorRequest;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Http\Resources\ObjetivoRecurso;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Los objetivos de seguridad de la información: la cláusula 6.2.
 *
 * Es lo que le faltaba al § 4.14 por el otro lado. Allí se declara qué se mide y
 * cada cuánto; aquí, a qué se compromete la organización y contra qué cifra se
 * comprueba. Dos cosas distintas que la norma pide las dos, y por eso este módulo
 * llega **después** del de indicadores: al revés, el objetivo nacería con el campo
 * que el auditor más mira —«cómo se evaluarán los resultados»— y nada detrás.
 *
 * **Aprobar va con su propio permiso y se comprueba en el controlador**, como
 * verificar una no conformidad: la ruta de transición es una sola y el destino es
 * lo que decide. Quien redacta un objetivo no es quien compromete a la
 * organización con él.
 *
 * Las actuaciones **son tareas** y se manejan desde aquí, no desde `/tareas`:
 * abrirlas por el camino general obligaría a elegir un origen que no se ofrece y
 * dejaría el vínculo a medias.
 */
class ObjetivoController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request, ObjetivoRecurso $recurso, RegistroObjetivos $registro): Response
    {
        return Inertia::render('objetivos/Index', [
            ...$this->tabla($recurso, $request),
            'alertas' => $registro->alertas(),
            'pendientes' => $registro->pendientes(),
            'total' => $registro->total(),
        ]);
    }

    public function create(CodigoObjetivo $codigos): Response
    {
        return Inertia::render('objetivos/Formulario', [
            'objetivo' => null,
            'sugerencia' => ['codigo' => $codigos->siguiente()],
            ...$this->opciones(),
        ]);
    }

    public function store(GuardarObjetivoRequest $request, RegistrarObjetivo $registrar): RedirectResponse
    {
        $objetivo = $registrar($request->validated(), $request->user());

        Inertia::flash('exito', "Objetivo {$objetivo->codigo} registrado.");

        return to_route('objetivos.show', $objetivo);
    }

    public function show(Objetivo $objetivo): Response
    {
        $objetivo->load([
            'responsable',
            'aprobadoPor',
            'indicadores.ultimaMedicion',
            'indicadores.responsable',
            'tareas.responsable',
            'transiciones.usuario',
        ]);

        $avance = $objetivo->avance();

        return Inertia::render('objetivos/Ficha', [
            'objetivo' => $this->serializar($objetivo),
            /*
             * El avance se manda aparte del objetivo porque no es un campo suyo:
             * se deriva de sus indicadores y se enseña **al lado** del estado sin
             * sobrescribirlo. Ver `Avance`.
             */
            'avance' => [
                'enObjetivo' => $avance->enObjetivo,
                'medidos' => $avance->medidos,
                'total' => $avance->total,
                'etiqueta' => $avance->etiqueta(),
                'tono' => $avance->tono(),
                /*
                 * La contradicción se señala y no se corrige: un objetivo que
                 * alguien dio por alcanzado con indicadores fuera de objetivo se
                 * ve de un vistazo, y la herramienta no toca el dato. Mismo papel
                 * que `Riesgo::residualSinRespaldo()`.
                 */
                'contradice' => $objetivo->estado === EstadoObjetivo::Alcanzado && $avance->seQuedaCorto(),
            ],
            'indicadores' => $objetivo->indicadores
                ->map(fn (Indicador $indicador): array => $this->serializarIndicador($indicador))
                ->values()
                ->all(),
            'actuaciones' => $objetivo->tareas
                ->map(fn (Tarea $tarea): array => $this->serializarActuacion($tarea))
                ->values()
                ->all(),
            /*
             * El coste se cuenta **sobre tareas distintas**, como en el plan de
             * adecuación y en una no conformidad: una actuación que empuja dos
             * objetivos se presupuesta una vez en cada ficha y nunca dos en la
             * misma. Y viaja con cuántas van sin estimar, que es el denominador
             * sin el cual «1.200 €» se lee como el coste total.
             */
            'coste' => [
                'total' => Coste::escribir(Coste::total($objetivo->tareas->all())),
                'sinEstimar' => Coste::sinEstimar($objetivo->tareas->all()),
            ],
            /*
             * Las transiciones las manda el servidor para que el cliente no
             * reconstruya la máquina de estados: un gesto que se acepta y luego
             * falla se explica mucho peor que uno que no se ofrece.
             */
            'transiciones' => array_map(
                fn (EstadoObjetivo $destino): array => [
                    'valor' => $destino->value,
                    'etiqueta' => $destino->etiqueta(),
                    'tono' => $destino->tono(),
                    'icono' => $destino->icono(),
                    'exigeMotivo' => $this->exigeMotivo($objetivo->estado, $destino),
                    /*
                     * Aprobar y declarar el resultado son de dirección; retirar
                     * también, porque es renunciar a un compromiso adquirido.
                     */
                    'permiso' => $destino->esComprometido() || $destino === EstadoObjetivo::Retirado
                        ? Permiso::ObjetivosAprobar->value
                        : Permiso::ObjetivosGestionar->value,
                ],
                $objetivo->estado->transicionesPermitidas(),
            ),
            'historial' => $objetivo->transiciones
                ->map(fn (ObjetivoTransicion $transicion): array => [
                    'id' => $transicion->id,
                    'anterior' => $transicion->estado_anterior?->etiqueta(),
                    'nuevo' => $transicion->estado_nuevo->etiqueta(),
                    'tono' => $transicion->estado_nuevo->tono(),
                    'icono' => $transicion->estado_nuevo->icono(),
                    'usuario' => $transicion->usuario?->name,
                    'fecha' => $transicion->created_at->format('d/m/Y H:i'),
                    'nota' => $transicion->nota,
                ])
                ->values()
                ->all(),
            'prioridades' => array_map(
                static fn (PrioridadTarea $prioridad): array => [
                    'valor' => $prioridad->value,
                    'etiqueta' => $prioridad->etiqueta(),
                ],
                PrioridadTarea::cases(),
            ),
            'puedeGestionar' => $this->puede(Permiso::ObjetivosGestionar),
            'puedeAprobar' => $this->puede(Permiso::ObjetivosAprobar),
            ...$this->opciones(),
        ]);
    }

    public function edit(Objetivo $objetivo): Response
    {
        return Inertia::render('objetivos/Formulario', [
            'objetivo' => $this->serializar($objetivo),
            'sugerencia' => null,
            ...$this->opciones(),
        ]);
    }

    public function update(GuardarObjetivoRequest $request, Objetivo $objetivo): RedirectResponse
    {
        $objetivo->update($request->validated());

        Inertia::flash('exito', 'Objetivo actualizado.');

        return to_route('objetivos.show', $objetivo);
    }

    public function destroy(Objetivo $objetivo): RedirectResponse
    {
        $codigo = $objetivo->codigo;
        $objetivo->delete();

        Inertia::flash('exito', "Objetivo {$codigo} eliminado.");

        return to_route('objetivos.index');
    }

    // --- El ciclo de la cláusula 6.2 ----------------------------------------

    public function transicion(
        CambiarEstadoObjetivoRequest $request,
        Objetivo $objetivo,
        CambiarEstadoObjetivo $cambiar,
    ): RedirectResponse {
        $destino = EstadoObjetivo::from((string) $request->validated('estado'));

        /*
         * Aprobar, declarar el resultado y retirar van con `objetivos.aprobar`, y
         * se comprueba aquí y no en la ruta porque la ruta es una sola: quien
         * redacta un objetivo no compromete a la organización con él. Es el mismo
         * reparto que la verificación de eficacia en el § 4.13.
         */
        if (($destino->esComprometido() || $destino === EstadoObjetivo::Retirado) && ! $this->puede(Permiso::ObjetivosAprobar)) {
            abort(403);
        }

        try {
            $cambiar(
                $objetivo,
                $destino,
                $request->user(),
                $request->string('nota')->value() ?: null,
            );
        } catch (TransicionDeObjetivoNoPermitida $error) {
            return back()->withErrors(['estado' => $error->getMessage()]);
        }

        Inertia::flash('exito', 'Objetivo actualizado.');

        return back();
    }

    // --- Cómo se evalúan los resultados (6.2, planificación e) ---------------

    public function vincularIndicador(
        VincularIndicadorRequest $request,
        Objetivo $objetivo,
        VincularIndicador $vincular,
    ): RedirectResponse {
        // Por el modelo y no por el id a pelo: así pasa por el scope de
        // organización, que es lo que impide vincular el indicador de otro cliente.
        $indicador = Indicador::query()->findOrFail($request->validated('indicador_id'));

        $vincular->vincular($objetivo, $indicador, $request->user());

        Inertia::flash('exito', "Indicador {$indicador->codigo} vinculado.");

        return back();
    }

    public function desvincularIndicador(
        Objetivo $objetivo,
        Indicador $indicador,
        VincularIndicador $vincular,
    ): RedirectResponse {
        $vincular->desvincular($objetivo, $indicador);

        Inertia::flash('exito', 'Indicador desvinculado. Sigue midiéndose; deja de evaluar este objetivo.');

        return back();
    }

    // --- Qué se hará (6.2, planificación a) ---------------------------------

    public function abrirActuacion(
        AbrirActuacionRequest $request,
        Objetivo $objetivo,
        AbrirActuacion $abrir,
    ): RedirectResponse {
        $tarea = $abrir($objetivo, $request->validated(), $request->user());

        Inertia::flash('exito', "Actuación «{$tarea->titulo}» abierta.");

        return back();
    }

    public function vincularActuacion(
        VincularActuacionRequest $request,
        Objetivo $objetivo,
        VincularActuacion $vincular,
    ): RedirectResponse {
        $tarea = Tarea::query()->findOrFail($request->validated('tarea_id'));

        $vincular->vincular($objetivo, $tarea, $request->user());

        Inertia::flash('exito', 'Actuación vinculada.');

        return back();
    }

    public function desvincularActuacion(
        Objetivo $objetivo,
        Tarea $tarea,
        VincularActuacion $vincular,
    ): RedirectResponse {
        $vincular->desvincular($objetivo, $tarea);

        Inertia::flash('exito', 'Actuación desvinculada. La tarea sigue en el plan de acción.');

        return back();
    }

    private function exigeMotivo(EstadoObjetivo $actual, EstadoObjetivo $destino): bool
    {
        if ($destino === EstadoObjetivo::Retirado || $destino === EstadoObjetivo::NoAlcanzado) {
            return true;
        }

        return $destino === EstadoObjetivo::Aprobado && $actual->esCerrado();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(Objetivo $objetivo): array
    {
        $plazo = Plazo::para(
            $objetivo->fecha_objetivo,
            $objetivo->estado->esCerrado(),
            $objetivo->fecha_cierre,
            $objetivo->haVencido(),
            $objetivo->estado === EstadoObjetivo::Retirado ? 'Retirado' : 'Cerrado',
        );

        return [
            'id' => $objetivo->id,
            'codigo' => $objetivo->codigo,
            'titulo' => $objetivo->titulo,
            'descripcion' => $objetivo->descripcion,
            'recursos' => $objetivo->recursos,
            'estado' => $objetivo->estado->value,
            'estadoEtiqueta' => $objetivo->estado->etiqueta(),
            'estadoTono' => $objetivo->estado->tono(),
            'estadoIcono' => $objetivo->estado->icono(),
            'esComprometido' => $objetivo->estado->esComprometido(),
            'responsable_id' => $objetivo->responsable_id,
            'responsable' => $objetivo->responsable?->name,
            'fecha_objetivo' => $objetivo->fecha_objetivo?->toDateString(),
            'fechaCierre' => $objetivo->fecha_cierre?->format('d/m/Y'),
            'aprobadoPor' => $objetivo->aprobadoPor?->name,
            'aprobadoEn' => $objetivo->aprobado_en?->format('d/m/Y H:i'),
            'notaAprobacion' => $objetivo->nota_aprobacion,
            'plazoEtiqueta' => $plazo->etiqueta,
            'plazoTono' => $plazo->tono,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarIndicador(Indicador $indicador): array
    {
        $cumplimiento = $indicador->cumplimiento();
        $ultima = $indicador->ultimaMedicion;

        return [
            'id' => $indicador->id,
            'codigo' => $indicador->codigo,
            'nombre' => $indicador->nombre,
            'periodicidad' => $indicador->periodicidad->etiqueta(),
            'objetivo' => $indicador->objetivo === null ? null : $indicador->unidad->escribir((float) $indicador->objetivo),
            'ultimoValor' => $ultima === null ? null : $indicador->unidad->escribir((float) $ultima->valor),
            'ultimoPeriodo' => $ultima === null ? null : $indicador->periodicidad->etiquetaDe($ultima->periodo_inicio),
            'cumplimiento' => $cumplimiento->value,
            'cumplimientoEtiqueta' => $cumplimiento->etiqueta(),
            'cumplimientoTono' => $cumplimiento->tono(),
            'cumplimientoIcono' => $cumplimiento->icono(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarActuacion(Tarea $tarea): array
    {
        $plazo = Plazo::de($tarea);

        return [
            'id' => $tarea->id,
            'titulo' => $tarea->titulo,
            'estado' => $tarea->estado->value,
            'estadoEtiqueta' => $tarea->estado->etiqueta(),
            'estadoTono' => $tarea->estado->tono(),
            'estadoIcono' => $tarea->estado->icono(),
            'responsable' => $tarea->responsable?->name,
            'plazoEtiqueta' => $plazo->etiqueta,
            'plazoTono' => $plazo->tono,
            'fecha' => $plazo->fecha,
            'coste' => Coste::deLaTarea($tarea),
        ];
    }

    /**
     * Las opciones de los desplegables.
     *
     * Los responsables van acotados a la organización a mano: `User` no lleva
     * `PerteneceAOrganizacion` —la autenticación tiene que poder encontrar a
     * alguien antes de saber de qué organización es—, así que aquí no hay scope
     * global ni RLS que tapen el cruce.
     *
     * Los indicadores sí lo llevan, así que basta con `activos()`: se ofrecen los
     * que se siguen midiendo, porque colgar un objetivo de un indicador retirado
     * sería evaluarlo con una cifra que ya no se toma.
     *
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'responsables' => User::query()
                ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                ->orderBy('name')
                ->get()
                ->map(static fn (User $usuario): array => [
                    'valor' => (string) $usuario->id,
                    'etiqueta' => $usuario->name,
                ])
                ->all(),
            'indicadoresDisponibles' => Indicador::query()
                ->activos()
                ->orderBy('codigo')
                ->get()
                ->map(static fn (Indicador $indicador): array => [
                    'valor' => (string) $indicador->id,
                    'etiqueta' => "{$indicador->codigo} · {$indicador->nombre}",
                ])
                ->all(),
        ];
    }

    private function puede(Permiso $permiso): bool
    {
        return request()->user()?->can($permiso->value) ?? false;
    }
}
