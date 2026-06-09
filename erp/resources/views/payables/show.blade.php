@extends('layouts.app')

@section('title', 'Detalhes do Contas a Pagar')

@section('topbar-actions')
    <a href="{{ route('payables.index') }}" class="btn btn-secondary">
        <x-heroicon-o-arrow-left class="w-4 h-4"/> Voltar
    </a>
    <a href="{{ route('payables.print', $payable) }}" target="_blank" class="btn btn-secondary">
        <x-heroicon-o-printer class="w-4 h-4"/> Imprimir
    </a>
    @if($payable->status !== \App\Enums\PaymentStatus::Cancelled)
        <form method="POST" action="{{ route('payables.cancel', $payable) }}" onsubmit="return confirm('Deseja realmente cancelar este título?')">
            @csrf
            <button type="submit" class="btn btn-secondary text-red-600 border-red-200 hover:bg-red-50">
                <x-heroicon-o-x-circle class="w-4 h-4"/> Cancelar Título
            </button>
        </form>
    @endif
@endsection

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <!-- Detalhes do Título -->
        <div class="card">
            <div class="card-header flex justify-between items-center">
                <h2 class="card-title">Título {{ $payable->code }}</h2>
                @php
                    $colorClass = match($payable->status) {
                        \App\Enums\PaymentStatus::Pending => 'bg-amber-100 text-amber-800',
                        \App\Enums\PaymentStatus::Paid => 'bg-green-100 text-green-800',
                        \App\Enums\PaymentStatus::PartiallyPaid => 'bg-blue-100 text-blue-800',
                        \App\Enums\PaymentStatus::Cancelled => 'bg-red-100 text-red-800',
                        default => 'bg-gray-100 text-gray-800'
                    };
                @endphp
                <span class="px-2 py-1 rounded text-sm font-semibold {{ $colorClass }}">
                    {{ $payable->status->label() }}
                </span>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <span class="text-xs text-gray-500 block">Fornecedor</span>
                        <strong>{{ $payable->supplier->name ?? 'Fornecedor Avulso' }}</strong>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 block">Data de Competência</span>
                        <strong>{{ \Carbon\Carbon::parse($payable->competence_date)->format('d/m/Y') }}</strong>
                    </div>
                    <div class="md:col-span-2">
                        <span class="text-xs text-gray-500 block">Descrição / Histórico</span>
                        <strong>{{ $payable->description }}</strong>
                    </div>
                    @if($payable->source_type)
                        <div>
                            <span class="text-xs text-gray-500 block">Documento de Origem</span>
                            <span class="text-sm font-medium text-gray-700">
                                {{ class_basename($payable->source_type) }} #{{ $payable->source_id }}
                            </span>
                        </div>
                    @endif
                    @if($payable->notes)
                        <div class="md:col-span-2">
                            <span class="text-xs text-gray-500 block">Observações</span>
                            <p class="text-sm text-gray-600 bg-gray-50 p-2 rounded border border-gray-100">{{ $payable->notes }}</p>
                        </div>
                    @endif
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6 pt-6 border-t border-gray-100">
                    <div>
                        <span class="text-xs text-gray-500 block">Valor Original</span>
                        <span class="text-lg font-semibold">R$ {{ number_format($payable->total_amount, 2, ',', '.') }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 block">Descontos</span>
                        <span class="text-lg font-semibold text-green-600">R$ {{ number_format($payable->discount_amount, 2, ',', '.') }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 block">Juros / Multas</span>
                        <span class="text-lg font-semibold text-amber-600">R$ {{ number_format($payable->interest_amount, 2, ',', '.') }}</span>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 block">Valor Líquido</span>
                        <span class="text-lg font-bold text-indigo-700">R$ {{ number_format($payable->net_amount, 2, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Parcelas -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Cronograma de Parcelas</h2>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Nº</th>
                                <th>Vencimento</th>
                                <th>Valor Parcela</th>
                                <th>Juros / Desconto</th>
                                <th>Valor Pago</th>
                                <th>Data Pagto</th>
                                <th>Status</th>
                                <th style="width:120px; text-align:right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($payable->installments as $inst)
                                <tr>
                                    <td>{{ $inst->installment_number }}</td>
                                    <td>{{ \Carbon\Carbon::parse($inst->due_date)->format('d/m/Y') }}</td>
                                    <td>R$ {{ number_format($inst->amount, 2, ',', '.') }}</td>
                                    <td>
                                        <span class="text-amber-600">+{{ number_format($inst->interest_amount, 2, ',', '.') }}</span> /
                                        <span class="text-green-600">-{{ number_format($inst->discount_amount, 2, ',', '.') }}</span>
                                    </td>
                                    <td><strong>R$ {{ number_format($inst->paid_amount, 2, ',', '.') }}</strong></td>
                                    <td>{{ $inst->paid_at ? \Carbon\Carbon::parse($inst->paid_at)->format('d/m/Y') : '-' }}</td>
                                    <td>
                                        @php
                                            $instColor = match($inst->status) {
                                                \App\Enums\InstallmentStatus::Pending => 'bg-amber-100 text-amber-800',
                                                \App\Enums\InstallmentStatus::Paid => 'bg-green-100 text-green-800',
                                                \App\Enums\InstallmentStatus::Cancelled => 'bg-red-100 text-red-800',
                                                default => 'bg-gray-100 text-gray-800'
                                            };
                                        @endphp
                                        <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $instColor }}">
                                            {{ $inst->status->label() }}
                                        </span>
                                    </td>
                                    <td style="text-align:right">
                                        @if($inst->status === \App\Enums\InstallmentStatus::Pending && $payable->status !== \App\Enums\PaymentStatus::Cancelled)
                                            <button type="button" class="btn btn-primary btn-sm" onclick="openPaymentModal({{ $inst->id }}, {{ $inst->amount }})">
                                                Baixar
                                            </button>
                                        @else
                                            <span class="text-xs text-gray-400">Sem ações</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Barra Lateral: Histórico de Auditoria -->
    <div class="space-y-6">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Logs de Auditoria</h2>
            </div>
            <div class="card-body">
                <div class="flow-root">
                    <ul role="list" class="-mb-8">
                        @forelse($payable->events as $event)
                            <li>
                                <div class="relative pb-8">
                                    @if(!$loop->last)
                                        <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                    @endif
                                    <div class="relative flex space-x-3">
                                        <div>
                                            <span class="h-8 w-8 rounded-full bg-indigo-50 flex items-center justify-center ring-8 ring-white">
                                                <x-heroicon-o-shield-check class="w-5 h-5 text-indigo-600"/>
                                            </span>
                                        </div>
                                        <div class="flex-1 min-w-0 pt-1.5">
                                            <p class="text-xs text-gray-700">
                                                Evento <strong>{{ $event->event_type }}</strong> por {{ $event->user->name ?? 'Sistema' }}
                                            </p>
                                            <div class="text-right text-xs whitespace-nowrap text-gray-500">
                                                <time>{{ $event->created_at->format('d/m/Y H:i') }}</time>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        @empty
                            <p class="text-sm text-gray-500">Nenhum evento registrado.</p>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Baixa de Parcela -->
<div id="paymentModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closePaymentModal()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-middle bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form id="paymentForm" method="POST" action="">
                @csrf
                <div class="bg-white px-6 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4" id="modal-title">
                        Registrar Pagamento
                    </h3>

                    <div class="space-y-4">
                        <div>
                            <label class="form-label">Valor da Parcela (R$)</label>
                            <input type="text" id="modal_amount_label" class="form-control bg-gray-50" readonly>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="discount_amount" class="form-label">Desconto (R$)</label>
                                <input type="number" step="0.01" name="discount_amount" id="discount_amount" class="form-control" value="0.00" oninput="recalculateNetPayment()">
                            </div>
                            <div>
                                <label for="interest_amount" class="form-label">Juros / Multa (R$)</label>
                                <input type="number" step="0.01" name="interest_amount" id="interest_amount" class="form-control" value="0.00" oninput="recalculateNetPayment()">
                            </div>
                        </div>
                        <div>
                            <label for="paid_amount" class="form-label">Valor Pago (R$)</label>
                            <input type="number" step="0.01" name="paid_amount" id="paid_amount" class="form-control font-semibold text-lg" required>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="payment_method" class="form-label">Método</label>
                                <select name="payment_method" id="payment_method" class="form-control" required>
                                    @foreach($paymentMethods as $method)
                                        <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="paid_at" class="form-label">Data de Pagamento</label>
                                <input type="date" name="paid_at" id="paid_at" class="form-control" value="{{ today()->toDateString() }}" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-6 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-3">
                    <button type="submit" class="btn btn-primary">Confirmar Pagamento</button>
                    <button type="button" class="btn btn-secondary" onclick="closePaymentModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    let currentInstallmentAmount = 0;

    function openPaymentModal(installmentId, amount) {
        currentInstallmentAmount = amount;
        
        document.getElementById('modal_amount_label').value = amount.toFixed(2);
        document.getElementById('discount_amount').value = '0.00';
        document.getElementById('interest_amount').value = '0.00';
        document.getElementById('paid_amount').value = amount.toFixed(2);
        
        const form = document.getElementById('paymentForm');
        form.action = `{{ route('payables.pay', ['payable' => $payable->id, 'installment' => ':id']) }}`.replace(':id', installmentId);
        
        document.getElementById('paymentModal').classList.remove('hidden');
    }

    function closePaymentModal() {
        document.getElementById('paymentModal').classList.add('hidden');
    }

    function recalculateNetPayment() {
        const discount = parseFloat(document.getElementById('discount_amount').value) || 0;
        const interest = parseFloat(document.getElementById('interest_amount').value) || 0;
        const net = (currentInstallmentAmount + interest) - discount;
        document.getElementById('paid_amount').value = net.toFixed(2);
    }
</script>
@endpush
@endsection
