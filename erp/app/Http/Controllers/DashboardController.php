<?php

namespace App\Http\Controllers;

use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\ServiceOrderStatus;
use App\Enums\QuoteStatus;
use App\Enums\SaleStatus;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        // Métricas do dia (OS e Técnicos)
        $todayCompleted = ServiceOrder::whereDate('completed_at', today())
            ->status('completed')
            ->count();

        $openOrders = ServiceOrder::query()->status('open')->count();

        $inService = ServiceOrder::query()->status([
            'in_service',
            'in_route',
        ])->count();

        $activeTechnicians = User::role('technician')
            ->whereHas('assignedServiceOrders', function ($q) {
                $q->whereHas('status', function ($sq) {
                    $sq->whereIn('slug', ['in_service', 'in_route']);
                });
            })->count();

        // Próximas OS agendadas
        $upcomingOrders = ServiceOrder::with(['client', 'technician', 'clientAddress'])
            ->whereDate('scheduled_at', '>=', today())
            ->whereHas('status', fn ($q) => $q->where('is_completed_state', false)->where('is_cancelled_state', false))
            ->orderBy('scheduled_at')
            ->limit(8)
            ->get();

        // OS recentes
        $recentOrders = ServiceOrder::with(['client', 'technician'])
            ->latest()
            ->limit(5)
            ->get();

        // Para técnicos: mostrar apenas as próprias OS e zerar dados comerciais
        if ($user->isTechnician()) {
            $upcomingOrders     = $upcomingOrders->where('technician_id', $user->id);
            $recentOrders       = ServiceOrder::with(['client'])->forTechnician($user->id)->latest()->limit(5)->get();
            $pendingQuotesCount = 0;
            $totalSalesValue    = 0;
            $recentQuotes       = collect();
            $recentSales        = collect();
        } else {
            // Dados Comerciais Consolidados para Operadores/Administradores
            $pendingQuotesCount = Quote::whereIn('status', [QuoteStatus::Draft->value, QuoteStatus::Sent->value])->count();
            $totalSalesValue    = Sale::whereIn('status', [SaleStatus::Completed->value, SaleStatus::Pending->value])->sum('total_amount');
            $recentQuotes       = Quote::with('client')->latest()->limit(5)->get();
            $recentSales        = Sale::with('client')->latest()->limit(5)->get();

            // Novas Métricas Financeiras (Módulo G)
            $overdueQuery = \App\Models\ReceivableInstallment::where('status', \App\Enums\InstallmentStatus::Pending->value)
                ->whereDate('due_date', '<', today());
            $overdueCount = $overdueQuery->count();
            $overdueAmount = (float) $overdueQuery->sum(\DB::raw('amount - paid_amount'));

            $dueTodayQuery = \App\Models\ReceivableInstallment::where('status', \App\Enums\InstallmentStatus::Pending->value)
                ->whereDate('due_date', today());
            $dueTodayCount = $dueTodayQuery->count();
            $dueTodayAmount = (float) $dueTodayQuery->sum(\DB::raw('amount - paid_amount'));

            $dueNext7DaysQuery = \App\Models\ReceivableInstallment::where('status', \App\Enums\InstallmentStatus::Pending->value)
                ->whereBetween('due_date', [today()->addDay(), today()->addDays(7)]);
            $dueNext7DaysCount = $dueNext7DaysQuery->count();
            $dueNext7DaysAmount = (float) $dueNext7DaysQuery->sum(\DB::raw('amount - paid_amount'));

            $accumulatedOverdueAmount = $overdueAmount;
            $averageReceivableAmount = (float) \App\Models\Receivable::avg('total_amount') ?? 0.00;
            $averageSaleAmount = (float) Sale::where('status', SaleStatus::Completed->value)->avg('total_amount') ?? 0.00;

            $currentMonthRevenue = (float) \App\Models\Receivable::whereBetween('competence_date', [
                today()->startOfMonth(),
                today()->endOfMonth()
            ])->sum('total_amount');

            $previousMonthRevenue = (float) \App\Models\Receivable::whereBetween('competence_date', [
                today()->subMonth()->startOfMonth(),
                today()->subMonth()->endOfMonth()
            ])->sum('total_amount');

            $monthlyChangePercent = 0.00;
            if ($previousMonthRevenue > 0) {
                $monthlyChangePercent = (($currentMonthRevenue - $previousMonthRevenue) / $previousMonthRevenue) * 100;
            }
        }

        return view('dashboard.index', compact(
            'todayCompleted',
            'openOrders',
            'inService',
            'activeTechnicians',
            'upcomingOrders',
            'recentOrders',
            'pendingQuotesCount',
            'totalSalesValue',
            'recentQuotes',
            'recentSales',
            'overdueCount',
            'overdueAmount',
            'dueTodayCount',
            'dueTodayAmount',
            'dueNext7DaysCount',
            'dueNext7DaysAmount',
            'accumulatedOverdueAmount',
            'averageReceivableAmount',
            'averageSaleAmount',
            'currentMonthRevenue',
            'previousMonthRevenue',
            'monthlyChangePercent',
        ));
    }
}
