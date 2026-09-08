<?php

declare(strict_types=1);

use App\Http\Controllers\ImplantacionController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\SistemaController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/panel');

Route::middleware('auth')->group(function (): void {
    Route::get('/panel', PanelController::class)->name('panel');

    Route::get('/sistemas', [SistemaController::class, 'index'])->name('sistemas.index');
    Route::get('/sistemas/crear', [SistemaController::class, 'create'])->name('sistemas.create');
    Route::post('/sistemas', [SistemaController::class, 'store'])->name('sistemas.store');
    Route::get('/sistemas/{sistema}/editar', [SistemaController::class, 'edit'])->name('sistemas.edit');
    Route::put('/sistemas/{sistema}', [SistemaController::class, 'update'])->name('sistemas.update');
    Route::delete('/sistemas/{sistema}', [SistemaController::class, 'destroy'])->name('sistemas.destroy');

    Route::get('/implantaciones', [ImplantacionController::class, 'index'])->name('implantaciones.index');
    Route::post('/implantaciones/estado', [ImplantacionController::class, 'cambiarEstado'])
        ->name('implantaciones.estado');
});
