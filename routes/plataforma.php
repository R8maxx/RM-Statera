<?php

declare(strict_types=1);

use App\Http\Controllers\Plataforma\AdministradorController;
use App\Http\Controllers\Plataforma\CuadroDeMandoController;
use App\Http\Controllers\Plataforma\OrganizacionController;
use App\Http\Controllers\Plataforma\PlanController;
use App\Http\Controllers\Plataforma\RescateController;
use App\Http\Controllers\Plataforma\SoporteController;
use App\Http\Controllers\Plataforma\TrazaController;
use App\Http\Middleware\SoloPlataforma;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| La plataforma (punto 41)
|--------------------------------------------------------------------------
|
| Lo que hace quien administra Statera: dar de alta organizaciones cliente y
| ver su ficha comercial. Nada de esto cuelga del contexto de organización —el
| administrador no tiene ninguna—, y nada lee una tabla del tenant.
|
| `SoloPlataforma` en lugar de `can:`: los permisos de spatie viven en el «team»
| de una organización, y el administrador no pertenece a ninguna. Exige además
| el segundo factor también para leer, como `/cuentas`.
|
| `{organizacion}` se resuelve con el binding implícito y sin acotar, que aquí
| es lo correcto: `organizaciones` es la raíz del tenant, sin scope ni RLS, y a
| estas rutas sólo llega la plataforma.
|
*/

Route::middleware(['auth', SoloPlataforma::class])
    ->prefix('plataforma')
    ->name('plataforma.')
    ->group(function (): void {
        /*
        | Cada ruta exige una capacidad (punto 48), que decide el perfil de quien
        | administra: `plataforma:<capacidad>`. `PerfilesTest` recorre estas rutas
        | y se pone rojo con una que no lleve ninguna. La única excepción es salir
        | del soporte: quien está dentro tiene que poder salir siempre.
        */
        Route::post('/soporte/salir', [SoporteController::class, 'salir'])->name('soporte.salir');

        Route::middleware('plataforma:clientes.ver')->group(function (): void {
            // La casa: lo que requiere atención hoy (punto 53).
            Route::get('/', [CuadroDeMandoController::class, 'index'])->name('inicio');
            Route::get('/organizaciones', [OrganizacionController::class, 'index'])->name('organizaciones.index');
            Route::get('/organizaciones/{organizacion}', [OrganizacionController::class, 'show'])
                ->whereNumber('organizacion')
                ->name('organizaciones.show');
        });

        Route::middleware('plataforma:clientes.gestionar')->group(function (): void {
            Route::get('/organizaciones/crear', [OrganizacionController::class, 'create'])->name('organizaciones.create');
            Route::post('/organizaciones', [OrganizacionController::class, 'store'])->name('organizaciones.store');
            Route::put('/organizaciones/{organizacion}/suscripcion', [OrganizacionController::class, 'suscripcion'])
                ->name('organizaciones.suscripcion');
            // `{cuentaId}` y no `{cuenta}`: ése lo resuelve el binding global, acotado a
            // la organización del contexto, que aquí no hay. Se acota en el controlador.
            Route::post('/organizaciones/{organizacion}/cuentas/{cuentaId}/reenviar', [OrganizacionController::class, 'reenviar'])
                ->whereNumber('cuentaId')
                ->name('organizaciones.reenviar');
        });

        // La baja (punto 46): un estado que se deshace, nunca un borrado.
        Route::middleware('plataforma:clientes.baja')->group(function (): void {
            Route::post('/organizaciones/{organizacion}/baja', [OrganizacionController::class, 'darDeBaja'])
                ->name('organizaciones.baja');
            Route::post('/organizaciones/{organizacion}/reactivar', [OrganizacionController::class, 'reactivar'])
                ->name('organizaciones.reactivar');
        });

        // El soporte (punto 44): sólo por la ventana que abre el cliente, y
        // dentro sólo se lee.
        Route::post('/organizaciones/{organizacion}/soporte', [SoporteController::class, 'entrar'])
            ->middleware('plataforma:soporte.entrar')
            ->name('soporte.entrar');

        // Quién administra la plataforma (punto 49). `{administrador}` es un
        // entero que se busca sólo entre las cuentas de la plataforma.
        Route::middleware('plataforma:administradores.gestionar')->group(function (): void {
            Route::get('/administradores', [AdministradorController::class, 'index'])->name('administradores.index');
            Route::post('/administradores', [AdministradorController::class, 'store'])->name('administradores.store');
            Route::put('/administradores/{administrador}/perfil', [AdministradorController::class, 'perfil'])
                ->whereNumber('administrador')
                ->name('administradores.perfil');
            Route::post('/administradores/{administrador}/retirar', [AdministradorController::class, 'retirar'])
                ->whereNumber('administrador')
                ->name('administradores.retirar');
        });

        // Rescatar cuentas con dos personas (punto 52): pedir desde la ficha
        // del cliente, ejecutar o rechazar desde Solicitudes.
        Route::middleware('plataforma:cuentas.rescatar')->group(function (): void {
            Route::post('/organizaciones/{organizacion}/rescates', [RescateController::class, 'store'])
                ->name('rescates.store');
            Route::get('/solicitudes', [RescateController::class, 'index'])->name('solicitudes.index');
            Route::post('/solicitudes/{solicitud}/ejecutar', [RescateController::class, 'ejecutar'])->name('solicitudes.ejecutar');
            Route::post('/solicitudes/{solicitud}/rechazar', [RescateController::class, 'rechazar'])->name('solicitudes.rechazar');
        });

        // La traza de la plataforma, consultable (punto 50).
        Route::get('/traza', [TrazaController::class, 'index'])
            ->middleware('plataforma:traza.ver')
            ->name('traza.index');

        // Los planes (punto 43). Sin borrar: se retiran con `activo`, porque
        // el histórico de las suscripciones apunta a ellos.
        Route::middleware('plataforma:planes.gestionar')->group(function (): void {
            Route::get('/planes', [PlanController::class, 'index'])->name('planes.index');
            Route::get('/planes/crear', [PlanController::class, 'create'])->name('planes.create');
            Route::post('/planes', [PlanController::class, 'store'])->name('planes.store');
            Route::get('/planes/{plan}/editar', [PlanController::class, 'edit'])->name('planes.edit');
            Route::put('/planes/{plan}', [PlanController::class, 'update'])->name('planes.update');
        });
    });
