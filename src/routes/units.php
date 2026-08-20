<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UnitController;
use App\Http\Middleware\CheckRole;

// Public (authenticated) endpoint for retrieving units list - no CheckRole required
Route::prefix('units')->middleware(['auth'])->group(function () {
    Route::get('/list', [UnitController::class, 'list'])->name('units.list');
});

// Management endpoints protected by superadmin role
Route::prefix('units')->middleware(['auth', CheckRole::class . ':superadmin'])->group(function () {
    Route::get('/', [UnitController::class, 'index'])->name('units.index');
    Route::post('/', [UnitController::class, 'store'])->name('units.store');
    Route::patch('/{id}', [UnitController::class, 'update'])->name('units.update');
    Route::delete('/{id}', [UnitController::class, 'destroy'])->name('units.destroy');
});
