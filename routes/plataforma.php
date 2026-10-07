<?php

declare(strict_types=1);

use App\Http\Controllers\Plataforma\OrganizacionController;
use App\Http\Controllers\Plataforma\PlanController;
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
        Route::redirect('/', '/plataforma/organizaciones');

        Route::get('/organizaciones', [OrganizacionController::class, 'index'])->name('organizaciones.index');
        Route::get('/organizaciones/crear', [OrganizacionController::class, 'create'])->name('organizaciones.create');
        Route::post('/organizaciones', [OrganizacionController::class, 'store'])->name('organizaciones.store');
        Route::get('/organizaciones/{organizacion}', [OrganizacionController::class, 'show'])->name('organizaciones.show');
        Route::put('/organizaciones/{organizacion}/suscripcion', [OrganizacionController::class, 'suscripcion'])
            ->name('organizaciones.suscripcion');

        // Los planes (punto 43). Sin borrar: se retiran con `activo`, porque
        // el histórico de las suscripciones apunta a ellos.
        Route::get('/planes', [PlanController::class, 'index'])->name('planes.index');
        Route::get('/planes/crear', [PlanController::class, 'create'])->name('planes.create');
        Route::post('/planes', [PlanController::class, 'store'])->name('planes.store');
        Route::get('/planes/{plan}/editar', [PlanController::class, 'edit'])->name('planes.edit');
        Route::put('/planes/{plan}', [PlanController::class, 'update'])->name('planes.update');
    });
