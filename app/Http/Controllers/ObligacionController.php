<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Continuidad\Enums\EstadoPrueba;
use App\Domain\Continuidad\Models\PruebaContinuidad;
use App\Domain\Documento\Models\Documento;
use App\Domain\Evidencia\Models\Evidencia;
use App\Domain\Obligacion\AsumirObligacion;
use App\Domain\Obligacion\Cadencia;
use App\Domain\Obligacion\CodigoCompromiso;
use App\Domain\Obligacion\Excepciones\CumplimientoInvalido;
use App\Domain\Obligacion\Models\Compromiso;
use App\Domain\Obligacion\Models\CompromisoCumplimiento;
use App\Domain\Obligacion\Models\Obligacion;
use App\Domain\Obligacion\ObligacionesAplicables;
use App\Domain\Obligacion\Referencia;
use App\Domain\Obligacion\RegistrarCompromiso;
use App\Domain\Obligacion\RegistrarCumplimiento;
use App\Domain\Obligacion\RegistroObligaciones;
use App\Domain\Obligacion\RetirarCompromiso;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Organizacion\Models\Organizacion;
use App\Domain\RevisionDireccion\Models\RevisionDireccion;
use App\Domain\Sistema\Models\Sistema;
use App\Http\Requests\GuardarCompromisoRequest;
use App\Http\Requests\RegistrarCumplimientoRequest;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Http\Resources\ObligacionRecurso;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El registro de obligaciones periódicas: § 4.16.
 *
 * **Dos superficies y no una.** La rejilla de `/calendario` enseña vencimientos
 * de siete fuentes; esto enseña una sola cosa —los compromisos— con su histórico.
 * Un conmutador entre las dos diría que son dos formas de ver el mismo dato, y no
 * lo son.
 *
 * Lo que este controlador **no hace** es generar compromisos solos:
 * `ObligacionesAplicables` propone y una persona acepta. Obligarse por una casilla
 * que nadie marcó es ponerle deberes a la organización en su nombre.
 */
