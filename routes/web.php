<?php

declare(strict_types=1);

use App\Http\Controllers\ActivoController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\DocumentoCuerpoController;
use App\Http\Controllers\EvidenciaController;
use App\Http\Controllers\ImplantacionController;
use App\Http\Controllers\MetodologiaRiesgoController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\PlantillaDocumentoController;
use App\Http\Controllers\RevisionInventarioController;
use App\Http\Controllers\RiesgoController;
use App\Http\Controllers\SistemaController;
use App\Http\Controllers\TareaController;
use App\Http\Controllers\ValoracionSistemaController;
use App\Http\Middleware\ExigirDosFactores;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de la aplicación
|--------------------------------------------------------------------------
|
| Dos capas de autorización, y hacen cosas distintas:
|
| - `can:...` decide QUÉ puede hacer cada rol. La capa de recursos filtra
|   además las acciones que se serializan, pero eso es cosmética: lo que manda
|   es esto.
| - `ExigirDosFactores` decide CÓMO tiene que estar protegida la cuenta de quien
|   escribe. Va sólo en las rutas de escritura, y nunca en `/perfil`, que es
|   donde se activa el segundo factor.
|
| Y por encima de las dos, el aislamiento de organización, que no se negocia con
| permisos: ningún rol atraviesa la frontera del tenant.
|
*/

Route::redirect('/', '/panel');

