<?php

use App\Http\Controllers\TilakaProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('tilaka')->middleware(['auth'])->group(function () {
    // Profile management
    Route::get('/profile', [TilakaProfileController::class, 'show'])->name('tilaka.profile.show');
    Route::post('/profile', [TilakaProfileController::class, 'store'])->name('tilaka.profile.store');
    Route::post('/profile/submit', [TilakaProfileController::class, 'submit'])->name('tilaka.profile.submit');

    Route::get('/profile/tilaka_userregstatus', [TilakaProfileController::class, 'userregstatus'])->name('tilaka.profile.userregstatus');

    // Document management
    Route::post('/profile/upload', [TilakaProfileController::class, 'uploadDocument'])->name('tilaka.profile.upload');
    Route::get('/profile/download/{documentType}', [TilakaProfileController::class, 'downloadDocument'])->name('tilaka.profile.download');
    Route::get('/profile/preview/{documentType}', [TilakaProfileController::class, 'previewDocument'])->name('tilaka.profile.preview');

    // UI Pages
    Route::get('/', [TilakaProfileController::class, 'index'])->name('tilaka.index');
});
