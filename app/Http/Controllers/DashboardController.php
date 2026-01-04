<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Product;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Display dashboard with aggregated statistics.
     */
    public function index()
    {
        $today = Carbon::today();

        // Calculate real statistics
        $salesToday = Sale::whereDate('created_at', $today)
            ->where('status', 'completed')
            ->sum('total');

        $totalSalesData = Sale::where('status', 'completed')
            ->select(DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as count'))
            ->first();

        $productsCount = Product::where('active', true)->count();
        
        $lowStockCount = Product::where('active', true)
            ->whereRaw('stock_balance <= min_stock')
            ->count();

        $customersCount = Customer::where('active', true)->count();

        $criticalStockCount = Product::where('active', true)
            ->whereRaw('stock_balance <= min_stock * 0.5')
            ->count();

        // Recent sales with relationships
        $recentSales = Sale::with(['customer', 'user', 'paymentMethod'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Low stock products
        $lowStockProducts = Product::with('category')
            ->where('active', true)
            ->whereRaw('stock_balance <= min_stock')
            ->orderBy('stock_balance')
            ->limit(5)
            ->get();

        // Mock financial data (módulo não implementado)
        $mockFinancial = [
            'pendingPayables' => 15420.50,
            'overduePayablesCount' => 3,
            'pendingReceivables' => 28750.00,
            'overduePayables' => [
                [
                    'id' => '1',
                    'supplierName' => 'Fornecedor Exemplo',
                    'description' => 'Módulo financeiro em desenvolvimento',
                    'amount' => 5000.00,
                    'dueDate' => '2026-01-01',
                ],
                [
                    'id' => '2',
                    'supplierName' => 'Distribuidora ABC',
                    'description' => 'Dados mockados temporariamente',
                    'amount' => 3500.00,
                    'dueDate' => '2025-12-28',
                ],
                [
                    'id' => '3',
                    'supplierName' => 'Fornecedor XYZ',
                    'description' => 'Aguardando implementação do módulo',
                    'amount' => 6920.50,
                    'dueDate' => '2025-12-15',
                ],
            ],
        ];

        return Inertia::render('dashboard/DashboardPage', [
            'stats' => [
                'salesToday' => $salesToday,
                'totalSales' => $totalSalesData->total ?? 0,
                'totalSalesCount' => $totalSalesData->count ?? 0,
                'productsCount' => $productsCount,
                'lowStockCount' => $lowStockCount,
                'customersCount' => $customersCount,
                'criticalStockCount' => $criticalStockCount,
            ],
            'recentSales' => $recentSales,
            'lowStockProducts' => $lowStockProducts,
            'mockFinancial' => $mockFinancial,
        ]);
    }
}
