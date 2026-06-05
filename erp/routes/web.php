<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ServiceOrderController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\SaleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Configurações da Empresa
    Route::get('/settings/company', [CompanyController::class, 'edit'])->name('company.edit');
    Route::put('/settings/company', [CompanyController::class, 'update'])->name('company.update');

    // Configurações de Status de OS
    Route::resource('settings/statuses', \App\Http\Controllers\Admin\ServiceOrderStatusController::class)
        ->names([
            'index' => 'settings.statuses.index',
            'create' => 'settings.statuses.create',
            'store' => 'settings.statuses.store',
            'edit' => 'settings.statuses.edit',
            'update' => 'settings.statuses.update',
            'destroy' => 'settings.statuses.destroy',
        ]);

    // Configurações de Checklists
    Route::resource('settings/checklists', \App\Http\Controllers\Admin\ChecklistTemplateController::class)
        ->names([
            'index' => 'settings.checklists.index',
            'create' => 'settings.checklists.create',
            'store' => 'settings.checklists.store',
            'edit' => 'settings.checklists.edit',
            'update' => 'settings.checklists.update',
            'destroy' => 'settings.checklists.destroy',
        ]);

    // Perfil do Usuário
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Clientes
    Route::get('clients/cnpj/{cnpj}', [ClientController::class, 'lookupCnpj'])->name('clients.cnpj-lookup');
    Route::resource('clients', ClientController::class);
    Route::post('clients/{client}/equipments', [\App\Http\Controllers\ClientEquipmentController::class, 'store'])->name('clients.equipments.store');
    Route::get('clients/{client}/equipments/json', [\App\Http\Controllers\ClientEquipmentController::class, 'listJson'])->name('clients.equipments.json');
    Route::put('equipments/{equipment}', [\App\Http\Controllers\ClientEquipmentController::class, 'update'])->name('equipments.update');
    Route::delete('equipments/{equipment}', [\App\Http\Controllers\ClientEquipmentController::class, 'destroy'])->name('equipments.destroy');

    // Produtos & Serviços
    Route::get('products/search', [ProductController::class, 'search'])->name('products.search');
    Route::resource('products', ProductController::class);

    // Serviços
    Route::get('services/search', [ServiceController::class, 'search'])->name('services.search');
    Route::resource('services', ServiceController::class);

    // Orçamentos (Quotes)
    Route::get('quotes/search-clients', [QuoteController::class, 'searchClients'])->name('quotes.search-clients');
    Route::get('quotes/search-items', [QuoteController::class, 'searchItems'])->name('quotes.search-items');
    Route::get('quotes/client-addresses/{client}', [QuoteController::class, 'getAddresses'])->name('quotes.client-addresses');
    Route::post('quotes/{quote}/convert', [QuoteController::class, 'convert'])->name('quotes.convert');
    Route::get('quotes/{quote}/pdf', [QuoteController::class, 'pdf'])->name('quotes.pdf');
    Route::resource('quotes', QuoteController::class);

    // Vendas Comerciais (Sales)
    Route::get('sales/{sale}/pdf', [SaleController::class, 'pdf'])->name('sales.pdf');
    Route::post('sales/{sale}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel');
    Route::resource('sales', SaleController::class)->only(['index', 'show']);

    // Ordens de Serviço
    Route::resource('service-orders', ServiceOrderController::class);
    Route::post('service-orders/{serviceOrder}/status', [ServiceOrderController::class, 'changeStatus'])
        ->name('service-orders.change-status');
    Route::get('service-orders/{serviceOrder}/pdf', [ServiceOrderController::class, 'pdf'])
        ->name('service-orders.pdf');
    Route::get('service-orders/{serviceOrder}/fiscal', [ServiceOrderController::class, 'fiscal'])
        ->name('service-orders.fiscal');
    Route::post('service-orders/{serviceOrder}/duplicate', [ServiceOrderController::class, 'duplicate'])
        ->name('service-orders.duplicate');

    // Operações em Campo (Checklists, Check-in, Assinatura, Anexos)
    Route::prefix('service-orders/{serviceOrder}')->group(function () {
        Route::get('checklists/{checklist}/fill',
            [\App\Http\Controllers\ServiceOrderOperationsController::class, 'fillChecklist'])
            ->name('service-orders.checklists.fill');
        Route::put('checklists/{checklist}/fill',
            [\App\Http\Controllers\ServiceOrderOperationsController::class, 'saveChecklist'])
            ->name('service-orders.checklists.save');
        Route::post('checkin',
            [\App\Http\Controllers\ServiceOrderOperationsController::class, 'checkIn'])
            ->name('service-orders.checkin');
        Route::post('signature',
            [\App\Http\Controllers\ServiceOrderOperationsController::class, 'saveSignature'])
            ->name('service-orders.signature');
        Route::post('attachments',
            [\App\Http\Controllers\ServiceOrderOperationsController::class, 'uploadAttachments'])
            ->name('service-orders.attachments.store');
        Route::delete('attachments/{attachment}',
            [\App\Http\Controllers\ServiceOrderOperationsController::class, 'destroyAttachment'])
            ->name('service-orders.attachments.destroy');
    });

    // Roteirização
    Route::resource('routes', RouteController::class)->only(['index', 'store', 'show']);

});

require __DIR__.'/auth.php';