class ObligacionController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request, ObligacionRecurso $recurso, RegistroObligaciones $registro, ObligacionesAplicables $aplicables): Response
    {
        return Inertia::render('obligaciones/Index', [
            ...$this->tabla($recurso, $request),
            'alertas' => $registro->alertas(),
            'pendientes' => $registro->pendientes(),
            'total' => $registro->total(),
            // Lo que el catálogo propone y todavía nadie ha asumido. Es lo que
            // evita que el registro se quede vacío para siempre.
            'sinAsumir' => $this->proponibles($aplicables),
            'puedeGestionar' => $this->puedeGestionar(),
        ]);
    }

    public function create(Request $request, CodigoCompromiso $codigos, ObligacionesAplicables $aplicables): Response
    {
        return Inertia::render('obligaciones/Formulario', [
            'compromiso' => null,
            'sugerencia' => [
                'codigo' => $codigos->siguiente(),
                'computa_desde' => Carbon::today()->toDateString(),
            ],
            'sinAsumir' => $this->proponibles($aplicables),
            ...$this->opcionesDeCompromiso(),
        ]);
    }

    public function store(GuardarCompromisoRequest $request, RegistrarCompromiso $registrar): RedirectResponse
    {
        $compromiso = $registrar($request->validated());

        Inertia::flash('exito', "Compromiso {$compromiso->codigo} registrado.");

        return to_route('obligaciones.show', $compromiso);
    }

    /**
     * Asume una obligación del catálogo.
     *
     * La cadencia y el título salen de la fila del catálogo y **se copian**: ver
     * `AsumirObligacion`. Lo que pide el formulario es lo único que la herramienta
     * no puede saber, que es desde cuándo corre el reloj.
     */
    public function asumir(Request $request, Obligacion $obligacion, AsumirObligacion $asumir): RedirectResponse
    {
        /*
         * Una obligación que el importador retiró **no se asume**. `ObligacionesAplicables`
         * ya filtra por `vigentes()`, pero eso sólo gobierna lo que la pantalla
         * propone: un POST directo a esta ruta la asumiría igual, y el importador
         * la marca precisamente porque dejó de exigirse.
         *
         * 404 y no 403, como el resto del producto: decir «existe pero no vale» ya
         * sería contar algo del catálogo.
         */
        abort_unless($obligacion->vigente, 404);

        $datos = $request->validate([
            'computa_desde' => ['nullable', 'date', 'before_or_equal:today'],
            'sistema_id' => ['nullable', 'integer', 'exists:sistemas,id'],
            'responsable_id' => ['nullable', 'integer', 'exists:users,id'],
            /*
             * La cadencia del catálogo es **la sugerida**, y quien asume puede ser
             * más estricto. `AsumirObligacion` lo admitía desde el principio y
             * nadie se lo pasaba, así que la frase de su docblock era falsa: o se
             * expone o se quita, y exponerlo es lo que hace útil la palabra
             * «sugerida» de la columna del catálogo.
             */
            'periodicidad_meses' => ['nullable', 'integer', 'between:'.Cadencia::MINIMO_MESES.','.Cadencia::MAXIMO_MESES],
        ]);

        $compromiso = $asumir(
            $obligacion,
            isset($datos['computa_desde']) ? Carbon::parse($datos['computa_desde']) : null,
            $datos['sistema_id'] ?? null,
            $datos['responsable_id'] ?? null,
            $datos['periodicidad_meses'] ?? null,
        );

        Inertia::flash('exito', "«{$compromiso->titulo}» asumida.");

        return to_route('obligaciones.show', $compromiso);
    }

    /**
     * Asume de una vez todas las que el catálogo propone.
     *
     * Sin esto el registro se queda vacío para siempre: nadie declara a mano seis
     * obligaciones que ya se sabe de memoria, y un registro vacío es lo mismo que
     * no tener el módulo. El reloj arranca hoy en todas, que es lo único
     * defendible sin preguntar una por una — quien sepa la fecha real la corrige
     * desde la ficha.
     */
    public function predefinidas(ObligacionesAplicables $aplicables, AsumirObligacion $asumir): RedirectResponse
    {
        $asumidas = 0;

        foreach ($aplicables->para($this->organizacion()) as $obligacion) {
            $asumir($obligacion);
            $asumidas++;
        }

        Inertia::flash('exito', $asumidas === 0
            ? 'No quedaba ninguna obligación del catálogo por asumir.'
            : "{$asumidas} obligaciones asumidas. Revisa desde cuándo corre el reloj en cada una.");

        return to_route('obligaciones.index');
    }

    public function show(Compromiso $compromiso): Response
    {
        $compromiso->load([
            'responsable',
            'obligacion.marco',
            'sistema',
            'cumplimientos.registradoPor',
            'cumplimientos.auditoria',
            'cumplimientos.revisionDireccion',
            'cumplimientos.documento',
            'cumplimientos.pruebaContinuidad',
            'cumplimientos.evidencia',
        ]);

        $proxima = $compromiso->proximaFecha();
        $dias = (int) Carbon::today()->diffInDays($proxima, false);

        return Inertia::render('obligaciones/Ficha', [
            'compromiso' => [
                'id' => $compromiso->id,
                'codigo' => $compromiso->codigo,
                'titulo' => $compromiso->titulo,
                'descripcion' => $compromiso->descripcion,
                'notas' => $compromiso->notas,
                'motivoRetirada' => $compromiso->motivo_retirada,
                'cadencia' => $compromiso->cadencia()->etiqueta(),
                'periodicidadMeses' => $compromiso->periodicidad_meses,
                'computaDesde' => $compromiso->computa_desde->toDateString(),
                'responsable' => $compromiso->responsable?->name,
                'sistema' => $compromiso->sistema?->nombre,
                'activo' => $compromiso->activo,
                'proximaFecha' => $proxima->toDateString(),
                'proximaEscrita' => $proxima->format('d/m/Y'),
                'dias' => $dias,
                'vencido' => $dias < 0,
                // De dónde sale y qué la exige, que es lo que un auditor pregunta
                // cuando ve una obligación en una lista.
                'origen' => $compromiso->obligacion === null ? null : [
                    'codigo' => $compromiso->obligacion->codigo,
                    'nombre' => $compromiso->obligacion->nombre,
                    'baseLegal' => $compromiso->obligacion->base_legal,
                    'marco' => $compromiso->obligacion->marco?->nombre,
                ],
            ],
            'cumplimientos' => $compromiso->cumplimientos->map(fn (CompromisoCumplimiento $cumplimiento): array => [
                'id' => $cumplimiento->id,
                // Las dos fechas, y no una: `fecha` es cuándo se cumplió y
                // `created_at` cuándo se apuntó. Enseñar sólo una las confunde.
                'fecha' => $cumplimiento->fecha->toDateString(),
                'fechaEscrita' => $cumplimiento->fecha->format('d/m/Y'),
                'cubreHasta' => $cumplimiento->cubre_hasta->format('d/m/Y'),
                'registradoEn' => $cumplimiento->created_at->format('d/m/Y H:i'),
                'registradoPor' => $cumplimiento->registradoPor?->name,
                'nota' => $cumplimiento->nota,
                'referencia' => Referencia::de($cumplimiento),
                'evidencia' => $cumplimiento->evidencia === null ? null : [
                    'id' => $cumplimiento->evidencia->id,
                    'titulo' => $cumplimiento->evidencia->titulo,
                ],
            ])->all(),
            'puedeGestionar' => $this->puedeGestionar(),
            ...$this->opcionesDeCumplimiento(),
        ]);
    }

    public function edit(Compromiso $compromiso): Response
    {
        return Inertia::render('obligaciones/Formulario', [
            'compromiso' => [
                'id' => $compromiso->id,
                'codigo' => $compromiso->codigo,
                'titulo' => $compromiso->titulo,
                'descripcion' => $compromiso->descripcion,
                'periodicidad_meses' => $compromiso->periodicidad_meses,
                'computa_desde' => $compromiso->computa_desde->toDateString(),
                'sistema_id' => $compromiso->sistema_id,
                'responsable_id' => $compromiso->responsable_id,
                'notas' => $compromiso->notas,
            ],
            'sugerencia' => null,
            'sinAsumir' => [],
            ...$this->opcionesDeCompromiso(),
        ]);
    }

    public function update(GuardarCompromisoRequest $request, Compromiso $compromiso): RedirectResponse
    {
        $compromiso->update($request->validated());

        Inertia::flash('exito', 'Compromiso actualizado.');

        return to_route('obligaciones.show', $compromiso);
    }

    public function retirar(Request $request, Compromiso $compromiso, RetirarCompromiso $retirar): RedirectResponse
    {
        $datos = $request->validate(['motivo' => ['nullable', 'string', 'max:2000']]);

        $retirar($compromiso, $datos['motivo'] ?? null);

        Inertia::flash('exito', 'Compromiso retirado. Su histórico de cumplimiento se conserva.');

        return to_route('obligaciones.show', $compromiso);
    }

    public function destroy(Compromiso $compromiso): RedirectResponse
    {
        $compromiso->delete();

        Inertia::flash('exito', 'Compromiso eliminado.');

        return to_route('obligaciones.index');
    }

    public function cumplir(RegistrarCumplimientoRequest $request, Compromiso $compromiso, RegistrarCumplimiento $registrar): RedirectResponse
    {
        $datos = $request->validated();

        try {
            $registrar(
                $compromiso,
                Carbon::parse($datos['fecha']),
                array_intersect_key($datos, array_flip(['auditoria_id', 'revision_direccion_id', 'documento_id', 'prueba_continuidad_id', 'evidencia_id', 'nota'])),
                $request->user(),
                isset($datos['cubre_hasta']) ? Carbon::parse($datos['cubre_hasta']) : null,
            );
        } catch (CumplimientoInvalido $e) {
            return back()->withErrors(['fecha' => $e->getMessage()]);
        }

        Inertia::flash('exito', 'Cumplimiento registrado.');

        return to_route('obligaciones.show', $compromiso);
    }

    public function borrarCumplimiento(Compromiso $compromiso, CompromisoCumplimiento $cumplimiento): RedirectResponse
    {
        $cumplimiento->delete();

        Inertia::flash('exito', 'Cumplimiento borrado. La próxima fecha vuelve a la que había antes.');

        return to_route('obligaciones.show', $compromiso);
    }

    /**
     * Lo que el catálogo propone y nadie ha asumido.
     *
     * @return list<array<string, mixed>>
     */
    private function proponibles(ObligacionesAplicables $aplicables): array
    {
        return $aplicables->para($this->organizacion())
            ->map(fn (Obligacion $obligacion): array => [
                'id' => $obligacion->id,
                'codigo' => $obligacion->codigo,
                'nombre' => $obligacion->nombre,
                'descripcion' => $obligacion->descripcion,
                'baseLegal' => $obligacion->base_legal,
                'marco' => $obligacion->marco?->codigo,
                'cadencia' => $obligacion->cadenciaSugerida()->etiqueta(),
            ])
            ->all();
    }

    private function organizacion(): Organizacion
    {
        return Organizacion::query()->findOrFail(app(ContextoOrganizacion::class)->idObligatorio());
    }

    private function puedeGestionar(): bool
    {
        return request()->user()?->can(Permiso::ObligacionesGestionar->value) ?? false;
    }

    /**
     * Lo que necesita el formulario del compromiso, y nada más.
     *
     * **Estaba partido en un solo método que devolvía las seis listas**, y las tres
     * pantallas recibían las seis. Costaba dos cosas: cuatro consultas por carga
     * que nadie usaba, y un aviso de Vue en cada una —`AppLayout` tiene raíz de
     * fragmento, así que los props que el componente no declara caen en `$attrs` y
     * no se pueden heredar—.
     *
     * @return array<string, mixed>
     */
    private function opcionesDeCompromiso(): array
    {
        return [
            'sistemas' => Sistema::query()
                ->orderBy('nombre')
                ->get()
                ->map(fn (Sistema $sistema): array => ['valor' => $sistema->id, 'etiqueta' => $sistema->nombre])
                ->all(),

            /*
             * Acotado a mano: `User` no lleva `PerteneceAOrganizacion`, así que
             * sin este `where` el desplegable listaría a los usuarios de todos los
             * clientes. Lo comprueba `ConsultasDeUsuarioAcotadasTest`.
             */
            'responsables' => User::query()
                ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                ->orderBy('name')
                ->get()
                ->map(fn (User $usuario): array => ['valor' => $usuario->id, 'etiqueta' => $usuario->name])
                ->all(),
        ];
    }

    /**
     * Con qué se puede demostrar un cumplimiento: sólo lo pide la ficha.
     *
     * Los cuatro registros son excluyentes entre sí —lo impone la base— y la
     * evidencia va aparte porque es otra cosa: es la prueba, y convive con el
     * registro que la originó.
     *
     * **Las cinco llevan tope.** `Documento` no lo llevaba y se traía el registro
     * documental entero para un desplegable.
     *
     * @return array<string, mixed>
     */
    private function opcionesDeCumplimiento(): array
    {
        return [
            'auditorias' => Auditoria::query()
                ->orderByDesc('fecha')
                ->limit(50)
                ->get()
                ->map(fn (Auditoria $auditoria): array => [
                    'valor' => $auditoria->id,
                    'etiqueta' => "{$auditoria->codigo} — {$auditoria->tipo->etiqueta()} · {$auditoria->fecha->format('d/m/Y')}",
                ])
                ->all(),

            'revisiones' => RevisionDireccion::query()
                ->orderByDesc('fecha')
                ->limit(50)
                ->get()
                ->map(fn (RevisionDireccion $revision): array => [
                    'valor' => $revision->id,
                    'etiqueta' => "{$revision->codigo} — {$revision->fecha->format('d/m/Y')}",
                ])
                ->all(),

            'documentos' => Documento::query()
                ->orderBy('codigo')
                ->limit(100)
                ->get()
                ->map(fn (Documento $documento): array => ['valor' => $documento->id, 'etiqueta' => "{$documento->codigo} — {$documento->titulo}"])
                ->all(),

            /*
             * Sólo las realizadas: una prueba planificada o cancelada todavía no
             * demuestra nada, y citarla como referencia de un cumplimiento sería
             * dar por hecho un resultado que no existe.
             */
            'pruebasContinuidad' => PruebaContinuidad::query()
                ->where('estado', EstadoPrueba::Realizada)
                ->orderByDesc('fecha_realizacion')
                ->limit(50)
                ->get()
                ->map(fn (PruebaContinuidad $prueba): array => ['valor' => $prueba->id, 'etiqueta' => "{$prueba->codigo} — {$prueba->titulo}"])
                ->all(),

            'evidencias' => Evidencia::query()
                ->orderByDesc('created_at')
                ->limit(100)
                ->get()
                ->map(fn (Evidencia $evidencia): array => ['valor' => $evidencia->id, 'etiqueta' => $evidencia->titulo])
                ->all(),
        ];
    }
}
