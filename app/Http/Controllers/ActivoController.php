<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Activo\Enums\Clasificacion;
use App\Domain\Activo\Enums\EstadoCicloVida;
use App\Domain\Activo\Enums\EstadoControl;
use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Activo\Excepciones\DependenciaCiclicaException;
use App\Domain\Activo\GeneradorEtiquetas;
use App\Domain\Activo\GrafoActivos;
use App\Domain\Activo\Models\Activo;
use App\Domain\Activo\Obsolescencia;
use App\Domain\Activo\RegistrarDependencia;
use App\Domain\Activo\ResumenInventario;
use App\Domain\Activo\ValoracionEfectiva;
use App\Domain\Catalogo\Enums\Dimension;
use App\Domain\Categorizacion\Enums\NivelDimension;
use App\Domain\Categorizacion\ValoracionDimensiones;
use App\Domain\Sistema\Models\Sistema;
use App\Http\Requests\GuardarActivoRequest;
use App\Http\Requests\MarcarRevisadosRequest;
use App\Http\Requests\VincularDependenciaRequest;
use App\Http\Resources\ActivoRecurso;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El inventario de activos.
 *
 * Delgado como el resto: valida con el `FormRequest`, delega en el dominio y
 * devuelve Inertia. Lo único que merece explicación es que la valoración
 * efectiva y el grafo se resuelven en `app/Domain/Activo/`, no aquí: son las
 * mismas preguntas que contestarán el análisis de riesgos y el BIA.
 */
