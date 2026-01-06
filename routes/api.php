<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AgentQueueController;

Route::prefix('agent')->middleware('ccr.agent')->group(function () {
    Route::get('/ping', [AgentQueueController::class, 'ping']);
    Route::get('/pending', [AgentQueueController::class, 'pending']);
    Route::post('/done/{id}', [AgentQueueController::class, 'done']);
    Route::post('/failed/{id}', [AgentQueueController::class, 'failed']);
});
