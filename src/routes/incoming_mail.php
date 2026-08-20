<?php

use App\Http\Controllers\IncomingMailController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\CheckRole;

// Allow all authenticated users; individual route access controlled by CheckIncomingMailAccess middleware
Route::prefix('incoming-mails')->middleware(['auth'])->group(function () {
    Route::get('/', [IncomingMailController::class, 'index'])->middleware(CheckRole::class . ':superadmin,admin,dirut,wadir')->name('incoming.index');
    Route::get('/list', [IncomingMailController::class, 'list_incoming_mails'])->middleware(CheckRole::class . ':superadmin,admin,dirut,wadir')->name('incoming.list_incoming_mails');
    Route::get('/statuses', [IncomingMailController::class, 'list_statuses'])->name('incoming.statuses');
    Route::get('/types', [IncomingMailController::class, 'list_types'])->middleware(CheckRole::class . ':superadmin,admin,dirut,wadir')->name('incoming.types');

    // Detail & view - access checked by middleware (role OR unit match via dispositions)
    Route::get('/view/{id}', [IncomingMailController::class, 'viewPage'])
        ->middleware(\App\Http\Middleware\CheckIncomingMailAccess::class)
        ->name('incoming.viewPage');

    Route::get('/show/{id}', [IncomingMailController::class, 'show'])
        ->middleware(\App\Http\Middleware\CheckIncomingMailAccess::class)
        ->name('incoming.show');

    Route::get('/preview/{id}', [IncomingMailController::class, 'preview'])
        ->middleware(\App\Http\Middleware\CheckIncomingMailAccess::class)
        ->name('incoming.preview');

    Route::post('/{id}/read', [IncomingMailController::class, 'markAsRead'])
        ->middleware(\App\Http\Middleware\CheckIncomingMailAccess::class)
        ->name('incoming.read');

    Route::get('/{id}/read-tracking', [IncomingMailController::class, 'getReadTracking'])
        ->middleware([\App\Http\Middleware\CheckIncomingMailAccess::class, CheckRole::class . ':superadmin,admin,dirut,wadir'])
        ->name('incoming.read_tracking');

    // Superadmin only routes
    Route::middleware(CheckRole::class . ':superadmin,admin')->group(function () {
        Route::get('/add', [IncomingMailController::class, 'add'])->name('incoming.add');
        Route::post('/store', [IncomingMailController::class, 'store'])->name('incoming.store');
        Route::patch('/update/{id}', [IncomingMailController::class, 'update'])->name('incoming.update');
        Route::post('/replace/{id}', [IncomingMailController::class, 'replace_document'])->name('incoming.replace');
        Route::patch('/edit-document/{id}', [IncomingMailController::class, 'edit_document'])->name('incoming.edit_document');
        Route::get('/{id}/unread-wadir', [IncomingMailController::class, 'getUnreadWadir'])->name('incoming.unread_wadir');
        Route::patch('/{id}/ready-dirut', [IncomingMailController::class, 'setReadyForDirut'])->name('incoming.ready_dirut');
    });

    // Dispositions - authenticated users can view, but only dirut/wadir can create/edit/delete
    Route::get('/{id}/dispositions', [App\Http\Controllers\DispositionController::class, 'index'])->name('incoming.dispositions.index');
    
    Route::middleware(CheckRole::class . ':dirut,wadir')->group(function () {
        Route::post('/{id}/dispositions', [App\Http\Controllers\DispositionController::class, 'store'])->name('incoming.dispositions.store');
        Route::patch('/{id}/dispositions/{disposition_id}', [App\Http\Controllers\DispositionController::class, 'update'])->name('incoming.dispositions.update');
        Route::delete('/{id}/dispositions/{disposition_id}', [App\Http\Controllers\DispositionController::class, 'destroy'])->name('incoming.dispositions.destroy');
    });
});
