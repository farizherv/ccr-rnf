<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

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
use App\Http\Controllers\Admin\NotificationRecipientController;

use App\Http\Controllers\CcrApprovalController;
use App\Http\Controllers\DirectorMonitoringController;

use App\Http\Controllers\InboxController;
use App\Http\Controllers\ExportPartsLabourController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\EditLockController;
use App\Http\Controllers\ItemsMasterController;
use App\Http\Controllers\CcrDraftController;
use App\Http\Controllers\WebPushSubscriptionController;


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
// HEALTH CHECK (public, no auth — for monitoring/uptime checks)
// ==================================================================
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        $cacheOk = Cache::store()->put('_health', true, 5);
        return response()->json([
            'status'    => 'ok',
            'db'        => 'connected',
            'cache'     => $cacheOk ? 'ok' : 'error',
            'timestamp' => now()->toIso8601String(),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'error',
            'message' => app()->isProduction() ? 'Service unavailable' : $e->getMessage(),
        ], 503);
    }
})->name('health');

// ==================================================================
// SEMUA FITUR WAJIB LOGIN — global rate limit 500 req/min
// (autosave draft bisa kirim banyak AJAX per menit)
// ==================================================================
Route::middleware(['auth', 'throttle:500,1'])->group(function () {

    // ✅ Dashboard: director ke monitoring, selain itu ke /ccr
    Route::get('/dashboard', function () {
        $roleRaw = auth()->user()->role;
        $role = $roleRaw instanceof \App\Enums\UserRole ? $roleRaw->value : strtolower(trim((string) $roleRaw));
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
    Route::post('/inbox/test-self', [InboxController::class, 'testSelf'])
        ->middleware('throttle:10,1')
        ->name('inbox.testSelf');

    Route::get('/inbox/panel', [InboxController::class, 'panel'])->name('inbox.panel');
    Route::post('/inbox/{id}/read-json', [InboxController::class, 'readJson'])->name('inbox.readJson');
    Route::post('/notifications/webpush/subscribe', [WebPushSubscriptionController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('notifications.webpush.subscribe');
    Route::post('/notifications/webpush/unsubscribe', [WebPushSubscriptionController::class, 'destroy'])
        ->middleware('throttle:20,1')
        ->name('notifications.webpush.unsubscribe');

    
    // ==============================================================
    // ✅ PREVIEW + EDIT boleh untuk Operator/Admin/Director
    // ==============================================================
    Route::middleware(['role:operator,admin,director'])->group(function () {

        // Global Items Master (typeahead)
        Route::get('/ccr/items-master/search', [ItemsMasterController::class, 'search'])
            ->name('items_master.search');
        Route::post('/ccr/items-master/seat/sync', [ItemsMasterController::class, 'syncSeat'])
            ->name('items_master.seat.sync');

        // ENGINE
        Route::get('/ccr/engine/edit/{id}', [CcrEngineController::class, 'edit'])->name('engine.edit');
        Route::get('/ccr/engine/preview/{id}/pdf', [ExportEngineController::class, 'previewPdfFile'])->name('engine.preview.pdf');
        Route::get('/ccr/engine/preview/{id}', [ExportEngineController::class, 'previewPdf'])->name('engine.preview');

        // SEAT
        Route::get('/ccr/seat/edit/{id}', [CcrSeatController::class, 'edit'])->name('seat.edit');
        Route::get('/ccr/seat/preview/{id}/pdf', [ExportSeatController::class, 'previewPdfFile'])->name('seat.preview.pdf');
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

        // DRAFTS (server-side, lintas perangkat)
        Route::get('/ccr/drafts', [CcrDraftController::class, 'index'])->name('ccr.drafts.index');
        Route::post('/ccr/drafts/upsert', [CcrDraftController::class, 'upsert'])->name('ccr.drafts.upsert');
        Route::delete('/ccr/drafts/{id}', [CcrDraftController::class, 'destroy'])->name('ccr.drafts.destroy');

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

        Route::prefix('admin/notifications')->name('admin.notifications.')->group(function () {
            Route::get('/', [NotificationRecipientController::class, 'index'])->name('index');
            Route::post('/', [NotificationRecipientController::class, 'store'])->name('store');
            Route::post('/bulk-update', [NotificationRecipientController::class, 'bulkUpdate'])->name('bulkUpdate');
            Route::delete('/{recipient}', [NotificationRecipientController::class, 'destroy'])->name('destroy');
        });

        // ACTIVITY LOG
        Route::get('admin/activity-log', [ActivityLogController::class, 'index'])->name('admin.activity-log');
    });

    // ==================================================================
    // DIRECTOR ONLY: Monitoring => NO CACHE
    // ==================================================================
    Route::middleware(['role:director', 'nocache'])->prefix('director')->group(function () {

        Route::get('/monitoring', [DirectorMonitoringController::class, 'index'])->name('director.monitoring');

        Route::post('/monitoring/{id}/approve', [DirectorMonitoringController::class, 'approve'])->name('director.monitoring.approve');
        Route::post('/monitoring/{id}/reject', [DirectorMonitoringController::class, 'reject'])->name('director.monitoring.reject');
    });

    // ==================================================================
    // Parts & Labour Worksheet + Detail
    // (disamakan aksesnya dengan halaman edit/preview: operator/admin/director)
    // ==================================================================
    Route::middleware(['role:operator,admin,director'])->group(function () {

        // EDIT LOCK (heartbeat + release)
        Route::post('/ccr/{id}/edit-lock/heartbeat', [EditLockController::class, 'heartbeat'])->name('ccr.editlock.heartbeat');
        Route::delete('/ccr/{id}/edit-lock/release', [EditLockController::class, 'release'])->name('ccr.editlock.release');

        Route::get('/engine/{id}/export-parts-labour', [ExportPartsLabourController::class, 'engine'])
            ->name('engine.export.parts_labour');

        Route::get('/seat/{id}/export-parts-labour', [ExportPartsLabourController::class, 'seat'])
            ->name('seat.export.parts_labour');

        // worksheet template (AJAX)
        Route::get('/engine/worksheet/template/defaults', [CcrEngineController::class, 'templateDefaults'])
            ->name('engine.worksheet.template.defaults');

        // worksheet template (AJAX) - SEAT
        Route::get('/seat/worksheet/template/defaults', [CcrSeatController::class, 'templateDefaults'])
            ->name('seat.worksheet.template.defaults');

        // APPLY template (legacy name, BIARKAN supaya yang lama tetap jalan)
        Route::post('/engine/{report}/worksheet/template', [CcrEngineController::class, 'applyWorksheetTemplate'])
            ->middleware('throttle:30,1')
            ->name('engine.worksheet.template');

        // APPLY template (alias name baru, supaya route('engine.worksheet.template.apply') tidak error)
        Route::post('/engine/{report}/worksheet/template/apply', [CcrEngineController::class, 'applyWorksheetTemplate'])
            ->middleware('throttle:30,1')
            ->name('engine.worksheet.template.apply');


        // APPLY template (SEAT) (legacy name, BIARKAN supaya yang lama tetap jalan)
        Route::post('/seat/{report}/worksheet/template', [CcrSeatController::class, 'applyWorksheetTemplate'])
            ->middleware('throttle:30,1')
            ->name('seat.worksheet.template');

        // APPLY template (SEAT) (alias name baru)
        Route::post('/seat/{report}/worksheet/template/apply', [CcrSeatController::class, 'applyWorksheetTemplate'])
            ->middleware('throttle:30,1')
            ->name('seat.worksheet.template.apply');

        // AUTOSAVE Worksheet payload (Parts + Detail)
        Route::post('/engine/{id}/worksheet/autosave', [CcrEngineController::class, 'autosaveWorksheet'])
            ->middleware('throttle:30,1')
            ->name('engine.worksheet.autosave');

        // AUTOSAVE Worksheet payload (Seat) (Parts + Detail)
        Route::post('/seat/{id}/worksheet/autosave', [CcrSeatController::class, 'autosaveWorksheet'])
            ->middleware('throttle:30,1')
            ->name('seat.worksheet.autosave');
    });

});
