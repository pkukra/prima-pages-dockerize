<?php

use App\Http\Controllers\Document\DocumentController;
use App\Http\Controllers\Document\DocumentSignController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\CheckRole;

Route::prefix('documents')->middleware(['auth'])->group(function () {
    Route::middleware([CheckRole::class . ':admin'])->group(function () {
        Route::get('/hash', [DocumentController::class, 'hash'])->name('docu.hash');
        Route::get('/list_owners', [DocumentController::class, 'list_owners'])->name('docu.list_owners');
        Route::get('/list_types', [DocumentController::class, 'list_types'])->name('docu.list_types');
        Route::get('/list_signers', [DocumentController::class, 'list_signers'])->name('docu.list_signers');

        Route::get('/add', [DocumentController::class, 'add'])->name('docu.add');
        Route::post('/store', [DocumentController::class, 'store'])->name('docu.store');
        Route::post('/{id}/add-signers', [DocumentController::class, 'add_signers'])->name('docu.add_signers');
        Route::delete('/{id}/signers/{userId}', [DocumentController::class, 'remove_signer'])->name('docu.remove_signer');
    });

    Route::get('/', [DocumentController::class, 'index'])->name('docu.index');
    Route::get('/list_documents', [DocumentController::class, 'list_documents'])->name('docu.list_documents');
    Route::get('/view/{id}', [DocumentController::class, 'viewPage'])->name('docu.viewPage');
    Route::get('/show/{id}', [DocumentController::class, 'show'])->name('docu.show');
    Route::get('/preview/{id}', [DocumentController::class, 'preview'])->name('docu.preview');
    Route::get('/download/{id}', [DocumentController::class, 'download'])->name('docu.download');

    Route::post('/document/{id}/add_template', [DocumentSignController::class, 'add_template'])->name('docu.add_template');
    Route::post('/document/{id}/sign', [DocumentSignController::class, 'sign'])->name('docu.sign');
});
