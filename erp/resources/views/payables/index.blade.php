@extends('layouts.app')

@section('title', 'Contas a Pagar')

@section('topbar-actions')
    <a href="{{ route('payables.export.xlsx', request()->all()) }}" class="btn btn-success flex items-center gap-2" style="background-color: #16a34a; border-color: #16a34a; color: white; border-radius: 8px;">
        <x-heroicon-o-document-arrow-down class="w-4 h-4"/> Exportar (XLSX)
    </a>
    <a href="{{ route('payables.create') }}" class="btn btn-primary" style="background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%); border-radius: 8px;">
        <x-heroicon-o-plus class="w-4 h-4"/> Novo Título
    </a>
@endsection

@section('content')
<div style="max-width: 1200px; margin: 0 auto; padding-bottom: 60px;">
    <!-- Filtros -->
    <div class="card mb-4 shadow-sm" style="border-radius: 12px; padding: 16px; border: 1px solid #e2e8f0;">
        <form method="GET" action="{{ route('payables.index') }}" id="filter-form" class="flex flex-col gap-3">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
                <!-- Busca -->
                <div style="position: relative;">
                    <label class="form-label" style="font-size: 11px; font-weight: 600; color: #64748b; margin-bottom: 4px; display: block;">Buscar Código/Fornecedor</label>
                    <div style="position: relative;">
                        <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8;">
                            <x-heroicon-o-magnifying-glass class="w-4 h-4" />
                        </span>
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                            placeholder="Buscar..." style="padding-left: 36px; border-radius: 8px;">
                    </div>
                </div>

                <!-- Status -->
                <div>
                    <label class="form-label" style="font-size: 11px; font-weight: 600; color: #64748b; margin-bottom: 4px; display: block;">Status</label>
                    <select name="status" class="form-control" style="border-radius: 8px;" onchange="this.form.submit()">
                        <option value="">Todos os status</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Data Início -->
                <div>
                    <label class="form-label" style="font-size: 11px; font-weight: 600; color: #64748b; margin-bottom: 4px; display: block;">Competência De</label>
                    <input type="date" name="start_date" value="{{ request('start_date') }}" class="form-control" style="border-radius: 8px;" onchange="this.form.submit()">
                </div>

                <!-- Data Fim -->
                <div>
                    <label class="form-label" style="font-size: 11px; font-weight: 600; color: #64748b; margin-bottom: 4px; display: block;">Competência Até</label>
                    <input type="date" name="end_date" value="{{ request('end_date') }}" class="form-control" style="border-radius: 8px;" onchange="this.form.submit()">
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <button type="submit" class="btn btn-secondary btn-sm" style="border-radius: 8px;">Filtrar</button>
                @if(request()->anyFilled(['search', 'status', 'start_date', 'end_date']))
                    <a href="{{ route('payables.index') }}" class="btn btn-secondary btn-sm"
                        style="border-radius: 8px; color: #ef4444;">Limpar</a>
                @endif
            </div>
        </form>
    </div>

    <!-- Listagem -->
    <div class="card shadow-sm" style="border-radius: 12px; padding: 0; overflow: hidden; border: 1px solid #e2e8f0;">
        <div class="table-wrap">
            <table class="table mb-0">
                <thead>
                    <tr style="background: #f8fafc;">
                        <th style="padding: 10px 14px;">Código</th>
                        <th>Fornecedor</th>
                        <th>Competência</th>
                        <th>Valor Total</th>
                        <th>Valor Líquido</th>
                        <th>Status</th>
                        <th style="width:120px; text-align:right; padding-right: 14px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payables as $pay)
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 12px 14px;"><strong>{{ $pay->code }}</strong></td>
                            <td>{{ $pay->supplier->name ?? 'Fornecedor Avulso' }}</td>
                            <td>{{ \Carbon\Carbon::parse($pay->competence_date)->format('d/m/Y') }}</td>
                            <td style="font-family: monospace; font-weight: 600;">R$ {{ number_format($pay->total_amount, 2, ',', '.') }}</td>
                            <td style="font-family: monospace; font-weight: 600; color: #dc2626;">R$ {{ number_format($pay->net_amount, 2, ',', '.') }}</td>
                            <td>
                                @php
                                    $colorClass = match($pay->status) {
                                        \App\Enums\PaymentStatus::Pending => 'badge-warning',
                                        \App\Enums\PaymentStatus::Paid => 'badge-success',
                                        \App\Enums\PaymentStatus::PartiallyPaid => 'badge-info',
                                        \App\Enums\PaymentStatus::Cancelled => 'badge-danger',
                                        default => 'badge-secondary'
                                    };
                                @endphp
                                <span class="badge {{ $colorClass }}">
                                    {{ $pay->status->label() }}
                                </span>
                            </td>
                            <td style="text-align:right; padding-right: 14px;">
                                <a href="{{ route('payables.show', $pay) }}" class="btn btn-secondary btn-sm" style="padding: 6px; border-radius: 6px;" title="Ver Detalhes">
                                    <x-heroicon-o-eye class="w-4 h-4"/>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align:center; padding:32px; color:#94a3b8">
                                Nenhum título a pagar encontrado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($payables->hasPages())
            <div class="p-4 border-t border-slate-200">
                {{ $payables->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
