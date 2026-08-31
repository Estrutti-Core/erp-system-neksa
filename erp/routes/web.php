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
    Route::get('settings/blocks/{block}/questions',
        [\App\Http\Controllers\Admin\ChecklistTemplateController::class, 'blockQuestions'])
        ->name('settings.checklists.block-questions');

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
    Route::get('sales/{sale}/payment', [SaleController::class, 'paymentForm'])->name('sales.payment');
    Route::post('sales/{sale}/complete', [SaleController::class, 'complete'])->name('sales.complete');
    Route::post('sales/{sale}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel');
    Route::post('sales/{sale}/attachments', [SaleController::class, 'uploadAttachments'])->name('sales.attachments.store');
    Route::delete('sales/{sale}/attachments/{attachment}', [SaleController::class, 'destroyAttachment'])->name('sales.attachments.destroy');
    Route::get('sales/export/xlsx', [SaleController::class, 'exportXlsx'])->name('sales.export.xlsx');
    Route::resource('sales', SaleController::class)->only(['index', 'show']);

    // Ordens de Serviço
    Route::get('service-orders/export/xlsx', [ServiceOrderController::class, 'exportXlsx'])->name('service-orders.export.xlsx');
    Route::resource('service-orders', ServiceOrderController::class);
    Route::get('service-orders/{serviceOrder}/payment', [ServiceOrderController::class, 'paymentForm'])
        ->name('service-orders.payment');
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
        Route::get('checklists/{checklist}/pdf',
            [\App\Http\Controllers\ServiceOrderOperationsController::class, 'checklistPdf'])
            ->name('service-orders.checklists.pdf');
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

    // Fornecedores, Compras e Recebimentos (Módulo E)
    Route::resource('suppliers', \App\Http\Controllers\SupplierController::class);
    Route::post('purchase-orders/{purchase_order}/order', [\App\Http\Controllers\PurchaseOrderController::class, 'order'])->name('purchase-orders.order');
    Route::post('purchase-orders/{purchase_order}/cancel', [\App\Http\Controllers\PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
    Route::resource('purchase-orders', \App\Http\Controllers\PurchaseOrderController::class);
    Route::post('inventory-conferences', [\App\Http\Controllers\InventoryConferenceController::class, 'store'])->name('inventory-conferences.store');
    Route::get('inventory-conferences/{inventory_conference}', [\App\Http\Controllers\InventoryConferenceController::class, 'show'])->name('inventory-conferences.show');
    Route::post('inventory-conferences/{inventory_conference}/complete', [\App\Http\Controllers\InventoryConferenceController::class, 'complete'])->name('inventory-conferences.complete');

    // Importação de XML NF-e
    Route::get('xml-imports', [\App\Http\Controllers\XmlImportController::class, 'index'])->name('xml-imports.index');
    Route::get('xml-imports/create', [\App\Http\Controllers\XmlImportController::class, 'create'])->name('xml-imports.create');
    Route::post('xml-imports', [\App\Http\Controllers\XmlImportController::class, 'store'])->name('xml-imports.store');
    Route::get('xml-imports/{xmlImport}', [\App\Http\Controllers\XmlImportController::class, 'show'])->name('xml-imports.show');
    Route::post('xml-imports/items/{xmlImportItem}/resolve', [\App\Http\Controllers\XmlImportController::class, 'resolveItem'])->name('xml-imports.resolve-item');
    Route::post('xml-imports/{xmlImport}/confirm', [\App\Http\Controllers\XmlImportController::class, 'confirm'])->name('xml-imports.confirm');

    // Roteirização
    Route::resource('routes', RouteController::class)->only(['index', 'store', 'show']);

    // Módulo F: Financeiro
    Route::resource('financial-accounts', \App\Http\Controllers\FinancialAccountController::class);
    Route::get('receivables/export/xlsx', [\App\Http\Controllers\ReceivableController::class, 'exportXlsx'])->name('receivables.export.xlsx');
    Route::resource('receivables', \App\Http\Controllers\ReceivableController::class)->only(['index', 'show', 'create', 'store']);
    Route::post('receivables/{receivable}/installments/{installment}/pay', [\App\Http\Controllers\ReceivableController::class, 'pay'])->name('receivables.pay');
    Route::post('receivables/{receivable}/cancel', [\App\Http\Controllers\ReceivableController::class, 'cancel'])->name('receivables.cancel');
    Route::get('receivables/{receivable}/print', [\App\Http\Controllers\ReceivableController::class, 'print'])->name('receivables.print');

    Route::get('payables/export/xlsx', [\App\Http\Controllers\PayableController::class, 'exportXlsx'])->name('payables.export.xlsx');
    Route::resource('payables', \App\Http\Controllers\PayableController::class)->only(['index', 'show', 'create', 'store']);
    Route::post('payables/{payable}/installments/{installment}/pay', [\App\Http\Controllers\PayableController::class, 'pay'])->name('payables.pay');
    Route::post('payables/{payable}/cancel', [\App\Http\Controllers\PayableController::class, 'cancel'])->name('payables.cancel');
    Route::get('payables/{payable}/print', [\App\Http\Controllers\PayableController::class, 'print'])->name('payables.print');

    Route::get('financial/cash-flow', [\App\Http\Controllers\CashFlowController::class, 'index'])->name('financial.cash-flow');
    Route::get('financial/audit', [\App\Http\Controllers\FinancialEventController::class, 'index'])->name('financial.audit');
    Route::get('financial/closing', [\App\Http\Controllers\FinancialClosingController::class, 'index'])->name('financial.closing');
    Route::get('financial/closing/xlsx', [\App\Http\Controllers\FinancialClosingController::class, 'xlsx'])->name('financial.closing.xlsx');
    Route::get('financial/closing/pdf', [\App\Http\Controllers\FinancialClosingController::class, 'pdf'])->name('financial.closing.pdf');

    Route::resource('payment-conditions', \App\Http\Controllers\PaymentConditionController::class);
});

require __DIR__.'/auth.php';
