<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\BorrowingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;

Route::get('/', function () {
    return redirect('/login');
});

Auth::routes();

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard');
    
    // File Management Routes
    Route::resource('files', FileController::class);
    Route::get('/files/search/results', [FileController::class, 'search'])->name('files.search');
    Route::post('/files/{file}/move', [FileController::class, 'move'])->name('files.move');
    
    // Location Management Routes
    Route::resource('locations', LocationController::class);
    Route::get('/locations/{location}/files', [LocationController::class, 'files'])->name('locations.files');
    
    // Borrowing System Routes
    Route::resource('borrowings', BorrowingController::class);
    Route::post('/borrowings/{borrowing}/return', [BorrowingController::class, 'returnFile'])->name('borrowings.return');
    
    // Reports Routes
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/active-files', [ReportController::class, 'activeFiles'])->name('reports.active-files');
    Route::get('/reports/borrowed-files', [ReportController::class, 'borrowedFiles'])->name('reports.borrowed-files');
    Route::get('/reports/department/{department}', [ReportController::class, 'departmentFiles'])->name('reports.department');
    Route::get('/reports/activity-log', [ReportController::class, 'activityLog'])->name('reports.activity-log');
    Route::get('/reports/statistics', [ReportController::class, 'statistics'])->name('reports.statistics');
    
    // Export Routes
    Route::get('/export/files/pdf', [ReportController::class, 'exportFilesPdf'])->name('export.files.pdf');
    Route::get('/export/files/excel', [ReportController::class, 'exportFilesExcel'])->name('export.files.excel');
    Route::get('/export/borrowings/pdf', [ReportController::class, 'exportBorrowingsPdf'])->name('export.borrowings.pdf');
    Route::get('/export/borrowings/excel', [ReportController::class, 'exportBorrowingsExcel'])->name('export.borrowings.excel');
    
    // User Management Routes (Admin only)
    Route::middleware(['role:admin'])->group(function () {
        Route::resource('users', UserController::class);
        Route::post('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
    });
});