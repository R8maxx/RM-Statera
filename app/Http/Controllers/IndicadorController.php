<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Catalogo\Models\Marco;
use App\Domain\Metrica\CodigoIndicador;
use App\Domain\Metrica\Enums\CalculoIndicador;
use App\Domain\Metrica\Enums\OrigenMedicion;
use App\Domain\Metrica\Enums\Periodicidad;
use App\Domain\Metrica\Enums\SentidoIndicador;
use App\Domain\Metrica\Enums\UnidadIndicador;
use App\Domain\Metrica\Models\Indicador;
use App\Domain\Metrica\Models\Medicion;
use App\Domain\Metrica\RegistrarIndicador;
use App\Domain\Metrica\RegistrarMedicion;
use App\Domain\Metrica\RegistroIndicadores;
use App\Domain\Metrica\SerieIndicador;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Http\Requests\GuardarIndicadorRequest;
use App\Http\Requests\RegistrarMedicionRequest;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Http\Resources\IndicadorRecurso;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El cuadro de indicadores: § 4.14 y cláusula 9.1.
 *
 * Dos caminos para que entre una cifra, y no son intercambiables. **«Medir
 * ahora»** cierra el periodo de un indicador calculado y sella lo que Statera
 * cuenta hoy; es el mismo trabajo que hace el comando de las siete y media,
 * disponible a mano para el día que alguien quiera cerrar antes o comprobar qué
 * saldría. **El formulario de medición** es para lo que no está en esta base de
 * datos, que es la mitad de lo que la 9.1 pide mientras el § 4.8 no exista.
 *
 * Ninguno de los dos recalcula nada al mirarlo: una serie que se recalcula
 * reescribe el pasado.
 */
