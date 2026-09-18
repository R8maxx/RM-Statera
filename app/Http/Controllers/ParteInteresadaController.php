<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Contexto\CodigoContexto;
use App\Domain\Contexto\Enums\Ambito;
use App\Domain\Contexto\Enums\NaturalezaRequisito;
use App\Domain\Contexto\Enums\TipoParteInteresada;
use App\Domain\Contexto\GuardarRequisitosInteresado;
use App\Domain\Contexto\Models\ParteInteresada;
use App\Domain\Contexto\Models\RequisitoInteresado;
use App\Domain\Contexto\RegistrarParteInteresada;
use App\Domain\Contexto\RegistroContexto;
use App\Domain\Contexto\RetirarDelAnalisis;
use App\Domain\Contexto\VincularImplantacionARequisito;
use App\Domain\Implantacion\Models\Implantacion;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Http\Requests\GuardarParteInteresadaRequest;
use App\Http\Requests\GuardarRequisitosInteresadoRequest;
use App\Http\Requests\RetirarDelContextoRequest;
use App\Http\Requests\VincularEnContextoRequest;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Http\Resources\ParteInteresadaRecurso;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Las partes interesadas y lo que exigen: la cláusula 4.2.
 *
 * **Los requisitos se guardan enteros en una sola ruta**, como la lista de
 * comprobación de una tarea: lo que se está escribiendo es «qué nos pide este
 * regulador», y eso se piensa de una vez mirando la lista.
 *
 * Y de aquí sale la costura que paga el módulo: atar un requisito legal a la
 * implantación que lo cubre es lo que permite que la Declaración de Aplicabilidad
 * imprima «exigido por el regulador X» como justificación de inclusión, al lado de
 * «Anexo A» y de «tratamiento del riesgo R-014».
 */
