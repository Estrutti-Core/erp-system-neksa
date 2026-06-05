<?php

use App\Http\Controllers\Api\V1\ServiceOrderController;
use App\Http\Controllers\Api\V1\ClientController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {

    // Ordens de Serviço (mobile)
    Route::get('/service-orders', [ServiceOrderController::class, 'index']);
    Route::get('/service-orders/{serviceOrder}', [ServiceOrderController::class, 'show']);
    Route::put('/service-orders/{serviceOrder}/status', [ServiceOrderController::class, 'updateStatus']);
    Route::post('/service-orders/{serviceOrder}/checkin', [ServiceOrderController::class, 'checkIn']);

    // Clientes
    Route::get('/clients', [ClientController::class, 'index']);
    Route::get('/clients/{client}', [ClientController::class, 'show']);
    Route::post('/clients', [ClientController::class, 'store']);

});
