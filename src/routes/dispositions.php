<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DispositionController;

Route::middleware(['auth'])->group(function () {
    Route::get('/dispositions/my', [DispositionController::class, 'my'])->name('dispositions.my');
    Route::get('/dispositions/created', [DispositionController::class, 'createdByMe'])->name('dispositions.created');
    Route::patch('/dispositions/{id}/resolve', [DispositionController::class, 'resolve'])->name('dispositions.resolve');
    Route::post('/dispositions/{id}/resolve', [DispositionController::class, 'resolve'])->name('dispositions.resolve_post');
    Route::get('/dispositions/{id}/resolve-file', [DispositionController::class, 'resolveFile'])->name('dispositions.resolve_file');
});

Route::get(
    '/dispositions/{id}/download-pdf',
    [DispositionController::class, 'downloadDispositionPdf']
)->name('dispositions.download_pdf');
