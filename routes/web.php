<?php

declare(strict_types=1);

use App\Http\Controllers\ActivoController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\ContextoController;
use App\Http\Controllers\CuestionContextoController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\DocumentoCuerpoController;
use App\Http\Controllers\EvidenciaController;
use App\Http\Controllers\ImplantacionController;
use App\Http\Controllers\MetodologiaRiesgoController;
use App\Http\Controllers\NoConformidadController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\ParteInteresadaController;
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
    | Contexto de la organización (§ 4.1) y partes interesadas (4.2)
    |--------------------------------------------------------------------------
    |
    | Tres verbos y no dos. `contexto.aprobar` está aparte de `contexto.gestionar`
    | por lo mismo que `riesgos.aceptar` y `documentos.aprobar`: apuntar una
    | debilidad es trabajo operativo y declarar que ése es el contexto de la
    | organización —congelándolo, porque aprobar es lo que congela— es de
    | dirección.
    |
    | `/contexto` es la vista de lectura —la matriz del DAFO— y
    | `/contexto/cuestiones` la tabla donde se trabaja, igual que `/tareas` y
    | `/tareas/tablero`. Las rutas literales van antes que las de parámetro:
    | `analisis` y `cuestiones` no son identificadores.
    |
    | `scopeBindings()` en todo lo anidado: el scope global tapa el cruce entre
    | clientes, pero entre dos partes interesadas de la MISMA organización no hay
    | nada, y sin él `/partes-interesadas/3/requisitos/9` resolvería el requisito 9
    | fuese o no de la parte 3.
    |
    */

    Route::middleware('can:contexto.ver')->group(function (): void {
        Route::get('/contexto', [ContextoController::class, 'index'])->name('contexto.index');

        Route::get('/contexto/analisis', [ContextoController::class, 'analisis'])->name('contexto.analisis.index');

        Route::get('/contexto/cuestiones', [CuestionContextoController::class, 'index'])
            ->name('contexto.cuestiones.index');

        Route::get('/contexto/cuestiones/crear', [CuestionContextoController::class, 'create'])
            ->middleware(['can:contexto.gestionar', ExigirDosFactores::class])
            ->name('contexto.cuestiones.create');

        Route::get('/contexto/cuestiones/{cuestion}', [CuestionContextoController::class, 'show'])
            ->name('contexto.cuestiones.show');

        Route::get('/contexto/analisis/{analisis}', [ContextoController::class, 'mostrarAnalisis'])
            ->name('contexto.analisis.show');
    });

    Route::middleware(['can:contexto.gestionar', ExigirDosFactores::class])
        ->scopeBindings()
        ->group(function (): void {
            Route::put('/contexto/analisis', [ContextoController::class, 'guardarAnalisis'])
                ->name('contexto.analisis.update');

            Route::post('/contexto/cuestiones', [CuestionContextoController::class, 'store'])
                ->name('contexto.cuestiones.store');
            Route::get('/contexto/cuestiones/{cuestion}/editar', [CuestionContextoController::class, 'edit'])
                ->name('contexto.cuestiones.edit');
            Route::put('/contexto/cuestiones/{cuestion}', [CuestionContextoController::class, 'update'])
                ->name('contexto.cuestiones.update');
            Route::delete('/contexto/cuestiones/{cuestion}', [CuestionContextoController::class, 'destroy'])
                ->name('contexto.cuestiones.destroy');

            // Retirar es lo que se usa; borrar es la salida de emergencia.
            Route::post('/contexto/cuestiones/{cuestion}/retirada', [CuestionContextoController::class, 'retirar'])
                ->name('contexto.cuestiones.retirar');

            // Antes que `{riesgo}` y que `{tarea}`: «vincular» no es un identificador.
            Route::post('/contexto/cuestiones/{cuestion}/riesgos', [CuestionContextoController::class, 'vincularRiesgo'])
                ->name('contexto.cuestiones.riesgos.vincular');
            Route::delete('/contexto/cuestiones/{cuestion}/riesgos/{riesgo}', [CuestionContextoController::class, 'desvincularRiesgo'])
                ->name('contexto.cuestiones.riesgos.desvincular');

            Route::post('/contexto/cuestiones/{cuestion}/tareas/vincular', [CuestionContextoController::class, 'vincularTarea'])
                ->name('contexto.cuestiones.tareas.vincular');
            Route::post('/contexto/cuestiones/{cuestion}/tareas', [CuestionContextoController::class, 'abrirTarea'])
                ->name('contexto.cuestiones.tareas.abrir');
            Route::delete('/contexto/cuestiones/{cuestion}/tareas/{tarea}', [CuestionContextoController::class, 'desvincularTarea'])
                ->name('contexto.cuestiones.tareas.desvincular');
        });

    /*
     * Aprobar va con su permiso y con segundo factor: es lo que numera el
     * análisis, congela la instantánea y lo vuelve inmutable.
     */
    Route::middleware(['can:contexto.aprobar', ExigirDosFactores::class])->group(function (): void {
        Route::post('/contexto/analisis/{analisis}/aprobacion', [ContextoController::class, 'aprobar'])
            ->name('contexto.analisis.aprobar');
    });

    Route::middleware('can:contexto.ver')->group(function (): void {
        Route::get('/partes-interesadas', [ParteInteresadaController::class, 'index'])
            ->name('partes-interesadas.index');

        Route::get('/partes-interesadas/crear', [ParteInteresadaController::class, 'create'])
            ->middleware(['can:contexto.gestionar', ExigirDosFactores::class])
            ->name('partes-interesadas.create');

        Route::get('/partes-interesadas/{parte}', [ParteInteresadaController::class, 'show'])
            ->name('partes-interesadas.show');
    });

    Route::middleware(['can:contexto.gestionar', ExigirDosFactores::class])
        ->scopeBindings()
        ->group(function (): void {
            Route::post('/partes-interesadas', [ParteInteresadaController::class, 'store'])
                ->name('partes-interesadas.store');
            Route::get('/partes-interesadas/{parte}/editar', [ParteInteresadaController::class, 'edit'])
                ->name('partes-interesadas.edit');
            Route::put('/partes-interesadas/{parte}', [ParteInteresadaController::class, 'update'])
                ->name('partes-interesadas.update');
            Route::delete('/partes-interesadas/{parte}', [ParteInteresadaController::class, 'destroy'])
                ->name('partes-interesadas.destroy');

            Route::post('/partes-interesadas/{parte}/retirada', [ParteInteresadaController::class, 'retirar'])
                ->name('partes-interesadas.retirar');

            // La lista entera en una petición, como la de comprobación de una tarea.
            Route::put('/partes-interesadas/{parte}/requisitos', [ParteInteresadaController::class, 'guardarRequisitos'])
                ->name('partes-interesadas.requisitos.update');

            Route::post('/partes-interesadas/{parte}/requisitos/{requisito}/implantaciones', [ParteInteresadaController::class, 'vincularImplantacion'])
                ->name('partes-interesadas.implantaciones.vincular');
            Route::delete('/partes-interesadas/{parte}/requisitos/{requisito}/implantaciones/{implantacion}', [ParteInteresadaController::class, 'desvincularImplantacion'])
                ->name('partes-interesadas.implantaciones.desvincular');
        });

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
    | Auditorías
    |--------------------------------------------------------------------------
    |
    | § 4.12, y la cláusula 9.2 de ISO. El rol `Auditor` lee y no escribe: es el
    | auditor externo que viene de fuera, y quien registra la auditoría interna es
    | el responsable de seguridad. Dejarle escribir sería que quien audita
    | redactara el acta de su propia auditoría.
    |
    | `scopeBindings()` en todo lo que cuelga de `{auditoria}`: el scope global de
    | organización tapa el cruce entre clientes, y entre dos auditorías de la
    | misma organización no hay nada que lo tape. Sin él, una línea de la checklist
    | de otra auditoría se resolvería sin más.
    |
    */

    Route::middleware('can:auditorias.ver')->group(function (): void {
        Route::get('/auditorias', [AuditoriaController::class, 'index'])->name('auditorias.index');

        // Antes que `{auditoria}`, para que `crear` no se lea como un id.
        Route::get('/auditorias/crear', [AuditoriaController::class, 'create'])
            ->middleware(['can:auditorias.gestionar', ExigirDosFactores::class])
            ->name('auditorias.create');

        Route::get('/auditorias/{auditoria}', [AuditoriaController::class, 'show'])->name('auditorias.show');

        /*
         * La checklist es una pantalla propia y no un bloque de la ficha: son 52
         * medidas en categoría básica y unas 122 en un sistema de ISO, y a ese
         * tamaño hacen falta filtros, orden y marcado en bloque. Mismo criterio
         * que las tres pantallas del plan de acción.
         */
        Route::get('/auditorias/{auditoria}/checklist', [AuditoriaController::class, 'checklist'])
            ->name('auditorias.checklist');
    });

    Route::middleware(['can:auditorias.gestionar', ExigirDosFactores::class])
        ->scopeBindings()
        ->group(function (): void {
            Route::post('/auditorias', [AuditoriaController::class, 'store'])->name('auditorias.store');
            Route::get('/auditorias/{auditoria}/editar', [AuditoriaController::class, 'edit'])->name('auditorias.edit');
            Route::put('/auditorias/{auditoria}', [AuditoriaController::class, 'update'])->name('auditorias.update');
            Route::delete('/auditorias/{auditoria}', [AuditoriaController::class, 'destroy'])->name('auditorias.destroy');

            Route::post('/auditorias/{auditoria}/estado', [AuditoriaController::class, 'transicion'])
                ->name('auditorias.transicion');

            Route::post('/auditorias/{auditoria}/checklist', [AuditoriaController::class, 'precargar'])
                ->name('auditorias.checklist.precargar');

            // Antes que `{punto}`: `resultado` no es un identificador.
            Route::post('/auditorias/{auditoria}/checklist/resultado', [AuditoriaController::class, 'marcarConformes'])
                ->name('auditorias.checklist.conformes');

            Route::put('/auditorias/{auditoria}/checklist/{punto}', [AuditoriaController::class, 'revisar'])
                ->name('auditorias.checklist.revisar');

            Route::post('/auditorias/{auditoria}/hallazgos', [AuditoriaController::class, 'registrarHallazgo'])
                ->name('auditorias.hallazgos.registrar');
            Route::delete('/auditorias/{auditoria}/hallazgos/{hallazgo}', [AuditoriaController::class, 'retirarHallazgo'])
                ->name('auditorias.hallazgos.retirar');
        });

    /*
    |--------------------------------------------------------------------------
    | No conformidades
    |--------------------------------------------------------------------------
    |
    | § 4.13, y la cláusula 10.2 de ISO. La otra mitad del módulo de auditorías:
    | un hallazgo sin tratamiento detrás no cierra ningún ciclo.
    |
    | **Tres permisos y no dos.** `verificar` está separado de `gestionar` porque
    | comprobar que una acción correctiva funcionó no puede hacerlo quien la
    | ejecutó, que es la cláusula 10.2 e) entera. La ruta de transición es una
    | sola —el destino manda—, así que ese permiso se comprueba dentro del
    | controlador y no aquí.
    |
    | `scopeBindings()` en lo que cuelga de `{no_conformidad}`: la acción
    | correctiva de otra no conformidad no se desvincula desde ésta.
    |
    */

    Route::middleware('can:no_conformidades.ver')->group(function (): void {
        Route::get('/no-conformidades', [NoConformidadController::class, 'index'])
            ->name('no-conformidades.index');

        // Antes que `{no_conformidad}`, para que `crear` no se lea como un id.
        Route::get('/no-conformidades/crear', [NoConformidadController::class, 'create'])
            ->middleware(['can:no_conformidades.gestionar', ExigirDosFactores::class])
            ->name('no-conformidades.create');

        Route::get('/no-conformidades/{no_conformidad}', [NoConformidadController::class, 'show'])
            ->name('no-conformidades.show');
    });

    Route::middleware(['can:no_conformidades.gestionar', ExigirDosFactores::class])
        ->scopeBindings()
        ->group(function (): void {
            Route::post('/no-conformidades', [NoConformidadController::class, 'store'])
                ->name('no-conformidades.store');
            Route::get('/no-conformidades/{no_conformidad}/editar', [NoConformidadController::class, 'edit'])
                ->name('no-conformidades.edit');
            Route::put('/no-conformidades/{no_conformidad}', [NoConformidadController::class, 'update'])
                ->name('no-conformidades.update');
            Route::delete('/no-conformidades/{no_conformidad}', [NoConformidadController::class, 'destroy'])
                ->name('no-conformidades.destroy');

            /*
             * Una sola ruta para todo el ciclo, verificar incluido: el destino es
             * lo que decide, y el permiso extra lo comprueba el controlador. Dos
             * rutas obligarían al cliente a saber cuál usar para cada transición.
             */
            Route::post('/no-conformidades/{no_conformidad}/estado', [NoConformidadController::class, 'transicion'])
                ->name('no-conformidades.transicion');

            // Antes que `{tarea}`: `vincular` no es un identificador.
            Route::post('/no-conformidades/{no_conformidad}/acciones/vincular', [NoConformidadController::class, 'vincularAccion'])
                ->name('no-conformidades.acciones.vincular');

            Route::post('/no-conformidades/{no_conformidad}/acciones', [NoConformidadController::class, 'abrirAccion'])
                ->name('no-conformidades.acciones.abrir');
            Route::delete('/no-conformidades/{no_conformidad}/acciones/{tarea}', [NoConformidadController::class, 'desvincularAccion'])
                ->name('no-conformidades.acciones.desvincular');
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
