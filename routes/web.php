<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/', [App\Http\Controllers\DashboardController::class, 'index'])->name('welcome');
    Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

    // Cadastros - Resource Routes
    Route::resource('cadastros/categorias', App\Http\Controllers\CategoryController::class)->names([
        'index' => 'cadastros.categorias.index',
        'store' => 'cadastros.categorias.store',
        'update' => 'cadastros.categorias.update',
        'destroy' => 'cadastros.categorias.destroy',
    ])->only(['index', 'store', 'update', 'destroy']);

    Route::resource('cadastros/produtos', App\Http\Controllers\ProductController::class)->names([
        'index' => 'cadastros.produtos.index',
        'store' => 'cadastros.produtos.store',
        'update' => 'cadastros.produtos.update',
        'destroy' => 'cadastros.produtos.destroy',
    ])->only(['index', 'store', 'update', 'destroy']);

    Route::resource('cadastros/fornecedores', App\Http\Controllers\SupplierController::class)->names([
        'index' => 'cadastros.fornecedores.index',
        'store' => 'cadastros.fornecedores.store',
        'update' => 'cadastros.fornecedores.update',
        'destroy' => 'cadastros.fornecedores.destroy',
    ])->only(['index', 'store', 'update', 'destroy']);

    Route::resource('cadastros/clientes', App\Http\Controllers\CustomerController::class)->names([
        'index' => 'cadastros.clientes.index',
        'store' => 'cadastros.clientes.store',
        'update' => 'cadastros.clientes.update',
        'destroy' => 'cadastros.clientes.destroy',
    ])->only(['index', 'store', 'update', 'destroy']);

    Route::resource('cadastros/funcionarios', App\Http\Controllers\EmployeeController::class)->names([
        'index' => 'cadastros.funcionarios.index',
        'store' => 'cadastros.funcionarios.store',
        'update' => 'cadastros.funcionarios.update',
        'destroy' => 'cadastros.funcionarios.destroy',
    ])->only(['index', 'store', 'update', 'destroy']);

    Route::resource('cadastros/pagamentos', App\Http\Controllers\PaymentMethodController::class)->names([
        'index' => 'cadastros.pagamentos.index',
        'store' => 'cadastros.pagamentos.store',
        'update' => 'cadastros.pagamentos.update',
        'destroy' => 'cadastros.pagamentos.destroy',
    ])->only(['index', 'store', 'update', 'destroy']);

    // Estoque
    Route::get('/estoque/consulta', [App\Http\Controllers\StockQueryController::class, 'index'])->name('estoque.consulta');
    
    Route::get('/estoque/ajuste', [App\Http\Controllers\StockAdjustmentController::class, 'index'])->name('estoque.ajuste.index');
    Route::post('/estoque/ajuste', [App\Http\Controllers\StockAdjustmentController::class, 'store'])->name('estoque.ajuste.store');
    
    Route::get('/estoque/entrada', [App\Http\Controllers\StockEntryController::class, 'index'])->name('estoque.entrada.index');
    Route::post('/estoque/entrada', [App\Http\Controllers\StockEntryController::class, 'store'])->name('estoque.entrada.store');
    
    Route::get('/estoque/movimentacoes', [App\Http\Controllers\StockMovementController::class, 'index'])->name('estoque.movimentacoes');

    // Vendas
    Route::get('/vendas/lista', [App\Http\Controllers\SalesListController::class, 'index'])->name('vendas.lista');

    // Financeiro
    Route::get('/financeiro/pagar', fn() => Inertia::render('Financeiro/AccountsPayablePage'))->name('financeiro.pagar');
    Route::get('/financeiro/receber', fn() => Inertia::render('Financeiro/AccountsReceivablePage'))->name('financeiro.receber');
    Route::get('/financeiro/fluxo', fn() => Inertia::render('Financeiro/CashFlowPage'))->name('financeiro.fluxo');

    // Relatórios
    Route::get('/relatorios/vendas', [App\Http\Controllers\SalesReportController::class, 'index'])->name('relatorios.vendas');
    Route::get('/relatorios/produtos', [App\Http\Controllers\TopProductsController::class, 'index'])->name('relatorios.produtos');
    Route::get('/relatorios/estoque-critico', [App\Http\Controllers\CriticalStockController::class, 'index'])->name('relatorios.estoque-critico');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
