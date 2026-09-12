<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Documento\Contenido\ContenidoDocumento;
use App\Domain\Documento\EmitirVersion;
use App\Domain\Documento\Enums\ClasificacionDocumental;
use App\Domain\Documento\Enums\TipoDocumento;
use App\Domain\Documento\GenerarDocumento;
use App\Domain\Documento\Models\Documento;
use App\Domain\Documento\Models\DocumentoVersion;
use App\Domain\Documento\Narrativa\MarkdownDocumento;
use App\Domain\Documento\Narrativa\MaterializarSecciones;
use App\Domain\Documento\Render\EscritorWord;
use App\Domain\Sistema\Models\Sistema;
use App\Http\Requests\EmitirVersionRequest;
use App\Http\Requests\GenerarDocumentoRequest;
use App\Http\Requests\GuardarDocumentoRequest;
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

    public function index(Request $request): Response
    {
        return Inertia::render('documentos/Index', $this->tabla(new DocumentoRecurso, $request));
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
    public function show(Documento $documento): Response
    {
        $documento->load(['sistema.marco', 'responsable']);

        return Inertia::render('documentos/Ficha', [
            'documento' => $this->serializar($documento),
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
                ->with('generadaPor')
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
     * Convierte el borrador en una entrega, y con eso en algo inmutable.
     */
    public function emitir(EmitirVersionRequest $request, Documento $documento, EmitirVersion $emitir): RedirectResponse
    {
        $borrador = $documento->borrador()->first();

        abort_if($borrador === null, 404);

        $version = $emitir($borrador, $request->string('motivo')->value() ?: null);

        Inertia::flash('exito', "Versión v{$version->numero} emitida. A partir de ahora no se puede modificar ni regenerar.");

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
            'estado' => $version->estado_generacion->value,
            'estadoEtiqueta' => $version->estado_generacion->etiqueta(),
            'estadoTono' => $version->estado_generacion->tono(),
            'enCurso' => $version->estado_generacion->enCurso(),
            'descargable' => $version->tieneFichero(),
            'emisible' => $version->esEmisible(),
            // La huella entera, no un prefijo: es lo que se contrasta con el
            // fichero que se le entrega al auditor.
            'huella' => $version->hash_sha256,
            'tamano' => $version->tamano,
            'totalRequisitos' => $version->total_requisitos,
            'totalExcluidos' => $version->total_excluidos,
            'totalImplantados' => $version->total_implantados,
            'motivo' => $version->motivo,
            'error' => $version->error,
            'quien' => $version->generadaPor?->name,
            'emitida' => $version->emitida_en?->toDateTimeString(),
            'creada' => $version->created_at->toDateTimeString(),
        ];
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
            'responsables' => User::query()->orderBy('name')->get()
                ->map(fn (User $u): array => ['valor' => (string) $u->id, 'etiqueta' => $u->name])->all(),
        ];
    }
}
