<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Auditoria\CerrarAuditoria;
use App\Domain\Auditoria\Enums\EstadoAuditoria;
use App\Domain\Auditoria\Enums\ResultadoPunto;
use App\Domain\Auditoria\Enums\TipoAuditoria;
use App\Domain\Auditoria\Enums\TipoHallazgo;
use App\Domain\Auditoria\Excepciones\AuditoriaCerrada;
use App\Domain\Auditoria\Excepciones\TransicionDeAuditoriaNoPermitida;
use App\Domain\Auditoria\Models\Auditoria;
use App\Domain\Auditoria\Models\AuditoriaPunto;
use App\Domain\Auditoria\Models\Hallazgo;
use App\Domain\Auditoria\PrecargarChecklist;
use App\Domain\Auditoria\RegistrarAuditoria;
use App\Domain\Auditoria\RegistrarHallazgo;
use App\Domain\Auditoria\RegistroAuditorias;
use App\Domain\Auditoria\RevisarPunto;
use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Sistema\Models\Sistema;
use App\Http\Requests\GuardarAuditoriaRequest;
use App\Http\Requests\MarcarConformesRequest;
use App\Http\Requests\RegistrarHallazgoRequest;
use App\Http\Requests\RevisarPuntoRequest;
use App\Http\Resources\AuditoriaRecurso;
use App\Http\Resources\ChecklistRecurso;
use App\Http\Resources\Concerns\RespondeConRecurso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El registro de auditorías y su checklist.
 *
 * La checklist es **una ruta propia** y no un bloque de la ficha: son 52 medidas
 * en un sistema de categoría básica y unas 122 en uno de ISO, y a ese tamaño hace
 * falta filtrar, ordenar y marcar en bloque. Mismo criterio que las tres
 * pantallas del plan de acción.
 */
