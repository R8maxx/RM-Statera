<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Activo\Enums\TipoActivo;
use App\Domain\Activo\Models\Activo;
use App\Domain\Autorizacion\Enums\Permiso;
use App\Domain\Continuidad\Models\PruebaContinuidad;
use App\Domain\Documento\AcusarLectura;
use App\Domain\Documento\AprobarVersion;
use App\Domain\Documento\CoberturaAcuse;
use App\Domain\Documento\Contenido\ContenidoDocumento;
use App\Domain\Documento\Enums\ClasificacionDocumental;
use App\Domain\Documento\Enums\EstadoDocumental;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\EnviarARevision;
use App\Domain\Documento\GenerarDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Documento\Narrativa\MarkdownDocumento;
use App\Domain\Documento\Narrativa\MaterializarSecciones;
use App\Domain\Documento\RechazarVersion;
use App\Domain\Documento\Render\EscritorWord;
use App\Domain\Documento\ResumenDocumental;
use App\Domain\Organizacion\ContextoOrganizacion;
use App\Domain\Sistema\Models\Sistema;
use App\Http\Requests\AprobarVersionRequest;
use App\Http\Requests\EnviarARevisionRequest;
use App\Http\Requests\GenerarDocumentoRequest;
use App\Http\Requests\GuardarDocumentoRequest;
use App\Http\Requests\RechazarVersionRequest;
use App\Http\Resources\Concerns\RespondeConRecurso;
use App\Http\Resources\DocumentoRecurso;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lo que se le entrega al auditor.
 *
 * Delgado como el resto: valida con el `FormRequest`, delega en el dominio y
 * devuelve Inertia. Generar no se hace aquí —va a la cola— y emitir tampoco: de
 * eso responde `EmitirVersion`.
 */
class DocumentoController extends Controller
{
    use RespondeConRecurso;

