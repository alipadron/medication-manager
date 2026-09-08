<?php

use App\Http\Controllers\Api\V1\DoseEventController;
use App\Http\Controllers\Api\V1\MedicationController;
use App\Http\Controllers\Api\V1\PersonController;
use App\Http\Controllers\Api\V1\ScheduleController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::apiResource('people', PersonController::class)->only(['index', 'store']);
    Route::apiResource('medications', MedicationController::class)->only(['index', 'store']);
    Route::apiResource('schedules', ScheduleController::class)->only(['index', 'store']);
    Route::get('schedules/{schedule}/shifted', [ScheduleController::class, 'shifted']);
    Route::apiResource('dose-events', DoseEventController::class)->only(['index', 'store']);
});
