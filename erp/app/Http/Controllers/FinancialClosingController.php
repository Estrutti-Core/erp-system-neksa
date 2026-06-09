<?php

namespace App\Http\Controllers;

use App\Models\Receivable;
use App\Models\ReceivableInstallment;
use App\Models\Payable;
use App\Models\PayableInstallment;
use App\Models\Sale;
use App\Models\ServiceOrder;
use App\Services\ExportXlsxService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class FinancialClosingController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->input('month', Carbon::now()->format('Y-m'));
        try {
            $date = Carbon::createFromFormat('Y-m', $month);
        } catch (\Exception $e) {
            $month = Carbon::now()->format('Y-m');
            $date = Carbon::createFromFormat('Y-m', $month);
        }
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        // 1. Regime de Caixa (Fluxo Financeiro de Caixa no Mês)
        $cashInflow = (float) ReceivableInstallment::where('status', \App\Enums\InstallmentStatus::Paid->value)
            ->whereBetween('paid_at', [$startOfMonth, $endOfMonth])
            ->sum('paid_amount');

        $cashOutflow = (float) PayableInstallment::where('status', \App\Enums\InstallmentStatus::Paid->value)
            ->whereBetween('paid_at', [$startOfMonth, $endOfMonth])
            ->sum('paid_amount');

        $cashBalance = $cashInflow - $cashOutflow;

        // 2. Regime de Competência (Títulos com Competência no Mês)
        $accrualInflow = (float) Receivable::whereBetween('competence_date', [$startOfMonth, $endOfMonth])
            ->sum('total_amount');

        $accrualOutflow = (float) Payable::whereBetween('competence_date', [$startOfMonth, $endOfMonth])
            ->sum('total_amount');

        $accrualBalance = $accrualInflow - $accrualOutflow;

        // 3. Faturamento detalhado no mês por tipo de Anexo
        $revenueComercio = (float) Receivable::whereBetween('competence_date', [$startOfMonth, $endOfMonth])
            ->where('source_type', Sale::class)
            ->sum('total_amount');

        $revenueServicos = (float) Receivable::whereBetween('competence_date', [$startOfMonth, $endOfMonth])
            ->where('source_type', ServiceOrder::class)
            ->sum('total_amount');

        $revenueAvulsa = (float) Receivable::whereBetween('competence_date', [$startOfMonth, $endOfMonth])
            ->whereNull('source_type')
            ->sum('total_amount');

        $totalRevenueMonth = $revenueComercio + $revenueServicos + $revenueAvulsa;

        // 4. RBT12 (Faturamento dos 12 meses anteriores)
        $rbt12Start = $startOfMonth->copy()->subMonths(12);
        $rbt12End = $startOfMonth->copy()->subDay(); // Fim do mês anterior

        $rbt12Total = (float) Receivable::whereBetween('competence_date', [$rbt12Start, $rbt12End])
            ->sum('total_amount');

        // Cálculo de alíquotas efetivas
        $comercioEffectiveRate = $this->calculateEffectiveRate($rbt12Total, 'comercio');
        $servicosEffectiveRate = $this->calculateEffectiveRate($rbt12Total, 'servicos');

        // Impostos calculados sobre o mês
        $taxComercio = $revenueComercio * ($comercioEffectiveRate / 100);
        $taxServicos = ($revenueServicos + $revenueAvulsa) * ($servicosEffectiveRate / 100);
        $totalTaxDue = $taxComercio + $taxServicos;

        // Lançamentos de caixa detalhados
        $cashEntries = DB::table('receivable_installments as ri')
            ->join('receivables as r', 'r.id', '=', 'ri.receivable_id')
            ->select('ri.paid_at as date', 'r.description', 'ri.paid_amount as amount', DB::raw("'receita' as type"), 'ri.payment_method')
            ->where('ri.status', \App\Enums\InstallmentStatus::Paid->value)
            ->whereBetween('ri.paid_at', [$startOfMonth, $endOfMonth])
            ->unionAll(
                DB::table('payable_installments as pi')
                    ->join('payables as p', 'p.id', '=', 'pi.payable_id')
                    ->select('pi.paid_at as date', 'p.description', 'pi.paid_amount as amount', DB::raw("'despesa' as type"), 'pi.payment_method')
                    ->where('pi.status', \App\Enums\InstallmentStatus::Paid->value)
                    ->whereBetween('pi.paid_at', [$startOfMonth, $endOfMonth])
            )
            ->orderBy('date', 'asc')
            ->get();

        return view('financial.closing', compact(
            'month',
            'cashInflow',
            'cashOutflow',
            'cashBalance',
            'accrualInflow',
            'accrualOutflow',
            'accrualBalance',
            'rbt12Total',
            'revenueComercio',
            'revenueServicos',
            'revenueAvulsa',
            'totalRevenueMonth',
            'comercioEffectiveRate',
            'servicosEffectiveRate',
            'taxComercio',
            'taxServicos',
            'totalTaxDue',
            'cashEntries'
        ));
    }

    public function xlsx(Request $request)
    {
        $month = $request->input('month', Carbon::now()->format('Y-m'));
        try {
            $date = Carbon::createFromFormat('Y-m', $month);
        } catch (\Exception $e) {
            $month = Carbon::now()->format('Y-m');
            $date = Carbon::createFromFormat('Y-m', $month);
        }
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth   = $date->copy()->endOfMonth();

        // Busca lançamentos de caixa do mês
        $entries = DB::table('receivable_installments as ri')
            ->join('receivables as r', 'r.id', '=', 'ri.receivable_id')
            ->select(
                'ri.paid_at as date',
                DB::raw("'receita' as type"),
                'r.description',
                'ri.paid_amount as amount'
            )
            ->where('ri.status', \App\Enums\InstallmentStatus::Paid->value)
            ->whereBetween('ri.paid_at', [$startOfMonth, $endOfMonth])
            ->unionAll(
                DB::table('payable_installments as pi')
                    ->join('payables as p', 'p.id', '=', 'pi.payable_id')
                    ->select(
                        'pi.paid_at as date',
                        DB::raw("'despesa' as type"),
                        'p.description',
                        'pi.paid_amount as amount'
                    )
                    ->where('pi.status', \App\Enums\InstallmentStatus::Paid->value)
                    ->whereBetween('pi.paid_at', [$startOfMonth, $endOfMonth])
            )
            ->orderBy('date', 'asc')
            ->get();

        // Monta rows em array para usar com LazyCollection
        $rows = $entries->map(fn ($e) => [
            Carbon::parse($e->date)->format('Y-m-d'),
            ucfirst($e->type),
            $e->description,
            (float) $e->amount,
        ]);

        $headers = ['Data', 'Tipo', 'Descrição', 'Valor'];
        // Coluna A = Data (date), D = Valor (currency)
        $formats = [
            'A' => 'date',
            'D' => 'currency',
        ];

        // Illuminate\Support\Collection já possui ->lazy() nativo — compatível com ExportXlsxService::export()
        return app(ExportXlsxService::class)->export(
            'Fechamento ' . $month,
            $headers,
            $rows,
            fn ($row) => $row,
            $formats,
            'Fechamento_' . $month . '.xlsx'
        );
    }

    public function pdf(Request $request)
    {
        $month = $request->input('month', Carbon::now()->format('Y-m'));
        try {
            $date = Carbon::createFromFormat('Y-m', $month);
        } catch (\Exception $e) {
            $month = Carbon::now()->format('Y-m');
            $date = Carbon::createFromFormat('Y-m', $month);
        }
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        // 1. Regime de Caixa
        $cashInflow = (float) ReceivableInstallment::where('status', \App\Enums\InstallmentStatus::Paid->value)
            ->whereBetween('paid_at', [$startOfMonth, $endOfMonth])
            ->sum('paid_amount');

        $cashOutflow = (float) PayableInstallment::where('status', \App\Enums\InstallmentStatus::Paid->value)
            ->whereBetween('paid_at', [$startOfMonth, $endOfMonth])
            ->sum('paid_amount');

        $cashBalance = $cashInflow - $cashOutflow;

        // 2. Regime de Competência
        $accrualInflow = (float) Receivable::whereBetween('competence_date', [$startOfMonth, $endOfMonth])
            ->sum('total_amount');

        $accrualOutflow = (float) Payable::whereBetween('competence_date', [$startOfMonth, $endOfMonth])
            ->sum('total_amount');

        $accrualBalance = $accrualInflow - $accrualOutflow;

        // 3. Faturamento por Anexo
        $revenueComercio = (float) Receivable::whereBetween('competence_date', [$startOfMonth, $endOfMonth])
            ->where('source_type', Sale::class)
            ->sum('total_amount');

        $revenueServicos = (float) Receivable::whereBetween('competence_date', [$startOfMonth, $endOfMonth])
            ->where('source_type', ServiceOrder::class)
            ->sum('total_amount');

        $revenueAvulsa = (float) Receivable::whereBetween('competence_date', [$startOfMonth, $endOfMonth])
            ->whereNull('source_type')
            ->sum('total_amount');

        $totalRevenueMonth = $revenueComercio + $revenueServicos + $revenueAvulsa;

        // 4. RBT12
        $rbt12Start = $startOfMonth->copy()->subMonths(12);
        $rbt12End = $startOfMonth->copy()->subDay();

        $rbt12Total = (float) Receivable::whereBetween('competence_date', [$rbt12Start, $rbt12End])
            ->sum('total_amount');

        $comercioEffectiveRate = $this->calculateEffectiveRate($rbt12Total, 'comercio');
        $servicosEffectiveRate = $this->calculateEffectiveRate($rbt12Total, 'servicos');

        $taxComercio = $revenueComercio * ($comercioEffectiveRate / 100);
        $taxServicos = ($revenueServicos + $revenueAvulsa) * ($servicosEffectiveRate / 100);
        $totalTaxDue = $taxComercio + $taxServicos;

        // Configuração da empresa
        $company = \App\Models\Company::first();

        $pdf = PDF::loadView('financial.closing_pdf', compact(
            'month',
            'cashInflow',
            'cashOutflow',
            'cashBalance',
            'accrualInflow',
            'accrualOutflow',
            'accrualBalance',
            'rbt12Total',
            'revenueComercio',
            'revenueServicos',
            'revenueAvulsa',
            'totalRevenueMonth',
            'comercioEffectiveRate',
            'servicosEffectiveRate',
            'taxComercio',
            'taxServicos',
            'totalTaxDue',
            'company'
        ));

        // Nomenclatura padronizada
        $filename = 'Closing_' . $month . '.pdf';

        return $pdf->stream($filename);
    }

    private function calculateEffectiveRate(float $rbt12, string $type): float
    {
        if ($rbt12 <= 180000.00) {
            return $type === 'comercio' ? 4.0 : 6.0;
        }

        if ($type === 'comercio') {
            if ($rbt12 <= 360000.00) {
                return (($rbt12 * 0.073) - 5940.00) / $rbt12 * 100;
            } elseif ($rbt12 <= 720000.00) {
                return (($rbt12 * 0.095) - 13860.00) / $rbt12 * 100;
            } elseif ($rbt12 <= 1800000.00) {
                return (($rbt12 * 0.107) - 22500.00) / $rbt12 * 100;
            } elseif ($rbt12 <= 3600000.00) {
                return (($rbt12 * 0.143) - 87300.00) / $rbt12 * 100;
            } else {
                return (($rbt12 * 0.19) - 378000.00) / $rbt12 * 100;
            }
        } else {
            if ($rbt12 <= 360000.00) {
                return (($rbt12 * 0.112) - 9360.00) / $rbt12 * 100;
            } elseif ($rbt12 <= 720000.00) {
                return (($rbt12 * 0.135) - 17640.00) / $rbt12 * 100;
            } elseif ($rbt12 <= 1800000.00) {
                return (($rbt12 * 0.16) - 35640.00) / $rbt12 * 100;
            } elseif ($rbt12 <= 3600000.00) {
                return (($rbt12 * 0.21) - 125640.00) / $rbt12 * 100;
            } else {
                return (($rbt12 * 0.33) - 648000.00) / $rbt12 * 100;
            }
        }
    }
}