    public function index(Request $request, ResumenDocumental $resumen): Response
    {
        return Inertia::render('documentos/Index', [
            ...$this->tabla(new DocumentoRecurso, $request),
            /*
             * Lo que pide acción hoy, encima de la tabla y no en el panel: mismo
             * reparto que en el inventario —el panel contesta cómo va la cosa y
             * la tabla contesta qué hay que resolver—.
             */
            'alertas' => $resumen->alertas(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('documentos/Formulario', [
            'documento' => null,
            ...$this->opciones(),
        ]);
    }

    public function store(GuardarDocumentoRequest $request, MaterializarSecciones $materializar): RedirectResponse
    {
        $documento = Documento::query()->create($request->validated());

        // El documento nace con los textos base de la organización. A partir de
        // aquí son suyos: si la plantilla cambia, este documento no se mueve.
        $materializar($documento);

        Inertia::flash('exito', "Documento {$documento->codigo} creado. Genera un borrador para ver cómo queda.");

        return to_route('documentos.show', $documento);
    }

    /**
     * La ficha: el borrador vivo, el historial de entregas y sus huellas.
     */
    public function show(Request $request, Documento $documento, CoberturaAcuse $acuse): Response
    {
        $documento->load(['sistema.marco', 'responsable']);

        $vigente = $documento->versionAprobada()->with('aprobadaPor')->first();
        $usuario = $request->user();

        return Inertia::render('documentos/Ficha', [
            'documento' => $this->serializar($documento),

            /*
             * Sólo un plan de continuidad vincula servicios (§ 4.11), y nulo
             * -no una lista vacía- es lo que le dice a la ficha que no ofrezca
             * el bloque en absoluto para cualquier otro tipo.
             */
            ...$this->serviciosDelPlan($documento),

            /*
             * La versión vigente va suelta y no dentro de `versiones`: es la que
             * se lee, la que se acusa y la que caduca, y la ficha la enseña
             * arriba. Las demás son histórico.
             */
            'versionVigente' => $this->serializarVersion($vigente),

            /*
             * El bloque de acuse **no se manda si el documento no lo exige**, y
             * el de aprobación no manda botones a quien no tiene el permiso: el
             * frontend decide qué pinta y nunca qué autoriza.
             */
            'acuse' => $vigente !== null && $documento->exigeAcuse()
                ? [
                    ...$acuse->de($vigente),
                    'yaAcusado' => $usuario instanceof User && $acuse->loHaAcusado($vigente, $usuario),
                ]
                : null,

            'puedeAprobar' => $usuario instanceof User
                && $usuario->can(Permiso::DocumentosAprobar->value),
            'puedeRedactar' => $usuario instanceof User
                && $usuario->can(Permiso::DocumentosRedactar->value),
            /*
             * Si el documento se editó DESPUÉS de generar el borrador, el PDF
             * que hay en disco es anterior y no lleva esos cambios. Sin este
             * aviso, alguien edita, descarga, no ve su texto y da el módulo por
             * roto.
             */
            'cuerpoMasNuevoQueElBorrador' => $this->cuerpoPosteriorAlBorrador($documento),
            /*
             * Estos dos props son los que recarga el poll mientras el trabajo
             * está vivo, y van sueltos —no dentro de `documento`— porque una
             * recarga parcial pide claves de primer nivel.
             *
             * Como cierres: sólo se evalúan cuando el prop viaja, así que un
             * `router.reload({ only: [...] })` no vuelve a consultar la ficha
             * entera cada tres segundos.
             */
            'versionEnCurso' => fn (): ?array => $this->serializarVersion(
                $documento->versiones()->whereNull('numero')->with('generadaPor')->first(),
            ),
            'versiones' => fn (): array => $documento->versionesEmitidas()
                ->with(['generadaPor', 'aprobadaPor'])
                ->get()
                ->map(fn (DocumentoVersion $v): array => (array) $this->serializarVersion($v))
                ->all(),
        ]);
    }

    public function edit(Documento $documento): Response
    {
        return Inertia::render('documentos/Formulario', [
            'documento' => $this->serializar($documento),
            ...$this->opciones(),
        ]);
    }

    public function update(
        GuardarDocumentoRequest $request,
        Documento $documento,
        MaterializarSecciones $materializar,
    ): RedirectResponse {
        $documento->update($request->validated());

        /*
         * El tipo se puede cambiar, y con él cambia qué huecos existen: una SoA
         * que pasa a DdA arrastraría su nota de exclusiones invisible y la
         * recuperaría —con un texto de hace meses— si alguien la devolviera. Se
         * tiran las que dejan de aplicar y se materializan las nuevas.
         */
        $materializar->sincronizarConElTipo($documento->fresh() ?? $documento);

        Inertia::flash('exito', "Documento {$documento->codigo} actualizado.");

        return to_route('documentos.show', $documento);
    }

    /**
     * Borrar la serie con entregas detrás no se ofrece.
     *
     * Una versión emitida es la prueba de lo que se entregó; permitir tirarla
     * desde un menú contextual convertiría el registro inmutable en una
     * formalidad. Si hay que retirar un documento, se retira el registro entero
     * a mano y queda en la traza.
     */
    public function destroy(Documento $documento): RedirectResponse
    {
        if ($documento->versiones()->whereNotNull('numero')->exists()) {
            Inertia::flash('error', "{$documento->codigo} tiene versiones emitidas y no se puede borrar: son la prueba de lo que se entregó.");

            return to_route('documentos.show', $documento);
        }

        /*
         * § 4.11: `pruebas_continuidad.documento_id` lleva `restrictOnDelete`
         * en la migración, así que sin este guardián un plan de continuidad
         * probado respondería con un `QueryException` crudo en vez de un
         * error que explique por qué. Una prueba registrada es la evidencia
         * de `op.cont.3`: borrar el plan la dejaría sin el documento que
         * demuestra.
         */
        if (PruebaContinuidad::query()->where('documento_id', $documento->id)->exists()) {
            Inertia::flash('error', "{$documento->codigo} tiene pruebas de continuidad registradas y no se puede borrar: son la evidencia de op.cont.3.");

            return to_route('documentos.show', $documento);
        }

        $codigo = $documento->codigo;
        $documento->delete();

        Inertia::flash('exito', "Documento {$codigo} eliminado.");

        return to_route('documentos.index');
    }

    /**
     * Encola la generación. Nunca genera aquí.
     */
    public function generar(GenerarDocumentoRequest $request, Documento $documento, GenerarDocumento $generar): RedirectResponse
    {
        $usuario = $request->user();

        $generar->encolar($documento, $usuario instanceof User ? $usuario : null, $request->parametros());

        Inertia::flash('exito', 'Generación encolada. El borrador aparecerá aquí en cuanto termine.');

        /*
         * Vuelve a donde se pidió, y no siempre a la ficha: generar se pide
         * también desde el editor, con «Ver el PDF», y mandar allí a la ficha
         * saca a alguien de un documento a medio redactar para enseñarle una
         * pantalla que no ha pedido. Desde la ficha, `back()` es la ficha.
         */
        return back();
    }

    /**
     * «Esto ya está: que lo mire quien tiene que firmarlo.»
     *
     * No congela nada: el borrador sigue siendo regenerable mientras está en
     * revisión, porque quien revisa pide cambios y quien escribió los hace.
     */
    public function revisar(EnviarARevisionRequest $request, Documento $documento, EnviarARevision $enviar): RedirectResponse
    {
        $borrador = $documento->borrador()->first();

        abort_if($borrador === null, 404);

        $enviar($borrador, $request->string('motivo')->value() ?: null);

        Inertia::flash('exito', 'El documento está en revisión, a la espera de aprobación.');

        return to_route('documentos.show', $documento);
    }

    /**
     * La firma de la dirección, que es lo que entrega el documento.
     *
     * Aprobar **encola una regeneración**: el PDF tiene que salir con la firma
     * impresa en portada, y la portada se congela al generar. Quien numera y
     * mueve el fichero a `emitidas/` es el final de ese trabajo, así que aquí no
     * hay número que anunciar todavía — lo anuncia el cliente cuando lo ve, igual
     * que con «generando…».
     */
    public function aprobar(
        AprobarVersionRequest $request,
        Documento $documento,
        DocumentoVersion $version,
        AprobarVersion $aprobar,
    ): RedirectResponse {
        $usuario = $request->user();

        abort_unless($usuario instanceof User, 403);

        $aprobar($version, $usuario, $request->string('nota')->value() ?: null);

        Inertia::flash('exito', 'Documento aprobado. Se está generando la versión firmada.');

        return to_route('documentos.show', $documento);
    }

    /** La otra mitad de decidir: la dirección dice que no, y dice por qué. */
    public function rechazar(
        RechazarVersionRequest $request,
        Documento $documento,
        DocumentoVersion $version,
        RechazarVersion $rechazar,
    ): RedirectResponse {
        $rechazar($version, $request->string('motivo')->toString());

        Inertia::flash('exito', 'Versión rechazada. El motivo queda registrado para quien la retome.');

        return to_route('documentos.show', $documento);
    }

    /**
     * «He leído esta versión.»
     *
     * Sin permiso propio: se escribe sobre uno mismo, como en `/perfil`.
     */
    public function acusar(
        Request $request,
        Documento $documento,
        DocumentoVersion $version,
        AcusarLectura $acusar,
    ): RedirectResponse {
        $usuario = $request->user();

        abort_unless($usuario instanceof User, 403);

        $acusar($version, $usuario);

        Inertia::flash('exito', 'Queda registrado que has leído esta versión.');

        return to_route('documentos.show', $documento);
    }

    /**
     * El mismo documento en `.docx`, como copia de trabajo.
     *
     * **Se construye desde `instantanea`, nunca desde una consulta nueva.** Si
     * se consultara, el Word de una versión emitida en marzo enseñaría los datos
     * de octubre y contradiría al PDF que lo acompaña, con la huella de ese PDF
     * impresa dentro. Es el fallo más caro que puede tener este módulo.
     *
     * No se almacena ni se versiona: `documento_versiones` existe para demostrar
     * qué se entregó, y esto explícitamente no es la entrega. Además, el trigger
     * de inmutabilidad no dejaría adjuntarlo a una versión ya emitida.
     */
    public function word(
        Documento $documento,
        DocumentoVersion $version,
        EscritorWord $escritor,
        MarkdownDocumento $markdown,
    ): StreamedResponse {
        $cuerpo = $version->instantanea['cuerpo'] ?? null;

        /*
         * Sin cuerpo congelado no hay copia de trabajo que dar.
         *
         * Toda versión generada lleva su árbol dentro de `instantanea` —lo
         * escribe `GenerarDocumento::almacenar()`—, así que esto sólo se cumple
         * en una fila a medio hacer. Construirla desde una consulta nueva sería
         * el fallo más caro del módulo: el Word de una versión de marzo
         * enseñaría los datos de octubre, con la huella de aquel PDF impresa
         * dentro.
         */
        abort_if($version->instantanea === [] || ! is_array($cuerpo), 404);

        $documento->load(['sistema.marco', 'responsable']);

        $docx = $escritor(
            $documento,
            $version,
            ContenidoDocumento::desdeInstantanea($version->instantanea, $markdown),
            $cuerpo,
        );

        $nombre = Str::slug($documento->codigo.'-'.$version->etiqueta().'-copia-de-trabajo').'.docx';

        return response()->streamDownload(
            static fn () => print $docx,
            $nombre,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        );
    }

    /**
     * Descarga por URL firmada de corta duración, como las evidencias.
     *
     * Nunca un enlace directo al bucket: es privado, y el acceso tiene que pasar
     * por el scope de organización —esta ruta— para que el documento de un
     * cliente no sea legible con sólo saber su ruta.
     */
    public function descargar(Documento $documento, DocumentoVersion $version): SymfonyRedirect
    {
        return $this->haciaElFichero($version, 'attachment');
    }

    /**
     * El mismo PDF, pero para mirarlo dentro de la aplicación.
     *
     * El editor pinta la hoja con la misma hoja de estilos que se imprime, pero
     * la paginación, las viudas, la cabecera de tabla repetida y el pie con su
     * número de página sólo los sabe Chromium. Esto es lo que se comprueba antes
     * de emitir, y bajar el fichero para mirarlo y borrarlo es la fricción que
     * hace que nadie lo compruebe.
     *
     * Todo lo demás —`scopeBindings()` en la ruta, la comprobación del fichero,
     * la URL temporal— es lo mismo que la descarga: lo único que cambia es la
     * disposición, para que el visor del navegador lo pinte en vez de bajarlo.
     */
    public function ver(Documento $documento, DocumentoVersion $version): SymfonyRedirect
    {
        return $this->haciaElFichero($version, 'inline');
    }

    private function haciaElFichero(DocumentoVersion $version, string $disposicion): SymfonyRedirect
    {
        abort_unless($version->tieneFichero(), 404);

        return redirect()->away(
            Storage::disk((string) $version->disco)->temporaryUrl(
                (string) $version->ruta,
                now()->addMinutes(5),
                ['ResponseContentDisposition' => $disposicion.'; filename="'.$version->nombre_fichero.'"'],
            )
        );
    }

    /**
     * Si el documento se ha editado después de generar el borrador que hay en disco.
     *
     * Miraba `documento_secciones`, y desde que el documento entero es editable
     * **ahí no escribe nadie**: el aviso no volvía a saltar nunca. Alguien
     * editaba, descargaba el borrador de antes, no veía su texto y daba el
     * módulo por roto — que es exactamente lo que este aviso existe para evitar.
     * Ahora mira el cuerpo, que es donde se escribe.
     */
    private function cuerpoPosteriorAlBorrador(Documento $documento): bool
    {
        $borrador = $documento->borrador()->first();

        if ($borrador === null || ! $borrador->tieneFichero()) {
            return false;
        }

        $cuerpo = $documento->cuerpo()->first();

        return $cuerpo?->updated_at !== null && $cuerpo->updated_at > $borrador->updated_at;
    }

    /**
     * Los servicios de un plan de continuidad, y los que quedan por vincular.
     *
     * Nulo en los dos —y no una lista vacía— para cualquier otro tipo: es lo
     * que le dice a `documentos/Ficha.vue` que no ofrezca el bloque en
     * absoluto, en vez de enseñarlo vacío en un documento que no lo tiene.
     *
     * @return array{serviciosDelPlan: list<array<string, mixed>>|null, serviciosDisponibles: list<array<string, mixed>>|null}
     */
    private function serviciosDelPlan(Documento $documento): array
    {
        if ($documento->tipo !== TipoDocumento::PlanContinuidad) {
            return ['serviciosDelPlan' => null, 'serviciosDisponibles' => null];
        }

        $cubiertos = $documento->serviciosCubiertos()->orderBy('codigo')->get();

        return [
            'serviciosDelPlan' => $cubiertos
                ->map(fn (Activo $activo): array => [
                    'id' => $activo->id,
                    'codigo' => $activo->codigo,
                    'nombre' => $activo->nombre,
                ])
                ->all(),
            'serviciosDisponibles' => Activo::query()
                ->where('tipo', TipoActivo::Servicios->value)
                ->whereNotIn('id', $cubiertos->pluck('id'))
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre'])
                ->map(fn (Activo $activo): array => [
                    'valor' => (string) $activo->id,
                    'etiqueta' => "{$activo->codigo} · {$activo->nombre}",
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(Documento $documento): array
    {
        return [
            'id' => $documento->id,
            'codigo' => $documento->codigo,
            'titulo' => $documento->titulo,
            'tipo' => $documento->tipo->value,
            'tipoEtiqueta' => $documento->tipo->etiqueta(),
            'clasificacion' => $documento->clasificacion->value,
            'clasificacionEtiqueta' => $documento->clasificacion->etiqueta(),
            'sistema_id' => $documento->sistema_id,
            'sistema' => $documento->sistema?->nombre,
            'sistemaCodigo' => $documento->sistema?->codigo,
            'marco' => $documento->sistema?->marco?->nombre,
            'responsable_id' => $documento->responsable_id,
            'responsable' => $documento->responsable?->name,
            'notas' => $documento->notas,
            'periodicidad_revision_meses' => $documento->periodicidad_revision_meses,
            'exige_acuse' => $documento->exigeAcuse(),
            'redactado' => $documento->tipo->esRedactado(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializarVersion(?DocumentoVersion $version): ?array
    {
        if ($version === null) {
            return null;
        }

        return [
            'id' => $version->id,
            'numero' => $version->numero,
            'etiqueta' => $version->etiqueta(),

            /*
             * Dos estados, y no son lo mismo. `generacion*` es el ciclo de vida
             * del TRABAJO que produce el PDF —encolada, generando, fallida— y lo
             * mira el poll; `estado*` es el del DOCUMENTO —borrador, en revisión,
             * aprobado— y es lo que le importa a quien lo firma. Que el PDF se
             * haya generado bien no significa que nadie lo haya aprobado.
             */
            'generacion' => $version->estado_generacion->value,
            'generacionEtiqueta' => $version->estado_generacion->etiqueta(),
            'generacionTono' => $version->estado_generacion->tono(),
            'enCurso' => $version->estado_generacion->enCurso(),

            'estado' => $version->estado->value,
            'estadoEtiqueta' => $version->estado->etiqueta(),
            'estadoTono' => $version->estado->tono(),
            'estadoIcono' => $version->estado->icono(),

            /*
             * A dónde puede ir desde aquí. Lo manda el servidor para que el
             * cliente no tenga que reconstruir la máquina de estados: es el mismo
             * criterio que las transiciones de una tarjeta del tablero, y por el
             * mismo motivo —un gesto que se acepta y luego falla se explica mucho
             * peor que uno que no se ofrece—.
             */
            'transiciones' => array_map(
                static fn (EstadoDocumental $destino): array => [
                    'valor' => $destino->value,
                    'etiqueta' => $destino->etiqueta(),
                    'tono' => $destino->tono(),
                    'icono' => $destino->icono(),
                ],
                $version->estado->transicionesPermitidas(),
            ),

            'aprobadaPor' => $version->aprobadaPor?->name,
            'aprobadaEn' => $version->aprobada_en?->format('d/m/Y'),
            'notaAprobacion' => $version->nota_aprobacion,
            'motivoRechazo' => $version->motivo_rechazo,
            'proximaRevision' => $version->fecha_proxima_revision?->format('d/m/Y'),

            'descargable' => $version->tieneFichero(),
            // La huella entera, no un prefijo: es lo que se contrasta con el
            // fichero que se le entrega al auditor.
            'huella' => $version->hash_sha256,
            'tamano' => $version->tamano,
            /*
             * El recuento lo escribe el servidor y no la plantilla, porque no
             * significa lo mismo en todos los documentos: en una declaración son
             * los requisitos con sus excluidos e implantados, y en un plan de
             * adecuación las filas son **todas** pendientes por construcción, así
             * que «0 excluidos · 0 implantados» sería un dato falso al lado del
             * enlace de descarga.
             */
            'recuento' => $this->recuentoDeVersion($version),
            'motivo' => $version->motivo,
            'error' => $version->error,
            'quien' => $version->generadaPor?->name,
            'emitida' => $version->emitida_en?->toDateTimeString(),
            'creada' => $version->created_at->toDateTimeString(),
        ];
    }

    /**
     * Qué contiene una versión, en una línea y dicho según el documento que es.
     *
     * Devuelve nulo cuando no hay nada que contar: un documento redactado no
     * tiene filas, y una versión que todavía no se ha generado tampoco.
     */
    private function recuentoDeVersion(DocumentoVersion $version): ?string
    {
        if ($version->total_requisitos === null) {
            return null;
        }

        return match ($version->documento->tipo) {
            TipoDocumento::SoaIso, TipoDocumento::DdaEns => sprintf(
                '%d requisitos · %d excluidos · %d implantados',
                $version->total_requisitos,
                (int) $version->total_excluidos,
                (int) $version->total_implantados,
            ),

            // En un plan todas las filas son pendientes: decir cuántas están
            // implantadas sería decir siempre cero, y no porque no haya ninguna.
            TipoDocumento::PlanAdecuacionEns => sprintf(
                '%d medidas pendientes',
                $version->total_requisitos,
            ),

            /*
             * El análisis del contexto es calculado y aun así no tiene tabla de
             * requisitos: lo que cuenta son cuestiones y partes interesadas, y esas
             * cifras están en la instantánea, no en las tres columnas
             * denormalizadas. Decir «0 requisitos» sería peor que no decir nada.
             */
            TipoDocumento::AnalisisContexto,
            TipoDocumento::ActaRevision,
            TipoDocumento::DeclaracionConformidadEns,
            TipoDocumento::Politica,
            TipoDocumento::Norma,
            TipoDocumento::Procedimiento,
            TipoDocumento::PlanContinuidad => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        return [
            /*
             * `marco` viaja además de valor y etiqueta para que el formulario
             * pueda avisar en el momento de que una SoA no cabe en un sistema
             * del ENS, en vez de dejar que lo diga el `FormRequest` después de
             * enviar. La validación de verdad sigue estando allí.
             */
            'sistemas' => Sistema::query()->with('marco')->orderBy('codigo')->get()
                ->map(fn (Sistema $sistema): array => [
                    'valor' => (string) $sistema->id,
                    'etiqueta' => "{$sistema->codigo} — {$sistema->nombre}",
                    'marco' => $sistema->marco?->codigo,
                ])->all(),
            'tipos' => array_map(
                static fn (TipoDocumento $tipo): array => [
                    'valor' => $tipo->value,
                    'etiqueta' => $tipo->etiqueta(),
                    'marco' => $tipo->marcoEsperado(),
                ],
                TipoDocumento::cases(),
            ),
            'clasificaciones' => array_map(
                static fn (ClasificacionDocumental $c): array => ['valor' => $c->value, 'etiqueta' => $c->etiqueta()],
                ClasificacionDocumental::cases(),
            ),
            /*
             * Acotado a la organización a mano: `User` no lleva
             * `PerteneceAOrganizacion` —la autenticación tiene que poder
             * encontrar a alguien antes de saber de qué organización es—, así que
             * aquí no hay scope global ni RLS que tapen el cruce. Sin este
             * `where`, el desplegable de responsables lista a los usuarios de
             * todos los clientes.
             */
            'responsables' => User::query()
                ->where('organizacion_id', app(ContextoOrganizacion::class)->idObligatorio())
                ->orderBy('name')
                ->get()
                ->map(fn (User $u): array => ['valor' => (string) $u->id, 'etiqueta' => $u->name])->all(),
        ];
    }
}
