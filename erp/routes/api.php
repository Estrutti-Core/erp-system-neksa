<?php

use App\Http\Controllers\Api\V1\ChecklistController;
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
    Route::post('/service-orders/{serviceOrder}/signature', [ServiceOrderController::class, 'signature']);
    Route::post('/service-orders/{serviceOrder}/attachments', [ServiceOrderController::class, 'uploadAttachments']);
    // Checklists (offline-first)
    Route::get('/service-orders/{serviceOrder}/checklists', [ChecklistController::class, 'index']);
    Route::patch('/checklist-instances/{checklist}/answers', [ChecklistController::class, 'syncAnswers']);

    // Legado: mantido para compatibilidade com clientes antigos
    Route::post('/service-orders/{serviceOrder}/checklists/{checklist}/fill', [ServiceOrderController::class, 'fillChecklist']);

    // Clientes
    Route::get('/clients', [ClientController::class, 'index']);
    Route::get('/clients/{client}', [ClientController::class, 'show']);
    Route::post('/clients', [ClientController::class, 'store']);

});