Route::middleware('auth')->group(function (): void {
    Route::get('/panel', PanelController::class)->middleware('can:panel.ver')->name('panel');

    // La cuenta propia no lleva permiso: cualquiera gestiona la suya. Y no lleva
    // `ExigirDosFactores` porque es justamente la salida de ese callejón.
    Route::get('/perfil', [PerfilController::class, 'show'])->name('perfil');

    // El secreto del segundo factor y los códigos de recuperación exigen
    // reconfirmar la contraseña, igual que los endpoints de Fortify: son lo que
    // se lleva quien se siente delante de una sesión abierta.
    Route::get('/perfil/dos-factores', [PerfilController::class, 'dosFactores'])
        ->middleware('password.confirm')
        ->name('perfil.dos-factores');

    /*
    |--------------------------------------------------------------------------
    | Sistemas
    |--------------------------------------------------------------------------
    */

    Route::get('/sistemas', [SistemaController::class, 'index'])
        ->middleware('can:sistemas.ver')
        ->name('sistemas.index');

    Route::middleware(['can:sistemas.gestionar', ExigirDosFactores::class])->group(function (): void {
        Route::get('/sistemas/crear', [SistemaController::class, 'create'])->name('sistemas.create');
        Route::post('/sistemas', [SistemaController::class, 'store'])->name('sistemas.store');
        Route::get('/sistemas/{sistema}/editar', [SistemaController::class, 'edit'])->name('sistemas.edit');
        Route::put('/sistemas/{sistema}', [SistemaController::class, 'update'])->name('sistemas.update');
        Route::delete('/sistemas/{sistema}', [SistemaController::class, 'destroy'])->name('sistemas.destroy');
    });

    // La valoración va aparte del CRUD y con permiso propio: es la entrada del
    // motor, y guardarla recalcula lo que se le exige a la organización entera.
    Route::middleware(['can:sistemas.valorar', ExigirDosFactores::class])->group(function (): void {
        Route::get('/sistemas/{sistema}/valoracion', [ValoracionSistemaController::class, 'edit'])
            ->name('sistemas.valoracion.edit');
        Route::post('/sistemas/{sistema}/valoracion/simulacion', [ValoracionSistemaController::class, 'simular'])
            ->name('sistemas.valoracion.simular');
        Route::put('/sistemas/{sistema}/valoracion', [ValoracionSistemaController::class, 'update'])
            ->name('sistemas.valoracion.update');
    });

    /*
    |--------------------------------------------------------------------------
    | Implantaciones
    |--------------------------------------------------------------------------
    */

    Route::middleware('can:implantaciones.ver')->group(function (): void {
        Route::get('/implantaciones', [ImplantacionController::class, 'index'])->name('implantaciones.index');

        // La ficha se declara después de la acción masiva para que `estado` no
        // se lea como el identificador de una implantación.
        Route::get('/implantaciones/{implantacion}', [ImplantacionController::class, 'show'])
            ->name('implantaciones.show');
    });

    Route::middleware(['can:implantaciones.gestionar', ExigirDosFactores::class])->group(function (): void {
        Route::post('/implantaciones/estado', [ImplantacionController::class, 'cambiarEstado'])
            ->name('implantaciones.estado');
        Route::put('/implantaciones/{implantacion}', [ImplantacionController::class, 'update'])
            ->name('implantaciones.update');
        Route::post('/implantaciones/{implantacion}/estado', [ImplantacionController::class, 'transicion'])
            ->name('implantaciones.transicion');

        // El vínculo N:M se opera desde la ficha del requisito, que es donde
        // alguien se pregunta con qué prueba que lo cumple.
        Route::post('/implantaciones/{implantacion}/evidencias', [ImplantacionController::class, 'vincularEvidencia'])
            ->name('implantaciones.evidencias.vincular');
        Route::delete('/implantaciones/{implantacion}/evidencias/{evidencia}', [ImplantacionController::class, 'desvincularEvidencia'])
            ->name('implantaciones.evidencias.desvincular');
    });

    /*
    |--------------------------------------------------------------------------
    | Activos
    |--------------------------------------------------------------------------
    */

    Route::middleware('can:activos.ver')->group(function (): void {
        Route::get('/activos', [ActivoController::class, 'index'])->name('activos.index');

        // Antes que `{activo}`, para que `crear` y `etiquetas` no se lean como
        // identificadores de activo.
        Route::get('/activos/crear', [ActivoController::class, 'create'])
            ->middleware(['can:activos.gestionar', ExigirDosFactores::class])
            ->name('activos.create');

        // Las etiquetas sólo se leen y se imprimen: no escriben nada, así que
        // van con `ver` y sin segundo factor.
        Route::get('/activos/etiquetas', [ActivoController::class, 'etiquetas'])
            ->name('activos.etiquetas');

        Route::get('/activos/{activo}', [ActivoController::class, 'show'])->name('activos.show');
    });

    Route::middleware(['can:activos.gestionar', ExigirDosFactores::class])->group(function (): void {
        // Antes que `/activos/{activo}` en POST no hace falta —los verbos son
        // distintos—, pero se declara aquí junto al resto de la escritura.
        Route::post('/activos/revision', [ActivoController::class, 'marcarRevisados'])
            ->name('activos.revision');

        Route::post('/activos', [ActivoController::class, 'store'])->name('activos.store');
        Route::get('/activos/{activo}/editar', [ActivoController::class, 'edit'])->name('activos.edit');
        Route::put('/activos/{activo}', [ActivoController::class, 'update'])->name('activos.update');
        Route::delete('/activos/{activo}', [ActivoController::class, 'destroy'])->name('activos.destroy');

        // El grafo se opera desde la ficha del activo, que es donde alguien se
        // pregunta qué se cae si esto se cae.
        Route::post('/activos/{activo}/dependencias', [ActivoController::class, 'vincularDependencia'])
            ->name('activos.dependencias.vincular');
        Route::delete('/activos/{activo}/dependencias/{dependencia}', [ActivoController::class, 'desvincularDependencia'])
            ->name('activos.dependencias.desvincular');
    });

    /*
    |--------------------------------------------------------------------------
    | Revisiones del inventario
    |--------------------------------------------------------------------------
    |
    | Sin permiso propio: revisar el inventario es gestionarlo, y el enum de
    | permisos declara dos verbos por módulo a propósito.
    |
    */

    Route::middleware('can:activos.ver')->group(function (): void {
        Route::get('/revisiones', [RevisionInventarioController::class, 'index'])->name('revisiones.index');
    });

    Route::middleware(['can:activos.gestionar', ExigirDosFactores::class])->group(function (): void {
        Route::get('/revisiones/crear', [RevisionInventarioController::class, 'create'])->name('revisiones.create');
        Route::post('/revisiones', [RevisionInventarioController::class, 'store'])->name('revisiones.store');
        Route::get('/revisiones/{revision}/editar', [RevisionInventarioController::class, 'edit'])->name('revisiones.edit');
        Route::put('/revisiones/{revision}', [RevisionInventarioController::class, 'update'])->name('revisiones.update');
        Route::delete('/revisiones/{revision}', [RevisionInventarioController::class, 'destroy'])->name('revisiones.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Evidencias
    |--------------------------------------------------------------------------
    */

    Route::middleware('can:evidencias.ver')->group(function (): void {
        Route::get('/evidencias', [EvidenciaController::class, 'index'])->name('evidencias.index');

        // Antes que `{evidencia}`, para que `crear` no se lea como un id.
        Route::get('/evidencias/crear', [EvidenciaController::class, 'create'])
            ->middleware(['can:evidencias.gestionar', ExigirDosFactores::class])
            ->name('evidencias.create');

        Route::get('/evidencias/{evidencia}', [EvidenciaController::class, 'show'])->name('evidencias.show');

        // Redirige a una URL firmada de corta duración. El bucket es privado y
        // el acceso pasa por el scope de organización, no por saberse la ruta.
        Route::get('/evidencias/{evidencia}/descargar', [EvidenciaController::class, 'descargar'])
            ->name('evidencias.descargar');
    });

    Route::middleware(['can:evidencias.gestionar', ExigirDosFactores::class])->group(function (): void {
        Route::post('/evidencias', [EvidenciaController::class, 'store'])->name('evidencias.store');
        Route::get('/evidencias/{evidencia}/editar', [EvidenciaController::class, 'edit'])->name('evidencias.edit');
        Route::put('/evidencias/{evidencia}', [EvidenciaController::class, 'update'])->name('evidencias.update');
        Route::delete('/evidencias/{evidencia}', [EvidenciaController::class, 'destroy'])->name('evidencias.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Análisis de riesgos
    |--------------------------------------------------------------------------
    |
    | Tres verbos y no dos. `riesgos.aceptar` está aparte de `riesgos.gestionar`
    | porque ISO 27001 6.1.3 f) exige que el propietario del riesgo apruebe el
    | residual: un técnico que registra y puntúa riesgos no debe poder firmar uno.
    | Y cubre también la metodología, que es la otra decisión de dirección — fijar
    | el apetito de riesgo es decidir de antemano qué se va a poder aceptar.
    |
    */

    Route::middleware('can:riesgos.ver')->group(function (): void {
        Route::get('/riesgos', [RiesgoController::class, 'index'])->name('riesgos.index');

        // Antes que `{riesgo}`, para que `metodologia` y `crear` no se lean como
        // identificadores.
        Route::get('/riesgos/metodologia', [MetodologiaRiesgoController::class, 'edit'])
            ->name('riesgos.metodologia.edit');

        Route::get('/riesgos/crear', [RiesgoController::class, 'create'])
            ->middleware(['can:riesgos.gestionar', ExigirDosFactores::class])
            ->name('riesgos.create');

        Route::get('/riesgos/{riesgo}', [RiesgoController::class, 'show'])->name('riesgos.show');
    });

    Route::middleware(['can:riesgos.gestionar', ExigirDosFactores::class])->group(function (): void {
        Route::post('/riesgos', [RiesgoController::class, 'store'])->name('riesgos.store');
        Route::get('/riesgos/{riesgo}/editar', [RiesgoController::class, 'edit'])->name('riesgos.edit');
        Route::put('/riesgos/{riesgo}', [RiesgoController::class, 'update'])->name('riesgos.update');
        Route::delete('/riesgos/{riesgo}', [RiesgoController::class, 'destroy'])->name('riesgos.destroy');

        // La valoración va por su ruta y no por el formulario del riesgo: es lo
        // que jubila la anterior y congela la escala con la que se midió.
        Route::post('/riesgos/{riesgo}/valoracion', [RiesgoController::class, 'valorar'])
            ->name('riesgos.valorar');

        Route::post('/riesgos/{riesgo}/salvaguardas', [RiesgoController::class, 'vincularSalvaguarda'])
            ->name('riesgos.salvaguardas.vincular');
        Route::delete('/riesgos/{riesgo}/salvaguardas/{implantacion}', [RiesgoController::class, 'desvincularSalvaguarda'])
            ->name('riesgos.salvaguardas.desvincular');
    });

    Route::middleware(['can:riesgos.aceptar', ExigirDosFactores::class])->group(function (): void {
        Route::post('/riesgos/{riesgo}/aceptacion', [RiesgoController::class, 'aceptar'])
            ->name('riesgos.aceptar');

        Route::put('/riesgos/metodologia', [MetodologiaRiesgoController::class, 'update'])
            ->name('riesgos.metodologia.update');
    });

    /*
    |--------------------------------------------------------------------------
    | Plan de acción
    |--------------------------------------------------------------------------
    */

    Route::middleware('can:tareas.ver')->group(function (): void {
        Route::get('/tareas', [TareaController::class, 'index'])->name('tareas.index');

        /*
         * Las otras dos presentaciones del mismo plan, cada una con su ruta.
         *
         * No son pestañas de `/tareas`: el servidor manda datos distintos en
         * cada una —el tablero agrupa, el calendario acota por mes— y así se
         * pueden enlazar y compartir. Mismo criterio que `activos.etiquetas`.
         *
         * Van antes que `{tarea}` para que no se lean como identificadores.
         */
        Route::get('/tareas/tablero', [TareaController::class, 'tablero'])->name('tareas.tablero');
        Route::get('/tareas/calendario', [TareaController::class, 'calendario'])->name('tareas.calendario');

        // Antes que `{tarea}`, para que `crear` no se lea como un id.
        Route::get('/tareas/crear', [TareaController::class, 'create'])
            ->middleware(['can:tareas.gestionar', ExigirDosFactores::class])
            ->name('tareas.create');

        Route::get('/tareas/{tarea}', [TareaController::class, 'show'])->name('tareas.show');
    });

    Route::middleware(['can:tareas.gestionar', ExigirDosFactores::class])->group(function (): void {
        // Antes que `/tareas/{tarea}`: `estado` no es un identificador.
        Route::post('/tareas/estado', [TareaController::class, 'estado'])->name('tareas.estado');

        Route::post('/tareas', [TareaController::class, 'store'])->name('tareas.store');
        Route::get('/tareas/{tarea}/editar', [TareaController::class, 'edit'])->name('tareas.edit');
        Route::put('/tareas/{tarea}', [TareaController::class, 'update'])->name('tareas.update');
        Route::delete('/tareas/{tarea}', [TareaController::class, 'destroy'])->name('tareas.destroy');

        // El estado va por su ruta y no por el formulario: es lo que registra la
        // transición y ajusta la fecha de cierre.
        Route::post('/tareas/{tarea}/estado', [TareaController::class, 'transicion'])
            ->name('tareas.transicion');

        // La lista de comprobación llega entera: añadir, renombrar, marcar,
        // reordenar y borrar son la misma operación.
        Route::put('/tareas/{tarea}/subtareas', [TareaController::class, 'subtareas'])
            ->name('tareas.subtareas');

        Route::post('/tareas/{tarea}/implantaciones', [TareaController::class, 'vincular'])
            ->name('tareas.implantaciones.vincular');
        Route::delete('/tareas/{tarea}/implantaciones/{implantacion}', [TareaController::class, 'desvincular'])
            ->name('tareas.implantaciones.desvincular');
    });

    /*
    |--------------------------------------------------------------------------
    | Documentos
    |--------------------------------------------------------------------------
    */

    Route::middleware('can:documentos.ver')->group(function (): void {
        Route::get('/documentos', [DocumentoController::class, 'index'])->name('documentos.index');

        // Antes que `{documento}`, para que `crear` no se lea como un id.
        Route::get('/documentos/crear', [DocumentoController::class, 'create'])
            ->middleware(['can:documentos.generar', ExigirDosFactores::class])
            ->name('documentos.create');

        Route::get('/documentos/{documento}', [DocumentoController::class, 'show'])->name('documentos.show');

        /*
         * `scopeBindings()` acota la versión a su documento: sin él, `{version}`
         * se resolvería globalmente y una versión de OTRO documento se
         * descargaría desde una URL que no le corresponde. RLS sigue tapando el
         * cruce entre organizaciones; esto tapa el cruce dentro de la misma.
         */
        Route::get('/documentos/{documento}/versiones/{version}/descargar', [DocumentoController::class, 'descargar'])
            ->scopeBindings()
            ->name('documentos.versiones.descargar');

        /*
         * El mismo PDF, para mirarlo dentro de la aplicación en vez de bajarlo.
         * Lo usa «Ver el PDF» del editor: la paginación real es lo único que la
         * hoja del editor no puede enseñar.
         */
        Route::get('/documentos/{documento}/versiones/{version}/ver', [DocumentoController::class, 'ver'])
            ->scopeBindings()
            ->name('documentos.versiones.ver');

        /*
         * El mismo documento en Word, como copia de trabajo. El entregable
         * archivable sigue siendo el PDF/A: esto no se almacena ni se versiona.
         */
        Route::get('/documentos/{documento}/versiones/{version}/word', [DocumentoController::class, 'word'])
            ->scopeBindings()
            ->name('documentos.versiones.word');
    });

    Route::middleware(['can:documentos.generar', ExigirDosFactores::class])->group(function (): void {
        Route::post('/documentos', [DocumentoController::class, 'store'])->name('documentos.store');
        Route::get('/documentos/{documento}/editar', [DocumentoController::class, 'edit'])->name('documentos.edit');
        Route::put('/documentos/{documento}', [DocumentoController::class, 'update'])->name('documentos.update');
        Route::delete('/documentos/{documento}', [DocumentoController::class, 'destroy'])->name('documentos.destroy');

        // Encola: generar una SoA de noventa y tres controles nunca pasa por el
        // ciclo de petición.
        Route::post('/documentos/{documento}/generar', [DocumentoController::class, 'generar'])
            ->name('documentos.generar');

        /*
         * Aquí había un `/emitir`. Ya no: desde el § 4.5 **aprobar es lo que
         * emite**, y sale por su propia ruta con su propio permiso. Dejarla
         * abierta habría sido una puerta lateral para entregar sin firma, que es
         * justo lo que el flujo existe para impedir.
         */
    });

    /*
    |--------------------------------------------------------------------------
    | La aprobación (§ 4.5)
    |--------------------------------------------------------------------------
    |
    | Firmar es lo que numera la versión, congela el PDF y lo mueve a `emitidas/`.
    | No es un matiz de permisos: es la razón por la que ISO pide la aprobación, y
    | por eso no cuelga de `documentos.generar`.
    |
    | El rechazo va con el mismo permiso: decir que no es la otra mitad de decidir.
    |
    */

    Route::middleware(['can:documentos.aprobar', ExigirDosFactores::class])->group(function (): void {
        Route::post('/documentos/{documento}/versiones/{version}/aprobar', [DocumentoController::class, 'aprobar'])
            ->scopeBindings()
            ->name('documentos.versiones.aprobar');

        Route::post('/documentos/{documento}/versiones/{version}/rechazar', [DocumentoController::class, 'rechazar'])
            ->scopeBindings()
            ->name('documentos.versiones.rechazar');
    });

    /*
    |--------------------------------------------------------------------------
    | El acuse de lectura (§ 4.5)
    |--------------------------------------------------------------------------
    |
    | **Sin permiso propio y sin segundo factor**, y es la única escritura del
    | producto que va así. Es el mismo criterio que `/perfil`: se escribe sobre uno
    | mismo, no redefine nada de la organización y no hay nada que otra persona
    | pueda ganar haciéndolo por ti. Exigir aquí el segundo factor convertiría en
    | un trámite de dos pasos lo que tiene que costar un clic, y un acuse que
    | cuesta se deja de firmar.
    |
    */

    Route::middleware('can:documentos.ver')->group(function (): void {
        Route::post('/documentos/{documento}/versiones/{version}/acuse', [DocumentoController::class, 'acusar'])
            ->scopeBindings()
            ->name('documentos.versiones.acuse');
    });

    /*
    |--------------------------------------------------------------------------
    | Mandar a revisión
    |--------------------------------------------------------------------------
    |
    | Va con `redactar` y no con `generar`: es el final de escribir el documento
    | —«esto ya está, que lo mire quien firma»— y no el principio de entregarlo.
    | Quien lo redacta tiene que poder soltarlo sin depender de nadie.
    |
    */

    Route::middleware(['can:documentos.redactar', ExigirDosFactores::class])->group(function (): void {
        Route::post('/documentos/{documento}/revision', [DocumentoController::class, 'revisar'])
            ->name('documentos.revision');
    });

    /*
    |--------------------------------------------------------------------------
    | El cuerpo del documento
    |--------------------------------------------------------------------------
    |
    | Redactar no es lo mismo que generar: el técnico que prepara el documento
    | escribe su introducción, y quien lo entrega es otro.
    |
    | Aquí había además `documentos.textos.*`, que editaba once huecos narrativos
    | sueltos. Se retiró: desde que el documento entero es editable no quedaba
    | ningún enlace a esa pantalla, pero seguía alcanzable por URL y escribía en
    | una tabla que la generación ya no lee.
    */

    Route::middleware(['can:documentos.redactar', ExigirDosFactores::class])->group(function (): void {
        Route::get('/documentos/{documento}/cuerpo', [DocumentoCuerpoController::class, 'edit'])
            ->name('documentos.cuerpo.edit');
        Route::put('/documentos/{documento}/cuerpo', [DocumentoCuerpoController::class, 'update'])
            ->name('documentos.cuerpo.update');
    });

    /*
    |--------------------------------------------------------------------------
    | Plantillas de documento
    |--------------------------------------------------------------------------
    |
    | Los textos base de la organización (§ 4.5). Tocar esto decide cómo empiezan
    | TODOS los documentos futuros, así que lleva permiso propio.
    |
    | `{tipo}` se resuelve con el enum `TipoDocumento`, no con una cadena suelta:
    | un valor inventado responde 404 sin llegar al controlador.
    */

    Route::middleware(['can:documentos.plantillas', ExigirDosFactores::class])->group(function (): void {
        Route::get('/plantillas-documento', [PlantillaDocumentoController::class, 'index'])
            ->name('plantillas.index');
        Route::get('/plantillas-documento/{tipo}', [PlantillaDocumentoController::class, 'edit'])
            ->name('plantillas.edit');
        Route::put('/plantillas-documento/{tipo}', [PlantillaDocumentoController::class, 'update'])
            ->name('plantillas.update');
        Route::delete('/plantillas-documento/{tipo}/{seccion}', [PlantillaDocumentoController::class, 'restablecer'])
            ->name('plantillas.restablecer');
    });
});