class ParteInteresadaController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request, ParteInteresadaRecurso $recurso, RegistroContexto $registro): Response
    {
        return Inertia::render('partes-interesadas/Index', [
            ...$this->tabla($recurso, $request),
            'alertas' => $registro->alertasPartes(),
            'pendientes' => $registro->pendientesPartes(),
            'total' => $registro->totalPartes(),
        ]);
    }

    public function create(CodigoContexto $codigos): Response
    {
        return Inertia::render('partes-interesadas/Formulario', [
            'parte' => null,
            'sugerencia' => ['codigo' => $codigos->siguienteParte()],
            ...$this->opciones(),
        ]);
    }

    public function store(GuardarParteInteresadaRequest $request, RegistrarParteInteresada $registrar): RedirectResponse
    {
        $parte = $registrar($request->validated(), $request->user());

        Inertia::flash('exito', "Parte interesada {$parte->codigo} registrada. Ahora toca escribir qué os exige.");

        return to_route('partes-interesadas.show', $parte);
    }

    public function show(ParteInteresada $parte): Response
    {
        $parte->load([
            'responsable',
            'analisisAlta',
            'analisisBaja',
            'requisitos.implantaciones.requisito',
            'requisitos.implantaciones.sistema',
        ]);

        return Inertia::render('partes-interesadas/Ficha', [
            'parte' => [
                'id' => $parte->id,
                'codigo' => $parte->codigo,
                'nombre' => $parte->nombre,
                'tipo' => $parte->tipo->etiqueta(),
                'ambito' => $parte->ambito->etiqueta(),
                'descripcion' => $parte->descripcion,
                'responsable' => $parte->responsable?->name,
                'vigente' => $parte->estaVigente(),
                'motivoBaja' => $parte->motivo_baja,
                'altaEn' => $parte->analisisAlta?->etiqueta(),
                'bajaEn' => $parte->analisisBaja?->etiqueta(),
            ],
            'requisitos' => $parte->requisitos
                ->map(fn (RequisitoInteresado $requisito): array => $this->serializarRequisito($requisito))
                ->all(),
            'puedeGestionar' => $this->puede(Permiso::ContextoGestionar),
            'implantacionesDisponibles' => $this->implantacionesDisponibles(),
            ...$this->opciones(),
        ]);
    }

    public function edit(ParteInteresada $parte): Response
    {
        return Inertia::render('partes-interesadas/Formulario', [
            'parte' => [
                'id' => $parte->id,
                'codigo' => $parte->codigo,
                'nombre' => $parte->nombre,
                'tipo' => $parte->tipo->value,
                'ambito' => $parte->ambito->value,
                'descripcion' => $parte->descripcion,
                'responsable_id' => $parte->responsable_id,
            ],
            'sugerencia' => null,
            ...$this->opciones(),
        ]);
    }

    public function update(GuardarParteInteresadaRequest $request, ParteInteresada $parte): RedirectResponse
    {
        $parte->update($request->validated());

        Inertia::flash('exito', 'Parte interesada actualizada.');

        return to_route('partes-interesadas.show', $parte);
    }

    public function destroy(ParteInteresada $parte): RedirectResponse
    {
        $parte->delete();

        Inertia::flash('exito', 'Parte interesada eliminada.');

        return to_route('partes-interesadas.index');
    }

    public function retirar(
        RetirarDelContextoRequest $request,
        ParteInteresada $parte,
        RetirarDelAnalisis $retirar,
    ): RedirectResponse {
        $retirar->parte($parte, $request->string('motivo')->value(), $request->user());

        Inertia::flash('exito', "Parte interesada {$parte->codigo} retirada. Queda en el registro con su motivo.");

        return back();
    }

    public function guardarRequisitos(
        GuardarRequisitosInteresadoRequest $request,
        ParteInteresada $parte,
        GuardarRequisitosInteresado $guardar,
    ): RedirectResponse {
        /** @var list<array<string, mixed>> $lineas */
        $lineas = $request->validated()['requisitos'];

        $guardar($parte, $lineas);

        Inertia::flash('exito', 'Requisitos guardados.');

        return back();
    }

    public function vincularImplantacion(
        VincularEnContextoRequest $request,
        ParteInteresada $parte,
        RequisitoInteresado $requisito,
        VincularImplantacionARequisito $vinculos,
    ): RedirectResponse {
        /*
         * Por el modelo y no copiando el id: así pasa por el scope de organización
         * y por RLS. Lo ajeno da 404, nunca 403.
         */
        $implantacion = Implantacion::query()->with('requisito')->findOrFail($request->integer('implantacion_id'));

        $vinculos->vincular($requisito, $implantacion, $request->user());

        Inertia::flash('exito', sprintf(
            'Requisito atado a %s. Ahora la Declaración de Aplicabilidad puede citarlo como justificación de inclusión.',
            $implantacion->requisito->codigo ?? 'la medida',
        ));

        return back();
    }

    public function desvincularImplantacion(
        ParteInteresada $parte,
        RequisitoInteresado $requisito,
        Implantacion $implantacion,
        VincularImplantacionARequisito $vinculos,
    ): RedirectResponse {
        $vinculos->desvincular($requisito, $implantacion);

        Inertia::flash('exito', 'Vínculo retirado.');

        return back();
    }

    /**
     * Las medidas que se pueden atar a un requisito.
     *
     * **Sólo las aplicables.** Un requisito legal no se cubre con un control que al
     * sistema no se le exige, y ofrecer los excluidos llenaría el desplegable de
     * opciones que no significan nada. Es además lo que lo mantiene en un tamaño
     * razonable: 52 medidas en un sistema del ENS de categoría básica.
     *
     * **Limitación declarada**: es un desplegable y no un buscador. Con dos o tres
     * sistemas dentro la lista se hace larga, y ahí hará falta otra cosa.
     *
     * @return list<array<string, string>>
     */
    private function implantacionesDisponibles(): array
    {
        return Implantacion::query()
            ->aplicables()
            ->with(['requisito', 'sistema'])
            ->join('requisitos', 'requisitos.id', '=', 'implantaciones.requisito_id')
            ->orderBy('requisitos.codigo')
            ->select('implantaciones.*')
            ->get()
            ->map(static fn (Implantacion $implantacion): array => [
                'valor' => (string) $implantacion->id,
                'etiqueta' => sprintf(
                    '%s · %s (%s)',
                    $implantacion->requisito->codigo ?? '—',
                    $implantacion->requisito->titulo ?? '',
                    $implantacion->sistema->codigo ?? '',
                ),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarRequisito(RequisitoInteresado $requisito): array
    {
        return [
            'id' => $requisito->id,
            'descripcion' => $requisito->descripcion,
            'naturaleza' => $requisito->naturaleza->value,
            'naturalezaEtiqueta' => $requisito->naturaleza->etiqueta(),
            'naturalezaTono' => $requisito->naturaleza->tono(),
            'naturalezaIcono' => $requisito->naturaleza->icono(),
            'obliga' => $requisito->naturaleza->obliga(),
            'es_climatico' => $requisito->es_climatico,
            'referencia' => $requisito->referencia,
            'como_se_atiende' => $requisito->como_se_atiende,
            'implantaciones' => $requisito->implantaciones->map(fn (Implantacion $implantacion): array => [
                'id' => $implantacion->id,
                'codigo' => $implantacion->requisito?->codigo,
                'titulo' => $implantacion->requisito?->titulo,
                'sistema' => $implantacion->sistema?->codigo,
                'estado' => $implantacion->estado->etiqueta(),
                'estadoTono' => $implantacion->estado->tono(),
                'estadoIcono' => $implantacion->estado->icono(),
            ])->all(),
        ];
    }

    /**
     * **Los usuarios se acotan a mano**: `User` no lleva `PerteneceAOrganizacion`,
     * así que sin este `where` el desplegable lista a los de todos los clientes.
     *
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'tipos' => array_map(static fn (TipoParteInteresada $tipo): array => [
                'valor' => $tipo->value,
                'etiqueta' => $tipo->etiqueta(),
                'ambitoSugerido' => $tipo->ambitoSugerido()?->value,
            ], TipoParteInteresada::cases()),

            'ambitos' => array_map(static fn (Ambito $ambito): array => [
                'valor' => $ambito->value,
                'etiqueta' => $ambito->etiqueta(),
            ], Ambito::cases()),

            'naturalezas' => array_map(static fn (NaturalezaRequisito $naturaleza): array => [
                'valor' => $naturaleza->value,
                'etiqueta' => $naturaleza->etiqueta(),
                'tono' => $naturaleza->tono(),
                'icono' => $naturaleza->icono(),
            ], NaturalezaRequisito::cases()),

            'usuarios' => User::query()
                ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                ->orderBy('name')
                ->get(['id', 'name'])
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
