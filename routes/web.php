<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\CcrReportController;
use App\Http\Controllers\CcrEngineController;
use App\Http\Controllers\CcrSeatController;

use App\Http\Controllers\ExportEngineController;
use App\Http\Controllers\ExportSeatController;

use App\Http\Controllers\EngineTrashController;
use App\Http\Controllers\SeatTrashController;

use App\Http\Controllers\Trash\TrashMenuController;
use App\Http\Controllers\CcrTrashController;

use App\Http\Controllers\Admin\UserManagementController;

use App\Http\Controllers\CcrApprovalController;
use App\Http\Controllers\DirectorMonitoringController;

use App\Http\Controllers\InboxController;

// ==================================================================
// DEFAULT REDIRECT
// ==================================================================
Route::redirect('/', '/dashboard');

// ==================================================================
// AUTH (Login / Register / Forgot / Reset) => NO CACHE
// ==================================================================
Route::middleware('nocache')->group(function () {
    require __DIR__ . '/auth.php';
});

// ==================================================================
// SEMUA FITUR WAJIB LOGIN
// ==================================================================
Route::middleware(['auth'])->group(function () {

    // ✅ Dashboard: director ke monitoring, selain itu ke /ccr
    Route::get('/dashboard', function () {
        $role = strtolower(trim((string) auth()->user()->role));
        return $role === 'director'
            ? redirect()->route('director.monitoring')
            : redirect()->route('ccr.index');
    })->middleware('nocache')->name('dashboard');

    // ==================================================================
    // INBOX (semua role login boleh)
    // ==================================================================
    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox.index');
    Route::post('/inbox/{id}/read', [InboxController::class, 'read'])->name('inbox.read');
    Route::post('/inbox/read-all', [InboxController::class, 'readAll'])->name('inbox.readAll');
    Route::post('/inbox/clear-all', [InboxController::class, 'clearAll'])->name('inbox.clearAll');

    // ✅ NEW: hapus notif yang SUDAH dibaca saja
    Route::post('/inbox/clear-read', [InboxController::class, 'clearRead'])->name('inbox.clearRead');

    Route::get('/inbox/panel', [InboxController::class, 'panel'])->name('inbox.panel');
    Route::post('/inbox/{id}/read-json', [InboxController::class, 'readJson'])->name('inbox.readJson');

    
    // ==============================================================
    // ✅ PREVIEW + EDIT boleh untuk Operator/Admin/Director
    // ==============================================================
    Route::middleware(['role:operator,admin,director'])->group(function () {

        // ENGINE
        Route::get('/ccr/engine/edit/{id}', [CcrEngineController::class, 'edit'])->name('engine.edit');
        Route::get('/ccr/engine/preview/{id}', [ExportEngineController::class, 'previewPdf'])->name('engine.preview');

        // SEAT
        Route::get('/ccr/seat/edit/{id}', [CcrSeatController::class, 'edit'])->name('seat.edit');
        Route::get('/ccr/seat/preview/{id}', [ExportSeatController::class, 'preview'])->name('seat.preview');
    });

    // ==============================================================
    // SUBMIT (Operator/Admin -> Director)
    // ==============================================================
    Route::middleware(['role:operator,admin'])->group(function () {
        Route::post('/ccr/engine/{id}/submit', [CcrEngineController::class, 'submit'])->name('engine.submit');
        Route::post('/ccr/seat/{id}/submit', [CcrSeatController::class, 'submit'])->name('seat.submit');
    });

    // ==============================================================
    // DIRECTOR APPROVE/REJECT => NO CACHE
    // ==============================================================
    Route::middleware(['role:director', 'nocache'])->group(function () {
        Route::post('/director/ccr/{type}/{id}/approve', [CcrApprovalController::class, 'approve'])->name('director.ccr.approve');
        Route::post('/director/ccr/{type}/{id}/reject', [CcrApprovalController::class, 'reject'])->name('director.ccr.reject');
    });

    // ==================================================================
    // CCR: OPERATOR + ADMIN + DIRECTOR
    // ==================================================================
    Route::middleware(['role:operator,admin,director'])->group(function () {

        // MENU UTAMA CCR
        Route::get('/ccr', [CcrReportController::class, 'index'])->name('ccr.index');

        // MENU MANAGE CCR
        Route::get('/ccr/manage', [CcrReportController::class, 'editMenu'])->name('ccr.manage.menu');

        // ✅ ALIAS biar route('ccr.edit.menu') gak error (legacy name)
        Route::get('/ccr/edit-menu', [CcrReportController::class, 'editMenu'])->name('ccr.edit.menu');

        // LIST CCR ENGINE & SEAT
        Route::get('/ccr/manage/engine', [CcrReportController::class, 'editEngineList'])->name('ccr.manage.engine');
        Route::get('/ccr/manage/seat',   [CcrReportController::class, 'editSeatList'])->name('ccr.manage.seat');

        // ========================  CCR ENGINE  =============================
        Route::prefix('ccr/engine')->group(function () {

            Route::get('/create', [CcrEngineController::class, 'create'])->name('engine.create');
            Route::post('/store', [CcrEngineController::class, 'store'])->name('engine.store');

            Route::put('/update/{id}', [CcrEngineController::class, 'updateHeader'])->name('engine.update.header');

            Route::post('/item/{item}/update', [CcrEngineController::class, 'updateItem'])->name('engine.item.update');
            Route::delete('/item/{item}/delete', [CcrEngineController::class, 'deleteItem'])->name('engine.item.delete');

            Route::delete('/photo/{photo}/delete', [CcrEngineController::class, 'deletePhoto'])->name('engine.photo.delete');

            Route::get('/export/word/{id}', [ExportEngineController::class, 'downloadEngine'])->name('engine.export.word');
        });

        // ========================  CCR SEAT  ===============================
        Route::prefix('ccr/seat')->group(function () {

            Route::get('/create', [CcrSeatController::class, 'create'])->name('seat.create');
            Route::post('/store', [CcrSeatController::class, 'store'])->name('seat.store');

            Route::put('/update-header/{id}', [CcrSeatController::class, 'updateHeader'])->name('seat.update.header');

            Route::post('/item/{item}/update', [CcrSeatController::class, 'updateItem'])->name('seat.item.update');
            Route::delete('/item/{item}/delete', [CcrSeatController::class, 'deleteItem'])->name('seat.item.delete');

            Route::delete('/photo/{photo}/delete', [CcrSeatController::class, 'deletePhoto'])->name('seat.photo.delete');

            Route::get('/export/word/{id}', [ExportSeatController::class, 'generateSeatDownload'])->name('seat.export.word');
        });

        // CCR TRASH (boleh untuk director juga) - ini yang /ccr/trash
        Route::get('/ccr/trash', [CcrTrashController::class, 'index'])->name('ccr.trash.index');
        Route::post('/ccr/trash/restore', [CcrTrashController::class, 'restore'])->name('ccr.trash.restore');
    });

    // ==================================================================
    // ADMIN + DIRECTOR => NO CACHE (User management & trash admin)
    // ==================================================================
    Route::middleware(['role:admin,director', 'nocache'])->group(function () {

        // BULK TRASH DELETE
        Route::post('ccr/engine/trash-multiple', [EngineTrashController::class, 'trashMultiple'])->name('ccr.engine.trashMultiple');
        Route::post('ccr/seat/trash-multiple', [SeatTrashController::class, 'trashMultiple'])->name('ccr.seat.trashMultiple');

        // TRASH MENU (/trash)
        Route::prefix('trash')->name('trash.')->group(function () {

            Route::get('/', [TrashMenuController::class, 'index'])->name('menu');

            // ENGINE
            Route::get('/engine', [EngineTrashController::class, 'index'])->name('engine.index');
            Route::post('/engine/restore-multiple', [EngineTrashController::class, 'restoreMultiple'])->name('engine.restoreMultiple');
            Route::delete('/engine/force-multiple', [EngineTrashController::class, 'forceMultiple'])->name('engine.forceMultiple');
            Route::post('/engine/{id}/restore', [EngineTrashController::class, 'restore'])->name('engine.restore');
            Route::delete('/engine/{id}/force', [EngineTrashController::class, 'forceDelete'])->name('engine.force');

            // SEAT
            Route::get('/seat', [SeatTrashController::class, 'index'])->name('seat.index');
            Route::post('/seat/restore-multiple', [SeatTrashController::class, 'restoreMultiple'])->name('seat.restoreMultiple');
            Route::delete('/seat/force-multiple', [SeatTrashController::class, 'forceMultiple'])->name('seat.forceMultiple');
            Route::post('/seat/{id}/restore', [SeatTrashController::class, 'restore'])->name('seat.restore');
            Route::delete('/seat/{id}/force', [SeatTrashController::class, 'forceDelete'])->name('seat.force');
        });

        // USER MANAGEMENT
        Route::prefix('admin/users')->name('admin.users.')->group(function () {
            Route::get('/', [UserManagementController::class, 'index'])->name('index');
            Route::get('/create', [UserManagementController::class, 'create'])->name('create');
            Route::post('/', [UserManagementController::class, 'store'])->name('store');

            Route::get('/{user}/edit', [UserManagementController::class, 'edit'])->name('edit');
            Route::put('/{user}', [UserManagementController::class, 'update'])->name('update');
            
            Route::delete('/{user}', [UserManagementController::class, 'destroy'])
                ->name('destroy');

        });
    });

    // ==================================================================
    // DIRECTOR ONLY: Monitoring => NO CACHE
    // ==================================================================
    Route::middleware(['role:director', 'nocache'])->prefix('director')->group(function () {

        Route::get('/monitoring', [DirectorMonitoringController::class, 'index'])->name('director.monitoring');

        Route::post('/monitoring/{id}/approve', [DirectorMonitoringController::class, 'approve'])->name('director.monitoring.approve');
        Route::post('/monitoring/{id}/reject', [DirectorMonitoringController::class, 'reject'])->name('director.monitoring.reject');
    });
});