class IndicadorController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request, IndicadorRecurso $recurso, RegistroIndicadores $registro): Response
    {
        return Inertia::render('indicadores/Index', [
            ...$this->tabla($recurso, $request),
            'alertas' => $registro->alertas(),
            'pendientes' => $registro->pendientes(),
            'total' => $registro->total(),
        ]);
    }

    public function create(CodigoIndicador $codigos): Response
    {
        return Inertia::render('indicadores/Formulario', [
            'indicador' => null,
            'sugerencia' => ['codigo' => $codigos->siguiente()],
            ...$this->opciones(),
        ]);
    }

    public function store(GuardarIndicadorRequest $request, RegistrarIndicador $registrar): RedirectResponse
    {
        $indicador = $registrar($request->validated());

        Inertia::flash('exito', "Indicador {$indicador->codigo} declarado.");

        return to_route('indicadores.show', $indicador);
    }

    public function show(Request $request, Indicador $indicador, SerieIndicador $serie): Response
    {
        $indicador->load(['responsable', 'marco', 'ultimaMedicion']);

        $cumplimiento = $indicador->cumplimiento();

        return Inertia::render('indicadores/Ficha', [
            'indicador' => [
                'id' => $indicador->id,
                'codigo' => $indicador->codigo,
                'nombre' => $indicador->nombre,
                'descripcion' => $indicador->descripcion,
                'origen' => $indicador->origen->value,
                'origenEtiqueta' => $indicador->origen->etiqueta(),
                'origenIcono' => $indicador->origen->icono(),
                /*
                 * El método va impreso en la ficha y no escondido en una ayuda:
                 * «¿de dónde sale ese 87 %?» es la primera pregunta de cualquier
                 * auditor, y la 9.1 b) la hace por escrito.
                 */
                'metodo' => $indicador->calculo?->metodo() ?? $indicador->formula_o_fuente,
                'calculo' => $indicador->calculo?->value,
                'calculoEtiqueta' => $indicador->calculo?->etiqueta(),
                'marco' => $indicador->marco?->nombre,
                'unidad' => $indicador->unidad->value,
                'unidadEtiqueta' => $indicador->unidad->etiqueta(),
                'periodicidad' => $indicador->periodicidad->value,
                'periodicidadEtiqueta' => $indicador->periodicidad->etiqueta(),
                'sentidoEtiqueta' => $indicador->sentido->etiqueta(),
                'objetivo' => $indicador->objetivo === null ? null : (float) $indicador->objetivo,
                'objetivoEscrito' => $indicador->objetivo === null
                    ? null
                    : $indicador->sentido->comparador().' '.$indicador->unidad->escribir((float) $indicador->objetivo),
                'responsable' => $indicador->responsable?->name,
                'activo' => $indicador->activo,
                'cumplimiento' => $cumplimiento->value,
                'cumplimientoEtiqueta' => $cumplimiento->etiqueta(),
                'cumplimientoTono' => $cumplimiento->tono(),
                'cumplimientoIcono' => $cumplimiento->icono(),
                'periodoSinMedir' => $indicador->tienePeriodoSinMedir(),
                'periodoACerrar' => $indicador->periodicidad->etiquetaDe($indicador->periodoACerrar()[0]),
                'esCalculado' => $indicador->origen === OrigenMedicion::Calculado,
            ],
            'serie' => $serie->de($indicador),
            'mediciones' => $indicador->mediciones()->with('registradaPor')->limit(24)->get()
                ->map(fn ($medicion): array => [
                    'id' => $medicion->id,
                    'periodo' => $indicador->periodicidad->etiquetaDe($medicion->periodo_inicio),
                    'valor' => $indicador->unidad->escribir((float) $medicion->valor),
                    'fraccion' => $medicion->fraccion(),
                    'objetivo' => $medicion->objetivo === null
                        ? null
                        : $indicador->sentido->comparador().' '.$indicador->unidad->escribir((float) $medicion->objetivo),
                    'origen' => $medicion->origen->etiqueta(),
                    'medidaEn' => $medicion->medida_en->format('d/m/Y'),
                    'nota' => $medicion->nota,
                    'registradaPor' => $medicion->registradaPor?->name,
                ])->all(),
            'puedeGestionar' => $request->user()?->can(Permiso::IndicadoresGestionar->value) ?? false,
        ]);
    }

    public function edit(Indicador $indicador): Response
    {
        return Inertia::render('indicadores/Formulario', [
            'indicador' => [
                'id' => $indicador->id,
                'codigo' => $indicador->codigo,
                'nombre' => $indicador->nombre,
                'descripcion' => $indicador->descripcion,
                'origen' => $indicador->origen->value,
                'calculo' => $indicador->calculo?->value,
                'formula_o_fuente' => $indicador->formula_o_fuente,
                'marco_id' => $indicador->marco_id,
                'unidad' => $indicador->unidad->value,
                'sentido' => $indicador->sentido->value,
                'periodicidad' => $indicador->periodicidad->value,
                'objetivo' => $indicador->objetivo === null ? null : (float) $indicador->objetivo,
                'responsable_id' => $indicador->responsable_id,
                'activo' => $indicador->activo,
            ],
            'sugerencia' => null,
            ...$this->opciones(),
        ]);
    }

    public function update(GuardarIndicadorRequest $request, Indicador $indicador, RegistrarIndicador $registrar): RedirectResponse
    {
        $registrar($request->validated(), $indicador);

        Inertia::flash('exito', 'Indicador actualizado.');

        return to_route('indicadores.show', $indicador);
    }

    public function destroy(Indicador $indicador): RedirectResponse
    {
        $codigo = $indicador->codigo;
        $indicador->delete();

        Inertia::flash('exito', "Indicador {$codigo} eliminado.");

        return to_route('indicadores.index');
    }

    /**
     * Cierra el periodo de un indicador calculado.
     *
     * Mide **el periodo cerrado**, no el que está en curso, igual que el comando
     * programado: es el mismo trabajo, disponible a mano.
     */
    public function medir(Request $request, Indicador $indicador, RegistrarMedicion $registrar): RedirectResponse
    {
        if ($indicador->origen !== OrigenMedicion::Calculado) {
            Inertia::flash('aviso', 'Ese indicador se registra a mano: su cifra no sale de esta base de datos.');

            return back();
        }

        [$inicio, $fin] = $indicador->periodoACerrar();
        $medicion = $registrar->calculada($indicador, $inicio, $fin, $request->user());

        Inertia::flash('exito', sprintf(
            '%s: %s.',
            $indicador->periodicidad->etiquetaDe($inicio),
            $indicador->unidad->escribir((float) $medicion->valor),
        ));

        return back();
    }

    /** Sella a mano la cifra de un periodo. */
    public function registrarMedicion(RegistrarMedicionRequest $request, Indicador $indicador, RegistrarMedicion $registrar): RedirectResponse
    {
        $datos = $request->validated();
        [$inicio, $fin] = $indicador->periodicidad->periodoDe($request->date('fecha'));

        $registrar->manual($indicador, $inicio, $fin, [
            'valor' => (float) $datos['valor'],
            'numerador' => $datos['numerador'] ?? null,
            'denominador' => $datos['denominador'] ?? null,
            'medida_en' => $request->date('medida_en') ?? $fin,
            'nota' => $datos['nota'] ?? null,
        ], $request->user());

        Inertia::flash('exito', $indicador->periodicidad->etiquetaDe($inicio).' registrado.');

        return back();
    }

    /**
     * Borra una medición.
     *
     * Existe porque sellar el periodo equivocado es un error que hay que poder
     * deshacer, y porque la fila no la firma nadie: si la firmara, el camino
     * sería corregirla y dejar traza, no borrarla. La traza queda igual.
     */
    public function eliminarMedicion(Indicador $indicador, Medicion $medicion): RedirectResponse
    {
        $etiqueta = $indicador->periodicidad->etiquetaDe($medicion->periodo_inicio);
        $medicion->delete();

        Inertia::flash('exito', "Se ha borrado la medición de {$etiqueta}.");

        return back();
    }

    /**
     * Lo que el formulario necesita para pintarse.
     *
     * Los cálculos viajan con su unidad y su sentido naturales para que el
     * formulario los **proponga** al elegir uno. Propone y no impone, como
     * `TipoParteInteresada::ambitoSugerido()`: medir las tareas vencidas como
     * porcentaje del total abierto es legítimo.
     *
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'calculos' => array_map(static fn (CalculoIndicador $caso): array => [
                'valor' => $caso->value,
                'etiqueta' => $caso->etiqueta(),
                'metodo' => $caso->metodo(),
                'unidad' => $caso->unidad()->value,
                'sentido' => $caso->sentido()->value,
                'admiteMarco' => $caso->admiteMarco(),
            ], CalculoIndicador::cases()),

            'origenes' => $this->etiquetados(OrigenMedicion::cases()),
            'unidades' => $this->etiquetados(UnidadIndicador::cases()),
            'sentidos' => $this->etiquetados(SentidoIndicador::cases()),
            'periodicidades' => $this->etiquetados(Periodicidad::cases()),

            'marcos' => Marco::query()
                ->orderBy('nombre')
                ->get()
                ->map(fn (Marco $marco): array => ['valor' => $marco->id, 'etiqueta' => $marco->nombre])
                ->all(),

            /*
             * Acotado a mano: `User` no lleva `PerteneceAOrganizacion`, así que
             * sin este `where` el desplegable lista a los usuarios de todos los
             * clientes y no lo caza ningún test de aislamiento.
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
     * @param  array<int, OrigenMedicion|UnidadIndicador|SentidoIndicador|Periodicidad>  $casos
     * @return list<array{valor: string, etiqueta: string}>
     */
    private function etiquetados(array $casos): array
    {
        return array_map(
            static fn (OrigenMedicion|UnidadIndicador|SentidoIndicador|Periodicidad $caso): array => [
                'valor' => $caso->value,
                'etiqueta' => $caso->etiqueta(),
            ],
            $casos,
        );
    }
}