class AuditoriaController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request, AuditoriaRecurso $recurso, RegistroAuditorias $registro): Response
    {
        return Inertia::render('auditorias/Index', [
            ...$this->tabla($recurso, $request),
            'alertas' => $registro->alertas(),
            'pendientes' => $registro->pendientes(),
            'total' => $registro->total(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('auditorias/Formulario', [
            'auditoria' => null,
            ...$this->opciones(),
        ]);
    }

    public function store(GuardarAuditoriaRequest $request, RegistrarAuditoria $registrar): RedirectResponse
    {
        $auditoria = $registrar($request->validated());

        Inertia::flash('exito', "Auditoría {$auditoria->codigo} registrada.");

        return to_route('auditorias.show', $auditoria);
    }

    public function show(Auditoria $auditoria): Response
    {
        $auditoria->load([
            'sistema.marco',
            'cerradaPor',
            'hallazgos.punto.implantacion.requisito',
            // Para saber cuáles siguen sin tratamiento, que es la costura entre
            // las dos mitades del módulo.
            'hallazgos.noConformidad',
        ]);

        $avance = $auditoria->avance();

        return Inertia::render('auditorias/Ficha', [
            'auditoria' => $this->serializar($auditoria),
            'avance' => $avance,
            'hallazgos' => $auditoria->hallazgos
                ->map(fn (Hallazgo $hallazgo): array => $this->serializarHallazgo($hallazgo))
                ->values()
                ->all(),
            'tipos' => array_map(
                static fn (TipoHallazgo $tipo): array => [
                    'valor' => $tipo->value,
                    'etiqueta' => $tipo->etiqueta(),
                    'tono' => $tipo->tono(),
                    'icono' => $tipo->icono(),
                ],
                TipoHallazgo::cases(),
            ),
            /*
             * Las transiciones las manda el servidor para que el cliente no tenga
             * que reconstruir la máquina de estados, como en el tablero de tareas
             * y por el mismo motivo: un gesto que se acepta y luego falla se
             * explica mucho peor que uno que no se ofrece.
             */
            'transiciones' => array_map(
                static fn (EstadoAuditoria $destino): array => [
                    'valor' => $destino->value,
                    'etiqueta' => $destino->etiqueta(),
                    'tono' => $destino->tono(),
                    'icono' => $destino->icono(),
                ],
                $auditoria->estado->transicionesPermitidas(),
            ),
            'puedeGestionar' => $this->puedeGestionar(),
            /*
             * Abrir el tratamiento va con `no_conformidades.gestionar` y no con
             * el permiso de auditorías: son dos módulos y dos decisiones. Se
             * manda para que el botón no se ofrezca a quien la ruta va a
             * rechazar — un gesto que se acepta y luego falla se explica mucho
             * peor que uno que no se ofrece.
             */
            'puedeTratar' => request()->user()?->can(Permiso::NoConformidadesGestionar->value) ?? false,
        ]);
    }

    public function edit(Auditoria $auditoria): Response
    {
        return Inertia::render('auditorias/Formulario', [
            'auditoria' => $this->serializar($auditoria),
            ...$this->opciones(),
        ]);
    }

    public function update(GuardarAuditoriaRequest $request, Auditoria $auditoria): RedirectResponse
    {
        $auditoria->update($request->validated());

        Inertia::flash('exito', 'Auditoría actualizada.');

        return to_route('auditorias.show', $auditoria);
    }

    public function destroy(Auditoria $auditoria): RedirectResponse
    {
        $codigo = $auditoria->codigo;
        $auditoria->delete();

        Inertia::flash('exito', "Auditoría {$codigo} eliminada.");

        return to_route('auditorias.index');
    }

    // --- La checklist -------------------------------------------------------

    public function checklist(Request $request, Auditoria $auditoria): Response
    {
        $recurso = new ChecklistRecurso($auditoria);

        return Inertia::render('auditorias/Checklist', [
            /*
             * La clave de caché lleva el id de la auditoría, y sin eso navegar de
             * una a otra pintaría la segunda con la definición de la primera: el
             * cliente reclama la prop `once` cuando la clave coincide y copia el
             * valor viejo. `clave()` se queda estable porque es el nombre con el
             * que la vista de columnas se guarda en el navegador.
             */
            ...$this->tabla($recurso, $request, (string) $auditoria->id),
            'auditoria' => $this->serializar($auditoria),
            'resultados' => array_map(
                static fn (ResultadoPunto $resultado): array => [
                    'valor' => $resultado->value,
                    'etiqueta' => $resultado->etiqueta(),
                    'tono' => $resultado->tono(),
                    'icono' => $resultado->icono(),
                    'exigeHallazgo' => $resultado->exigeHallazgo(),
                ],
                ResultadoPunto::cases(),
            ),
            'tipos' => array_map(
                static fn (TipoHallazgo $tipo): array => [
                    'valor' => $tipo->value,
                    'etiqueta' => $tipo->etiqueta(),
                    'tono' => $tipo->tono(),
                    'icono' => $tipo->icono(),
                ],
                TipoHallazgo::cases(),
            ),
            'puedeGestionar' => $this->puedeGestionar(),
        ]);
    }

    public function precargar(Auditoria $auditoria, PrecargarChecklist $precargar): RedirectResponse
    {
        try {
            $anadidas = $precargar($auditoria);
        } catch (AuditoriaCerrada $error) {
            return back()->withErrors(['checklist' => $error->getMessage()]);
        }

        Inertia::flash('exito', $anadidas === 0
            ? 'La checklist ya estaba al día.'
            : trans_choice('Se ha añadido :count medida.|Se han añadido :count medidas.', $anadidas));

        return back();
    }

    public function revisar(
        RevisarPuntoRequest $request,
        Auditoria $auditoria,
        AuditoriaPunto $punto,
        RevisarPunto $revisar,
    ): RedirectResponse {
        try {
            $revisar->marcar(
                $punto,
                ResultadoPunto::from((string) $request->validated('resultado')),
                $request->string('nota')->value() ?: null,
            );
        } catch (AuditoriaCerrada $error) {
            return back()->withErrors(['resultado' => $error->getMessage()]);
        }

        return back();
    }

    public function marcarConformes(
        MarcarConformesRequest $request,
        Auditoria $auditoria,
        RevisarPunto $revisar,
    ): RedirectResponse {
        try {
            $marcadas = $revisar->marcarConformes($auditoria, $request->puntos());
        } catch (AuditoriaCerrada $error) {
            Inertia::flash('error', $error->getMessage());

            return back();
        }

        Inertia::flash('exito', trans_choice(
            'Se ha marcado :count medida como conforme.|Se han marcado :count medidas como conformes.',
            $marcadas,
        ));

        return back();
    }

    // --- Los hallazgos ------------------------------------------------------

    public function registrarHallazgo(
        RegistrarHallazgoRequest $request,
        Auditoria $auditoria,
        RegistrarHallazgo $registrar,
    ): RedirectResponse {
        /*
         * El punto se busca **dentro de esta auditoría**. El scope global tapa el
         * cruce entre organizaciones y entre dos auditorías de la misma no hay
         * nada que lo tape, así que un id de otra colaría el hallazgo donde no
         * toca.
         */
        $punto = null;
        $puntoId = $request->validated('auditoria_punto_id');

        if ($puntoId !== null) {
            $punto = $auditoria->puntos()->whereKey($puntoId)->firstOrFail();
        }

        try {
            $registrar->registrar(
                $auditoria,
                TipoHallazgo::from((string) $request->validated('tipo')),
                (string) $request->validated('descripcion'),
                $punto,
            );
        } catch (AuditoriaCerrada $error) {
            return back()->withErrors(['descripcion' => $error->getMessage()]);
        }

        Inertia::flash('exito', 'Hallazgo registrado.');

        return back();
    }

    public function retirarHallazgo(
        Auditoria $auditoria,
        Hallazgo $hallazgo,
        RegistrarHallazgo $registrar,
    ): RedirectResponse {
        try {
            $registrar->retirar($hallazgo);
        } catch (AuditoriaCerrada $error) {
            return back()->withErrors(['hallazgo' => $error->getMessage()]);
        }

        Inertia::flash('exito', 'Hallazgo retirado.');

        return back();
    }

    // --- El ciclo de la auditoría -------------------------------------------

    public function transicion(Request $request, Auditoria $auditoria, CerrarAuditoria $cerrar): RedirectResponse
    {
        $datos = $request->validate([
            'estado' => ['required', 'string'],
            'conclusiones' => ['nullable', 'string', 'max:5000'],
        ]);

        $destino = EstadoAuditoria::tryFrom((string) $datos['estado']);

        if ($destino === null) {
            return back()->withErrors(['estado' => 'Ese estado no existe.']);
        }

        try {
            match ($destino) {
                EstadoAuditoria::EnCurso => $auditoria->estado === EstadoAuditoria::Cerrada
                    ? $cerrar->reabrir($auditoria)
                    : $cerrar->empezar($auditoria),
                EstadoAuditoria::Cerrada => $cerrar->cerrar(
                    $auditoria,
                    $request->user(),
                    $datos['conclusiones'] ?? null,
                ),
                EstadoAuditoria::Planificada => throw TransicionDeAuditoriaNoPermitida::de(
                    $auditoria->estado,
                    $destino,
                ),
            };
        } catch (TransicionDeAuditoriaNoPermitida $error) {
            return back()->withErrors(['estado' => $error->getMessage()]);
        }

        Inertia::flash('exito', 'Auditoría actualizada.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(Auditoria $auditoria): array
    {
        return [
            'id' => $auditoria->id,
            'codigo' => $auditoria->codigo,
            'sistema_id' => $auditoria->sistema_id,
            'sistema' => $auditoria->sistema?->codigo,
            'sistemaNombre' => $auditoria->sistema?->nombre,
            'tipo' => $auditoria->tipo->value,
            'tipoEtiqueta' => $auditoria->tipo->etiqueta(),
            'tipoTono' => $auditoria->tipo->tono(),
            'tipoIcono' => $auditoria->tipo->icono(),
            'estado' => $auditoria->estado->value,
            'estadoEtiqueta' => $auditoria->estado->etiqueta(),
            'estadoTono' => $auditoria->estado->tono(),
            'estadoIcono' => $auditoria->estado->icono(),
            'alcance' => $auditoria->alcance,
            'fecha' => $auditoria->fecha->toDateString(),
            'auditor' => $auditoria->auditor,
            'entidad_certificadora' => $auditoria->entidad_certificadora,
            'conclusiones' => $auditoria->conclusiones,
            'fechaCierre' => $auditoria->fecha_cierre?->format('d/m/Y'),
            'cerradaPor' => $auditoria->cerradaPor?->name,
            'admiteCambios' => $auditoria->admiteCambios(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarHallazgo(Hallazgo $hallazgo): array
    {
        return [
            'id' => $hallazgo->id,
            'tipo' => $hallazgo->tipo->value,
            'tipoEtiqueta' => $hallazgo->tipo->etiquetaCorta(),
            'tipoTono' => $hallazgo->tipo->tono(),
            'tipoIcono' => $hallazgo->tipo->icono(),
            'exigeNoConformidad' => $hallazgo->tipo->exigeNoConformidad(),
            'descripcion' => $hallazgo->descripcion,
            'medida' => $hallazgo->punto?->implantacion?->requisito?->codigo,
            /*
             * El tratamiento, si lo tiene. Es lo que deja ver de un vistazo qué
             * hallazgos siguen sin tratar: una no conformidad mayor sin nada
             * detrás es un hallazgo de la auditoría siguiente.
             */
            'noConformidadId' => $hallazgo->noConformidad?->id,
            'noConformidadCodigo' => $hallazgo->noConformidad?->codigo,
        ];
    }

    private function puedeGestionar(): bool
    {
        return request()->user()?->can(Permiso::AuditoriasGestionar->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            'sistemas' => Sistema::query()->with('marco')->orderBy('codigo')->get()
                ->map(fn (Sistema $sistema): array => [
                    'valor' => (string) $sistema->id,
                    'etiqueta' => "{$sistema->codigo} — {$sistema->nombre}",
                    'marco' => $sistema->marco?->codigo,
                ])
                ->all(),
            'tipos' => array_map(
                static fn (TipoAuditoria $tipo): array => [
                    'valor' => $tipo->value,
                    'etiqueta' => $tipo->etiqueta(),
                    'descripcion' => $tipo->descripcion(),
                    'admiteEntidad' => $tipo->admiteEntidadCertificadora(),
                ],
                TipoAuditoria::cases(),
            ),
        ];
    }
}
