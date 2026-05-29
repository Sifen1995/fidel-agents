<?php

use App\Http\Controllers\HomeworkDemoController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AiRequestController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('ai')->group(function () {

    // Main Brain entry (this is your orchestrator endpoint)
    Route::post('/ask', [AiRequestController::class, 'handle']);

    // Optional: health check
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'service' => 'brain'
        ]);
    });

    Route::get('/status', [HomeworkDemoController::class, 'status']);

});