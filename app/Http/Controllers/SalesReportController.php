<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class SalesReportController extends Controller
{
    /**
     * Display sales report with period filter.
     */
    public function index(Request $request)
    {
        $period = $request->get('period', 'all');
        $now = Carbon::now();

        // Build query based on period
        $query = Sale::query();

        switch ($period) {
            case 'today':
                $query->whereDate('created_at', $now->toDateString());
                break;
            case 'week':
                $query->where('created_at', '>=', $now->copy()->subDays(7));
                break;
            case 'month':
                $query->whereYear('created_at', $now->year)
                      ->whereMonth('created_at', $now->month);
                break;
            // 'all' - no filter
        }

        // Get sales with relationships
        $sales = $query->with(['customer', 'user', 'items'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate statistics
        $stats = [
            'totalVendas' => $sales->count(),
            'valorTotal' => $sales->sum('total'),
            'ticketMedio' => $sales->count() > 0 ? $sales->avg('total') : 0,
            'itensVendidos' => $sales->sum(function ($sale) {
                return $sale->items->sum('quantity');
            }),
        ];

        // Daily sales aggregation
        $dailySales = Sale::query()
            ->when($period !== 'all', function ($q) use ($period, $now) {
                switch ($period) {
                    case 'today':
                        $q->whereDate('created_at', $now->toDateString());
                        break;
                    case 'week':
                        $q->where('created_at', '>=', $now->copy()->subDays(7));
                        break;
                    case 'month':
                        $q->whereYear('created_at', $now->year)
                          ->whereMonth('created_at', $now->month);
                        break;
                }
            })
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total) as value'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date', 'desc')
            ->limit(7)
            ->get()
            ->reverse()
            ->values();

        // Get last 20 sales for table
        $recentSales = $sales->take(20);

        return Inertia::render('Relatorios/SalesReportPage', [
            'sales' => $recentSales,
            'stats' => $stats,
            'dailySales' => $dailySales,
            'period' => $period,
        ]);
    }
}
