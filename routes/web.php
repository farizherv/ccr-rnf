<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CcrReportController;
use App\Http\Controllers\CcrEngineController;
use App\Http\Controllers\CcrSeatController;
use App\Http\Controllers\ExportEngineController;
use App\Http\Controllers\ExportSeatController;


// ==================================================================
// DEFAULT REDIRECT
// ==================================================================
Route::redirect('/', '/ccr');


// ==================================================================
// MENU UTAMA CCR
// ==================================================================
Route::get('/ccr', [CcrReportController::class, 'index'])
    ->name('ccr.index');

// MENU MANAGE CCR (ENGINE / SEAT)
Route::get('/ccr/manage', [CcrReportController::class, 'editMenu'])
    ->name('ccr.manage.menu');

// LIST CCR ENGINE & SEAT
Route::get('/ccr/manage/engine', [CcrReportController::class, 'editEngineList'])
    ->name('ccr.manage.engine');

Route::get('/ccr/manage/seat', [CcrReportController::class, 'editSeatList'])
    ->name('ccr.manage.seat');


// ==================================================================
// ========================  CCR ENGINE  =============================
// ==================================================================
Route::prefix('ccr/engine')->group(function () {

    Route::get('/create', [CcrEngineController::class, 'create'])->name('engine.create');
    Route::post('/store', [CcrEngineController::class, 'store'])->name('engine.store');

    Route::get('/edit/{id}', [CcrEngineController::class, 'edit'])->name('engine.edit');
    Route::put('/update/{id}', [CcrEngineController::class, 'updateHeader'])->name('engine.update.header');

    Route::post('/item/{item}/update', [CcrEngineController::class, 'updateItem'])->name('engine.item.update');
    Route::delete('/item/{item}/delete', [CcrEngineController::class, 'deleteItem'])->name('engine.item.delete');

    Route::delete('/photo/{photo}/delete', [CcrEngineController::class, 'deletePhoto'])->name('engine.photo.delete');

    Route::delete('/delete-multiple', [CcrReportController::class, 'deleteMultipleEngine'])
        ->name('ccr.engine.deleteMultiple');

    Route::get('/preview/{id}', [ExportEngineController::class, 'previewPdf'])
        ->name('engine.preview');

    Route::get('/export/word/{id}', [ExportEngineController::class, 'downloadEngine'])
        ->name('engine.export.word');
});


// ==================================================================
// ========================  CCR SEAT  ===============================
// ==================================================================
Route::prefix('ccr/seat')->group(function () {

    Route::get('/create', [CcrSeatController::class, 'create'])->name('seat.create');
    Route::post('/store', [CcrSeatController::class, 'store'])->name('seat.store');

    // EDIT SEAT
    Route::get('/edit/{id}', [CcrSeatController::class, 'edit'])->name('seat.edit');
    Route::put('/update-header/{id}', [CcrSeatController::class, 'updateHeader'])->name('seat.update.header');

    // UPDATE / DELETE ITEM
    Route::post('/item/{item}/update', [CcrSeatController::class, 'updateItem'])->name('seat.item.update');
    Route::delete('/item/{item}/delete', [CcrSeatController::class, 'deleteItem'])->name('seat.item.delete');

    // PREVIEW SEAT
    Route::get('/preview/{id}', [ExportSeatController::class, 'preview'])
        ->name('seat.preview');

    // EXPORT WORD — hanya satu!
    Route::get('/export/word/{id}', [ExportSeatController::class, 'generateSeat'])
        ->name('seat.export.word');

    // BULK DELETE
    Route::delete('/delete-multiple', [CcrSeatController::class, 'deleteMultiple'])
        ->name('ccr.seat.deleteMultiple');
});

