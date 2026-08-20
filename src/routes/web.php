<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TilakaDebugController;
use App\Http\Controllers\GmailSampleController;

// use App\Http\Controllers\TilakaController;
// use App\Http\Middleware\CheckRole;
// use App\Http\Controllers\ICDImportController;

Route::get('/x', function () {
    return Inertia::render('Dashboard');
})->name('dashboardx');

Route::get('/', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/studentsdashboard', [StudentController::class, 'index'])->name('studentsdashboard.index');
    Route::post('/addStudent', [StudentController::class, 'store'])->name('addStudent.store');
    Route::patch('/updateStudent/{id}', [StudentController::class, 'update'])->name('updateStudent.update');
    Route::delete('/deleteStudent/{id}', [StudentController::class, 'destroy'])->name('deleteStudent.destroy');
});


Route::get('/_debug/tilaka/token', [TilakaDebugController::class, 'getToken'])
    ->middleware(['auth']);

Route::get('/_debug/tilaka/uuid', [TilakaDebugController::class, 'getUuid'])
    ->middleware(['auth']);

Route::post('/_debug/gmail/send', [GmailSampleController::class, 'send'])
    ->middleware(['auth']);

Route::get('/_debug/gmail/send-emixbal', [GmailSampleController::class, 'sendToEmixbal'])
    ->middleware(['auth']);

require_once __DIR__ . '/docu.php';
require_once __DIR__ . '/incoming_mail.php';
require_once __DIR__ . '/units.php';
require_once __DIR__ . '/dispositions.php';
require_once __DIR__ . '/tilaka.php';

require __DIR__ . '/auth.php';
