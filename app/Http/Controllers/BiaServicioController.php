<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Activo\GrafoActivos;
use App\Domain\Activo\Models\Activo;
use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Continuidad\CambiarEstadoBia;
use App\Domain\Continuidad\EditarBia;
use App\Domain\Continuidad\Enums\EstadoBia;
use App\Domain\Continuidad\Enums\NivelImpacto;
use App\Domain\Continuidad\Enums\TramoImpacto;
use App\Domain\Continuidad\Excepciones\ServicioNoValido;
use App\Domain\Continuidad\Excepciones\TransicionDeBiaNoPermitida;
use App\Domain\Continuidad\Models\BiaServicio;
use App\Domain\Continuidad\Models\BiaServicioTransicion;
use App\Domain\Continuidad\RegistrarBia;
use App\Domain\Continuidad\UmbralTolerable;
use App\Domain\Documento\Models\Documento;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Http\Requests\CambiarEstadoBiaRequest;
use App\Http\Requests\GuardarBiaRequest;
use App\Http\Resources\BiaServicioRecurso;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Http\Resources\Panel\Indicador;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El análisis de impacto en el negocio de un servicio: § 4.11.
 *
 * **Sobre `IncidenteController`**, con el mismo reparto de rutas: lectura tras
 * `continuidad.ver`, escritura tras `continuidad.gestionar` y segundo factor.
 * La diferencia está en `transicion()`: pasar a `aprobado` exige además
 * `continuidad.aprobar`, y aquí es donde se comprueba — aceptar un RTO es
 * aceptar un riesgo, y el técnico no lo hace.
 *
 * **Sin `destroy()`.** Un BIA no se borra: un servicio que deja de existir se
 * declara `obsoleto`, con su motivo en el histórico. Borrarlo perdería
 * justamente lo que el invariante 7 exige conservar.
 */