class ActivoController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request, ActivoRecurso $recurso, ResumenInventario $resumen): Response
    {
        return Inertia::render('activos/Index', [
            ...$this->tabla($recurso, $request),
            // Los indicadores no se recalculan al paginar ni al ordenar, pero sí
            // al filtrar por uno de ellos —cambian cuando alguien arregla algo—,
            // así que viajan como prop normal y no como `once`.
            'alertas' => $resumen->alertas(),
            'pendientes' => $resumen->pendientesDeCompletar(),
            'vigentes' => $resumen->vigentes(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('activos/Formulario', [
            'activo' => null,
            // Un activo que todavía no existe no tiene ficha a la que apuntar,
            // así que tampoco tiene etiqueta. El prop viaja igual para que el
            // formulario no tenga que distinguir alta de edición.
            'etiqueta' => null,
            ...$this->opciones(),
        ]);
    }

    public function store(GuardarActivoRequest $request): RedirectResponse
    {
        $datos = $request->safe()->except('sistemas');

        $activo = Activo::query()->create($datos);
        $this->sincronizarAlcance($activo, $request->array('sistemas'));

        Inertia::flash('exito', "Activo {$activo->codigo} dado de alta. Declara de qué depende para que la valoración se propague.");

        return to_route('activos.show', $activo);
    }

    /**
     * La ficha: qué vale este activo, qué se apoya en él y de qué depende.
     */
    public function show(
        Request $request,
        Activo $activo,
        GrafoActivos $grafo,
        ValoracionEfectiva $efectiva,
        Obsolescencia $obsolescencia,
        GeneradorEtiquetas $generador,
    ): Response {
        $activo->load(['propietario', 'custodio', 'sistemas']);

        $valoracionEfectiva = $efectiva->de($activo);

        return Inertia::render('activos/Ficha', [
            'activo' => $this->serializar($activo),
            'etiqueta' => $this->etiqueta($activo, $request, $generador),
            'avisoSoporte' => $obsolescencia->aviso($activo),
            'valoracionPropia' => $this->serializarValoracion($activo->valoracion()),
            'valoracionEfectiva' => $this->serializarValoracion($valoracionEfectiva),
            // De dónde sale la diferencia. Sin esto, la cifra efectiva parece un
            // error de la herramienta y nadie se fía de ella.
            'motivos' => array_map(
                fn (array $motivo): array => [
                    'activo' => $this->resumir($motivo['activo']),
                    'dimensiones' => array_map(
                        static fn (Dimension $dimension): array => [
                            'codigo' => $dimension->value,
                            'nombre' => $dimension->nombre(),
                        ],
                        $motivo['dimensiones'],
                    ),
                ],
                $efectiva->motivos($activo),
            ),
            'dependeDe' => $grafo->dependenciasDe($activo)->map($this->resumir(...))->all(),
            'dependientes' => $grafo->dependientesDe($activo)->map($this->resumir(...))->all(),
            // Para el desplegable de «declarar dependencia»: cualquier activo de
            // la organización menos él mismo. Los que cerrarían un ciclo los
            // rechaza el dominio, con un mensaje que explica por dónde.
            'candidatos' => Activo::query()
                ->whereKeyNot($activo->id)
                ->orderBy('codigo')
                ->get()
                ->map(fn (Activo $candidato): array => [
                    'valor' => (string) $candidato->id,
                    'etiqueta' => "{$candidato->codigo} — {$candidato->nombre}",
                ])
                ->all(),
        ]);
    }

    public function edit(Activo $activo, Request $request, GeneradorEtiquetas $generador): Response
    {
        $activo->load(['sistemas', 'propietario', 'custodio']);

        return Inertia::render('activos/Formulario', [
            'activo' => $this->serializar($activo),
            'etiqueta' => $this->etiqueta($activo, $request, $generador),
            ...$this->opciones(),
        ]);
    }

    public function update(GuardarActivoRequest $request, Activo $activo): RedirectResponse
    {
        $activo->update($request->safe()->except('sistemas'));
        $this->sincronizarAlcance($activo, $request->array('sistemas'));

        Inertia::flash('exito', "Activo {$activo->codigo} actualizado.");

        return to_route('activos.show', $activo);
    }

    public function destroy(Activo $activo): RedirectResponse
    {
        $codigo = $activo->codigo;
        $sostenia = $activo->dependientes()->count();

        $activo->delete();

        Inertia::flash('exito', $sostenia === 0
            ? "Activo {$codigo} eliminado."
            : "Activo {$codigo} eliminado. Los {$sostenia} activos que se apoyaban en él dejan de heredar su valoración: repásalos.");

        return to_route('activos.index');
    }

    /**
     * La hoja de etiquetas QR para imprimir y pegar.
     *
     * Sin selección, salen todos los que deben llevarla. Con selección —desde la
     * acción masiva de la tabla— salen sólo esos, que es lo normal: se etiquetan
     * los equipos nuevos, no el parque entero cada vez.
     *
     * Lo que nunca sale es un activo sin carcasa o ya retirado, y esa criba la
     * hace el dominio, no esta consulta.
     */
    public function etiquetas(Request $request, GeneradorEtiquetas $generador): Response
    {
        $seleccion = array_filter(array_map(intval(...), $request->array('ids')));

        $activos = Activo::query()
            ->when($seleccion !== [], fn ($consulta) => $consulta->whereIn('id', $seleccion))
            ->orderBy('codigo')
            ->get();

        $organizacion = $request->user()?->organizacion;

        return Inertia::render('activos/Etiquetas', [
            'etiquetas' => $generador->etiquetables($activos)
                ->map(fn (Activo $activo): array => [
                    'id' => $activo->id,
                    'codigo' => $activo->codigo,
                    'nombre' => $activo->nombre,
                    'detalle' => $activo->marca_modelo ?? $activo->subtipo ?? $activo->tipo->etiqueta(),
                    'svg' => $organizacion === null ? null : $generador->svg($activo, $organizacion),
                ])
                ->all(),
            // Cuántos se quedaron fuera y por qué, para que nadie crea que se
            // ha perdido la mitad del inventario por el camino.
            'descartados' => $activos->count() - $generador->etiquetables($activos)->count(),
        ]);
    }

    /**
     * Marca los activos seleccionados como revisados hoy.
     *
     * Es lo que conecta el registro de revisiones con el inventario sin una
     * tabla pivote de por medio: la revisión anota qué se miró y esto pone la
     * fecha en las filas que se miraron.
     */
    public function marcarRevisados(MarcarRevisadosRequest $request): RedirectResponse
    {
        $ids = array_map(intval(...), $request->array('activos'));

        // `update` masivo y no un bucle de `save`: son cientos de filas y aquí
        // no hay nada que calcular por activo. El precio es que no se dispara
        // el evento de traza por fila, y es el correcto: la evidencia de que
        // esto pasó es la revisión que se registra aparte, no 300 apuntes.
        $afectados = Activo::query()->whereIn('id', $ids)->update([
            'ultima_revision' => Carbon::today(),
        ]);

        Inertia::flash('exito', $afectados === 1
            ? 'Un activo marcado como revisado hoy.'
            : "{$afectados} activos marcados como revisados hoy. Regístralo también en Revisiones para dejar constancia de qué se miró.");

        return back();
    }

    public function vincularDependencia(
        VincularDependenciaRequest $request,
        Activo $activo,
        RegistrarDependencia $registrar,
    ): RedirectResponse {
        $dependeDe = Activo::query()->findOrFail($request->integer('depende_de_id'));

        try {
            $registrar->vincular($activo, $dependeDe, $request->string('nota')->value() ?: null);
        } catch (DependenciaCiclicaException $excepcion) {
            return back()->withErrors(['depende_de_id' => $excepcion->getMessage()]);
        }

        Inertia::flash('exito', "{$activo->codigo} pasa a depender de {$dependeDe->codigo}.");

        return to_route('activos.show', $activo);
    }

    public function desvincularDependencia(
        Activo $activo,
        Activo $dependencia,
        RegistrarDependencia $registrar,
    ): RedirectResponse {
        $registrar->desvincular($activo, $dependencia);

        Inertia::flash('exito', "{$activo->codigo} ya no depende de {$dependencia->codigo}.");

        return to_route('activos.show', $activo);
    }

    /**
     * El QR del activo, o `null` si no lleva etiqueta.
     *
     * La criba la hace `Activo::llevaEtiqueta()`: un servicio en la nube o una
     * suscripción de SaaS no tienen carcasa donde pegar nada, y un equipo
     * retirado no se etiqueta, se borra. Enseñar un QR ahí invitaría a imprimir
     * una pegatina que no va a ninguna parte.
     *
     * @return array{svg: string, url: string}|null
     */
    private function etiqueta(Activo $activo, Request $request, GeneradorEtiquetas $generador): ?array
    {
        $organizacion = $request->user()?->organizacion;

        if ($organizacion === null || ! $activo->llevaEtiqueta()) {
            return null;
        }

        return [
            'svg' => $generador->svg($activo, $organizacion),
            'url' => $generador->contenido($activo, $organizacion),
        ];
    }

    /**
     * El alcance es N:M y la pivote lleva `organizacion_id` como cualquier otra
     * tabla de datos propios: sin rellenarlo, RLS rechaza la inserción.
     *
     * @param  array<int, mixed>  $sistemas
     */
    private function sincronizarAlcance(Activo $activo, array $sistemas): void
    {
        $vinculos = [];

        foreach ($sistemas as $sistemaId) {
            $vinculos[(int) $sistemaId] = ['organizacion_id' => $activo->organizacion_id];
        }

        $activo->sistemas()->sync($vinculos);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(Activo $activo): array
    {
        return [
            'id' => $activo->id,
            'codigo' => $activo->codigo,
            'nombre' => $activo->nombre,
            'descripcion' => $activo->descripcion,
            'tipo' => $activo->tipo->value,
            'tipoEtiqueta' => $activo->tipo->etiqueta(),
            'tipoIcono' => $activo->tipo->icono(),
            'subtipo' => $activo->subtipo,
            'marca_modelo' => $activo->marca_modelo,
            'especificaciones' => $activo->especificaciones,
            'sistema_operativo' => $activo->sistema_operativo,
            'fin_soporte_so' => $activo->fin_soporte_so?->toDateString(),
            'identificador' => $activo->identificador,
            'propietario_id' => $activo->propietario_id,
            'propietario' => $activo->propietario?->name,
            'custodio_id' => $activo->custodio_id,
            'custodio' => $activo->custodio?->name,
            'departamento' => $activo->departamento,
            'ubicacion' => $activo->ubicacion,
            'fin_garantia' => $activo->fin_garantia?->toDateString(),
            'estado_ciclo_vida' => $activo->estado_ciclo_vida->value,
            'estadoEtiqueta' => $activo->estado_ciclo_vida->etiqueta(),
            'estadoTono' => $activo->estado_ciclo_vida->tono(),
            'clasificacion' => $activo->clasificacion->value,
            'clasificacionEtiqueta' => $activo->clasificacion->etiqueta(),
            'clasificacionTono' => $activo->clasificacion->tono(),
            'cifrado' => $activo->cifrado->value,
            'cifradoEtiqueta' => $activo->cifrado->etiqueta(),
            'cifradoTono' => $activo->cifrado->tono(),
            'copia_seguridad' => $activo->copia_seguridad->value,
            'copiaEtiqueta' => $activo->copia_seguridad->etiqueta(),
            'copiaTono' => $activo->copia_seguridad->tono(),
            'ultima_revision' => $activo->ultima_revision?->toDateString(),
            'sinRevisar' => $activo->sinRevisar(),
            'llevaEtiqueta' => $activo->llevaEtiqueta(),
            'etiquetado_en' => $activo->etiquetado_en?->toDateString(),
            'observaciones' => $activo->observaciones,
            'esperaBorradoSeguro' => $activo->esperaBorradoSeguro(),
            'valor_c' => $activo->valor_c,
            'valor_i' => $activo->valor_i,
            'valor_d' => $activo->valor_d,
            'valor_a' => $activo->valor_a,
            'valor_t' => $activo->valor_t,
            'fecha_alta' => $activo->fecha_alta?->toDateString(),
            'fecha_baja' => $activo->fecha_baja?->toDateString(),
            'borrado_seguro_en' => $activo->borrado_seguro_en?->toDateTimeString(),
            'nota_baja' => $activo->nota_baja,
            'sistemas' => $activo->sistemas->map(fn (Sistema $sistema): array => [
                'id' => $sistema->id,
                'codigo' => $sistema->codigo,
                'nombre' => $sistema->nombre,
            ])->all(),
        ];
    }

    /**
     * La forma corta con la que viaja un activo dentro del grafo de otro.
     *
     * @return array<string, mixed>
     */
    private function resumir(Activo $activo): array
    {
        return [
            'id' => $activo->id,
            'codigo' => $activo->codigo,
            'nombre' => $activo->nombre,
            'tipo' => $activo->tipo->value,
            'tipoEtiqueta' => $activo->tipo->etiqueta(),
            'profundidad' => (int) ($activo->getAttribute('profundidad') ?? 1),
            'nota' => $activo->getAttribute('vinculo_nota'),
            'directa' => (int) ($activo->getAttribute('profundidad') ?? 1) === 1,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarValoracion(ValoracionDimensiones $valoracion): array
    {
        $categoria = $valoracion->categoria();

        return [
            'dimensiones' => array_map(
                static fn (Dimension $dimension): array => [
                    'codigo' => $dimension->value,
                    'nombre' => $dimension->nombre(),
                    'nivel' => $valoracion->nivelDe($dimension)->value,
                    'nivelEtiqueta' => $valoracion->nivelDe($dimension)->etiqueta(),
                    'peso' => $valoracion->nivelDe($dimension)->peso(),
                ],
                Dimension::cases(),
            ),
            'maximo' => $valoracion->nivelMaximo()->value,
            'maximoEtiqueta' => $valoracion->nivelMaximo()->etiqueta(),
            'categoria' => $categoria?->value,
            'categoriaEtiqueta' => $categoria?->etiqueta(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'tipos' => array_map(
                static fn (TipoActivo $tipo): array => [
                    'valor' => $tipo->value,
                    'etiqueta' => $tipo->etiqueta(),
                    'ayuda' => $tipo->ejemplo(),
                    'fichaTecnica' => $tipo->tieneFichaTecnica(),
                ],
                TipoActivo::cases(),
            ),
            'estados' => array_map(
                static fn (EstadoCicloVida $estado): array => [
                    'valor' => $estado->value,
                    'etiqueta' => $estado->etiqueta(),
                ],
                EstadoCicloVida::cases(),
            ),
            'niveles' => array_map(
                static fn (NivelDimension $nivel): array => [
                    'valor' => $nivel->value,
                    'etiqueta' => $nivel->etiqueta(),
                ],
                NivelDimension::cases(),
            ),
            // La pregunta de cada dimensión, no su nombre: quien valora no tiene
            // que saber qué es «trazabilidad», tiene que saber qué pasa si no se
            // puede reconstruir quién hizo qué.
            'dimensiones' => array_map(
                static fn (Dimension $dimension): array => [
                    'codigo' => $dimension->value,
                    'campo' => 'valor_'.mb_strtolower($dimension->value),
                    'nombre' => $dimension->nombre(),
                    'pregunta' => $dimension->pregunta(),
                ],
                Dimension::cases(),
            ),
            'clasificaciones' => array_map(
                static fn (Clasificacion $clasificacion): array => [
                    'valor' => $clasificacion->value,
                    'etiqueta' => $clasificacion->etiqueta(),
                    'ayuda' => $clasificacion->descripcion(),
                ],
                Clasificacion::cases(),
            ),
            'controles' => array_map(
                static fn (EstadoControl $control): array => [
                    'valor' => $control->value,
                    'etiqueta' => $control->etiqueta(),
                ],
                EstadoControl::cases(),
            ),
            // El mapa sistema operativo => fin de soporte, para que el
            // formulario proponga la fecha al elegir la versión y nadie tenga
            // que buscarla. Es una propuesta, no una imposición: se puede
            // escribir otra.
            'sistemasOperativos' => app(Obsolescencia::class)->sistemasOperativos(),
            'personas' => User::query()
                ->orderBy('name')
                ->get()
                ->map(fn (User $usuario): array => [
                    'valor' => (string) $usuario->id,
                    'etiqueta' => $usuario->name,
                ])
                ->all(),
            'sistemas' => Sistema::query()
                ->orderBy('codigo')
                ->get()
                ->map(fn (Sistema $sistema): array => [
                    'valor' => (string) $sistema->id,
                    'etiqueta' => "{$sistema->codigo} — {$sistema->nombre}",
                ])
                ->all(),
        ];
    }
}
