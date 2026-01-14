<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ImportController;

Route::get('/', function () {
    return redirect()->route('imports.index');
});

Route::middleware(['auth'])->group(function () {

    // Imports
    Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
    Route::post('/imports', [ImportController::class, 'store'])->name('imports.store');
    Route::get('/imports/{import}', [ImportController::class, 'show'])->name('imports.show');

    // AJAX rows for the Excel-like grid
    Route::get('/imports/{import}/rows.json', [ImportController::class, 'rowsJson'])->name('imports.rows');

    // Actions
    Route::post('/imports/{import}/process-names', [ImportController::class, 'processNames'])->name('imports.processNames');
    Route::post('/imports/{import}/generate-emails', [ImportController::class, 'generateEmails'])->name('imports.generateEmails');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/imports/{import}/report.json', [ImportController::class, 'reportJson'])->name('imports.report');
    Route::patch('/imports/{import}/rows/{row}/email', [ImportController::class, 'updateRowEmail'])->name('imports.rows.updateEmail');
    Route::get('/imports/{import}/export.csv', [ImportController::class, 'exportCsv'])->name('imports.exportCsv');
    Route::post('/imports/{import}/queue-emails', [ImportController::class, 'queueEmails'])->name('imports.queueEmails');

});

require __DIR__.'/auth.php';
