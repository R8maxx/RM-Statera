<?php

declare(strict_types=1);

use App\Http\Controllers\EvidenciaController;
use App\Http\Controllers\ImplantacionController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\SistemaController;
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
});