class BiaServicioController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request, BiaServicioRecurso $recurso): Response
    {
        return Inertia::render('continuidad/bia/Index', [
            ...$this->tabla($recurso, $request),
            'alertas' => $this->alertas(),
            'pendientes' => $this->pendientes(),
            'total' => BiaServicio::query()->count(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('continuidad/bia/Formulario', [
            'bia' => null,
            ...$this->opciones(),
        ]);
    }

    public function store(GuardarBiaRequest $request, RegistrarBia $registrar): RedirectResponse
    {
        try {
            $bia = $registrar($request->validated(), $request->user());
        } catch (ServicioNoValido $error) {
            return back()->withErrors(['activo_id' => $error->getMessage()]);
        }

        Inertia::flash('exito', 'BIA registrado.');

        return to_route('continuidad.bia.show', $bia);
    }

    public function show(BiaServicio $bia): Response
    {
        $bia->load(['activo', 'responsable', 'aprobadoPor', 'transiciones.usuario']);

        $umbral = UmbralTolerable::de($bia);

        return Inertia::render('continuidad/bia/Ficha', [
            'bia' => $this->serializar($bia),
            'umbral' => [
                'tramo' => $umbral?->value,
                'etiqueta' => $umbral?->etiqueta(),
                'horas' => UmbralTolerable::horas($bia),
            ],
            'tramos' => array_map(
                fn (TramoImpacto $tramo): array => [
                    'clave' => $tramo->value,
                    'etiqueta' => $tramo->etiqueta(),
                    'horas' => $tramo->horas(),
                    'nivel' => $bia->{$tramo->value}->value,
                    'nivelEtiqueta' => $bia->{$tramo->value}->etiqueta(),
                    'nivelTono' => $bia->{$tramo->value}->tono(),
                    'nivelIcono' => $bia->{$tramo->value}->icono(),
                    'esUmbral' => $umbral === $tramo,
                ],
                TramoImpacto::cases(),
            ),
            'dependencias' => app(GrafoActivos::class)->dependenciasDe($bia->activo)
                ->map(static fn (Activo $activo): array => [
                    'id' => $activo->id,
                    'codigo' => $activo->codigo,
                    'nombre' => $activo->nombre,
                    'tipo' => $activo->tipo->etiqueta(),
                    'tipoTono' => 'tipo:'.$activo->tipo->value,
                    'tipoIcono' => $activo->tipo->icono(),
                    'profundidad' => (int) $activo->getAttribute('profundidad'),
                ])
                ->values()
                ->all(),
            /*
             * Los planes de continuidad que cubren este servicio, con id,
             * código, título y si su versión aprobada existe: es lo único que
             * necesita la tarjeta de la ficha, y lo que `Documento` ofrece sin
             * reabrir la consulta larga de `DocumentoRecurso`.
             */
            'planes' => $bia->planes()->with('versionAprobada')->get()
                ->map(fn (Documento $plan): array => [
                    'id' => $plan->id,
                    'codigo' => $plan->codigo,
                    'titulo' => $plan->titulo,
                    'aprobado' => $plan->versionAprobada !== null,
                ])
                ->all(),
            // Pruebas: llega con la tarea 5. Vacío aquí y no ausente, para
            // que la ficha ya sepa pintar su estado vacío antes de que
            // exista el dato.
            'pruebas' => [],
            'transiciones' => array_map(
                fn (EstadoBia $destino): array => [
                    'valor' => $destino->value,
                    'etiqueta' => $destino->etiqueta(),
                    'tono' => $destino->tono(),
                    'icono' => $destino->icono(),
                    'exigeMotivo' => $this->exigeMotivo($bia->estado, $destino),
                    'permiso' => $destino === EstadoBia::Aprobado
                        ? Permiso::ContinuidadAprobar->value
                        : Permiso::ContinuidadGestionar->value,
                ],
                $bia->estado->transicionesPermitidas(),
            ),
            'historial' => $bia->transiciones
                ->map(static fn (BiaServicioTransicion $transicion): array => [
                    'id' => $transicion->id,
                    'anterior' => $transicion->estado_anterior?->etiqueta(),
                    'nuevo' => $transicion->estado_nuevo->etiqueta(),
                    'tono' => $transicion->estado_nuevo->tono(),
                    'icono' => $transicion->estado_nuevo->icono(),
                    'usuario' => $transicion->usuario?->name,
                    'fecha' => $transicion->created_at->toIso8601String(),
                    'nota' => $transicion->nota,
                ])
                ->values()
                ->all(),
            'puedeGestionar' => $this->puede(Permiso::ContinuidadGestionar),
            'puedeAprobar' => $this->puede(Permiso::ContinuidadAprobar),
        ]);
    }

    public function edit(BiaServicio $bia): Response
    {
        return Inertia::render('continuidad/bia/Formulario', [
            'bia' => $this->serializar($bia),
            ...$this->opciones(),
        ]);
    }

    public function update(GuardarBiaRequest $request, BiaServicio $bia, EditarBia $editar): RedirectResponse
    {
        $editar($bia, Arr::only($request->validated(), self::CAMPOS_EDITABLES), $request->user());

        Inertia::flash('exito', 'BIA actualizado.');

        return to_route('continuidad.bia.show', $bia);
    }

    public function transicion(
        CambiarEstadoBiaRequest $request,
        BiaServicio $bia,
        CambiarEstadoBia $cambiar,
    ): RedirectResponse {
        $destino = EstadoBia::from((string) $request->validated('estado'));

        // Aceptar un RTO es aceptar un riesgo: el guardián vive aquí y no
        // sólo en la interfaz, que se limita a ocultar el botón.
        if ($destino === EstadoBia::Aprobado) {
            abort_unless($request->user()?->can(Permiso::ContinuidadAprobar->value), 403);
        }

        try {
            $cambiar($bia, $destino, $request->user(), $request->string('nota')->value() ?: null);
        } catch (TransicionDeBiaNoPermitida $error) {
            return back()->withErrors(['estado' => $error->getMessage()]);
        }

        Inertia::flash('exito', 'BIA actualizado.');

        return back();
    }

    /**
     * Los únicos campos que una edición manda a `EditarBia`. Repetidos aquí y
     * no leídos de la acción del dominio: es una constante privada de ella y
     * no tiene por qué exponerse, así que este controlador defiende su propio
     * borde con su propia lista — que, si diverge, `EditarBia` la rechaza con
     * `InvalidArgumentException` y lo cazaría el primer test de edición.
     *
     * @var list<string>
     */
    private const CAMPOS_EDITABLES = [
        'impacto_4h',
        'impacto_1d',
        'impacto_3d',
        'impacto_1s',
        'impacto_1m',
        'rto_horas',
        'rpo_horas',
        'justificacion',
        'responsable_id',
    ];

    /**
     * Si el paso deshace algo que alguien ya había dado por hecho, que es lo
     * que exige motivo escrito. La regla vive en `CambiarEstadoBia`; esto es
     * sólo lo que el cliente necesita para abrir el cuadro de la nota antes de
     * enviar.
     */
    private function exigeMotivo(EstadoBia $actual, EstadoBia $destino): bool
    {
        return $destino === EstadoBia::Obsoleto
            || ($actual === EstadoBia::Aprobado && $destino === EstadoBia::Borrador);
    }

    /**
     * Lo que va mal de verdad, y lo que está a medias.
     *
     * Mismo criterio que `RegistroIncidentes`: cada indicador cuenta con el
     * mismo scope que usa su filtro de la tabla, así que pulsar la cifra
     * enseña exactamente esa cifra.
     *
     * @return list<Indicador>
     */
    private function alertas(): array
    {
        return [
            /*
             * `en_progreso` (ámbar) y no `caducada` (rojo), a propósito y por
             * el mismo motivo que `TramosImpacto.vue`: un RTO incoherente con
             * el umbral tolerable es una contradicción que corregir, no un
             * plazo ya incumplido — nadie ha dejado de cumplir nada todavía.
             * El rojo del dominio se reserva a lo vencido o lo incumplido
             * (DESIGN.md §3), y eso es exactamente `revision_vencida`, aquí
             * abajo.
             */
            $this->indicador(
                'rto_incoherente',
                'Con un RTO por encima del umbral tolerable',
                'rtoIncoherente',
                'en_progreso',
                'El RTO declarado promete más de lo que el propio BIA tolera.',
            ),
            $this->indicador(
                'revision_vencida',
                'Con la revisión vencida',
                'revisionVencida',
                'caducada',
                'El BIA lleva más de doce meses sin volver a aprobarse.',
            ),
        ];
    }

    /**
     * @return list<Indicador>
     */
    private function pendientes(): array
    {
        return [
            new Indicador(
                clave: 'borrador',
                etiqueta: 'En borrador',
                valor: BiaServicio::query()->where('estado', EstadoBia::Borrador->value)->count(),
                tono: 'no_iniciado',
                filtro: 'filter[estado]=borrador',
                base: '/continuidad/bia',
                ayuda: 'Todavía sin aprobar: el RTO que declara no cuenta como vigente.',
            ),
        ];
    }

    private function indicador(string $clave, string $etiqueta, string $scope, string $tono, ?string $ayuda = null): Indicador
    {
        return new Indicador(
            clave: $clave,
            etiqueta: $etiqueta,
            valor: BiaServicio::query()->{$scope}()->count(),
            tono: $tono,
            filtro: "filter[{$clave}]=1",
            base: '/continuidad/bia',
            ayuda: $ayuda,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(BiaServicio $bia): array
    {
        return [
            'id' => $bia->id,
            'activo_id' => $bia->activo_id,
            'servicio' => $bia->activo?->nombre,
            'servicioCodigo' => $bia->activo?->codigo,
            'impacto_4h' => $bia->impacto_4h->value,
            'impacto_1d' => $bia->impacto_1d->value,
            'impacto_3d' => $bia->impacto_3d->value,
            'impacto_1s' => $bia->impacto_1s->value,
            'impacto_1m' => $bia->impacto_1m->value,
            'rto_horas' => $bia->rto_horas,
            'rpo_horas' => $bia->rpo_horas,
            'justificacion' => $bia->justificacion,
            'estado' => $bia->estado->value,
            'estadoEtiqueta' => $bia->estado->etiqueta(),
            'estadoTono' => $bia->estado->tono(),
            'estadoIcono' => $bia->estado->icono(),
            'responsable_id' => $bia->responsable_id,
            'responsable' => $bia->responsable?->name,
            'aprobadoPor' => $bia->aprobadoPor?->name,
            'fechaAprobacion' => $bia->fecha_aprobacion?->format('d/m/Y'),
            'fechaRevision' => $bia->fecha_revision?->format('d/m/Y'),
            'rtoIncoherente' => $bia->rtoIncoherente(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'servicios' => Activo::query()
                ->where('tipo', TipoActivo::Servicios->value)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre'])
                ->map(static fn (Activo $activo): array => [
                    'valor' => (string) $activo->id,
                    'etiqueta' => "{$activo->codigo} · {$activo->nombre}",
                ])
                ->all(),
            'nivelesImpacto' => array_map(
                static fn (NivelImpacto $nivel): array => [
                    'valor' => $nivel->value,
                    'etiqueta' => $nivel->etiqueta(),
                ],
                NivelImpacto::cases(),
            ),
            // Acotado a la organización a mano: `User` no lleva el scope.
            'responsables' => User::query()
                ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                ->orderBy('name')
                ->get()
                ->map(static fn (User $usuario): array => [
                    'valor' => (string) $usuario->id,
                    'etiqueta' => $usuario->name,
                ])
                ->all(),
        ];
    }

    private function puede(Permiso $permiso): bool
    {
        return request()->user()?->can($permiso->value) ?? false;
    }
}
