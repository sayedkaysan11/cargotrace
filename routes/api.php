<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CarrierController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\TrackingEventController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Readable by every role: ShipmentPolicy + Shipment::visibleTo() decide what a customer may see.
    Route::get('/shipments', [ShipmentController::class, 'index']);
    Route::get('/shipments/{shipment}', [ShipmentController::class, 'show']);
    Route::get('/shipments/{shipment}/tracking', [TrackingEventController::class, 'index']);

    // Operations: admin + operator.
    Route::middleware('role:admin,operator')->group(function () {
        Route::apiResource('carriers', CarrierController::class)->only(['index', 'show']);
        Route::apiResource('locations', LocationController::class)->only(['index', 'show']);

        Route::post('/shipments', [ShipmentController::class, 'store']);
        Route::match(['put', 'patch'], '/shipments/{shipment}', [ShipmentController::class, 'update']);
        Route::post('/shipments/{shipment}/cancel', [ShipmentController::class, 'cancel']);
        Route::post('/shipments/{shipment}/tracking-events', [TrackingEventController::class, 'store']);
    });

    // Administration: admin only.
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('carriers', CarrierController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('locations', LocationController::class)->only(['store', 'update', 'destroy']);
        Route::delete('/shipments/{shipment}', [ShipmentController::class, 'destroy']);
    });

    // Next milestone: exceptions, notifications, dashboard statistics
});
