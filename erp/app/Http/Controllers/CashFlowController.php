<?php

namespace App\Http\Controllers;

use App\Services\FinancialService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashFlowController extends Controller
{
    public function __construct(
        private readonly FinancialService $financialService
    ) {}

    public function index(Request $request): View
    {
        $startDate = $request->filled('start_date') 
            ? Carbon::parse($request->input('start_date')) 
            : Carbon::today()->subDays(30);

        $endDate = $request->filled('end_date') 
            ? Carbon::parse($request->input('end_date')) 
            : Carbon::today();

        $regime = $request->input('regime', 'caixa');

        $flow = $this->financialService->getCashFlow($startDate, $endDate, $regime);
        $accounts = \App\Models\FinancialAccount::where('is_active', true)->with('type')->get();
        $consolidated_balance = $accounts->sum('balance');

        return view('financial.cash_flow', [
            'timeline' => $flow['timeline'],
            'total_inputs' => $flow['total_inputs'],
            'total_outputs' => $flow['total_outputs'],
            'net_balance' => $flow['net_balance'],
            'startDate' => $startDate->toDateString(),
            'endDate' => $endDate->toDateString(),
            'regime' => $regime,
            'accounts' => $accounts,
            'consolidated_balance' => $consolidated_balance,
        ]);
    }
}
