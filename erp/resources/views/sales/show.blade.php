@extends('layouts.app')

@section('title', 'Detalhes da Venda ' . $sale->code)

@section('content')
<div style="max-width: 1200px; margin: 0 auto; padding-bottom: 60px;">
    <!-- Alertas -->
    @if(session('success'))
        <div style="background: #ecfdf5; border: 1px solid #10b981; border-radius: 8px; padding: 14px; margin-bottom: 20px; color: #065f46; font-weight: 500; font-size: 14px;" class="flex items-center gap-2">
            <x-heroicon-o-check-circle class="w-5 h-5" style="color: #10b981;"/>
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div style="background: #fef2f2; border: 1px solid #ef4444; border-radius: 8px; padding: 14px; margin-bottom: 20px; color: #991b1b; font-weight: 500; font-size: 14px;">
            @foreach($errors->all() as $error)
                <div class="flex items-center gap-2 mb-1">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5" style="color: #ef4444;"/>
                    {{ $error }}
                </div>
            @endforeach
        </div>
    @endif

    <!-- Cabeçalho -->
    <div class="flex items-center justify-between mb-4">
        <a href="{{ route('sales.index') }}" class="btn btn-secondary" style="border-radius: 8px;">
            <x-heroicon-o-arrow-left class="w-4 h-4"/> Voltar
        </a>

        <div class="flex gap-2">
            <a href="{{ route('sales.pdf', $sale) }}" class="btn btn-secondary" style="border-radius: 8px;" target="_blank">
                <x-heroicon-o-document-arrow-down class="w-4 h-4"/> PDF
            </a>
            @can('update', $sale)
                @if($sale->status !== App\Enums\SaleStatus::Cancelled)
                    <form method="POST" action="{{ route('sales.cancel', $sale) }}" onsubmit="return confirm('Tem certeza que deseja CANCELAR esta venda? Isso estornará o estoque automaticamente.');">
                        @csrf
                        <button type="submit" class="btn btn-danger flex items-center gap-2" style="border-radius: 8px;">
                            <x-heroicon-o-x-mark class="w-4 h-4"/> Cancelar Venda
                        </button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    <!-- Grid Layout de 2 Colunas -->
    <div style="display: grid; grid-template-columns: 1.8fr 1.2fr; gap: 24px;">
        
        <!-- COLUNA PRINCIPAL (ESQUERDA) -->
        <div>
            <!-- Resumo e Itens da Venda -->
            <div class="card shadow-sm mb-4" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
                <div class="flex items-center justify-between mb-4 border-bottom pb-3">
                    <div>
                        <span class="badge badge-{{ $sale->status->color() }}">{{ $sale->status->label() }}</span>
                        <h2 style="font-size: 20px; font-weight: 800; color: #0f172a; margin-top: 6px;">Venda {{ $sale->code }}</h2>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 12px; color: #64748b;">Faturado em</span>
                        <div style="font-weight: 600; color: #334155; font-size: 14px;">{{ $sale->created_at->format('d/m/Y H:i') }}</div>
                    </div>
                </div>

                <h3 style="font-size: 13px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;">Itens da Venda</h3>
                <div class="table-wrap mb-4" style="border: 1px solid #e2e8f0; border-radius: 8px;">
                    <table>
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th style="padding: 10px 14px;">Descrição</th>
                                <th style="text-align: center;">Qtd</th>
                                <th style="text-align: right;">Preço Unit.</th>
                                <th style="text-align: right; padding-right: 14px;">Preço Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sale->items as $item)
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 12px 14px;">
                                        <div style="font-weight: 600; color: #1e293b;">{{ $item->description }}</div>
                                        <div style="font-size: 11px; color: #64748b;">SKU: {{ $item->product?->sku ?: '—' }}</div>
                                    </td>
                                    <td style="text-align: center; font-weight: 500; color: #334155;">{{ number_format($item->quantity, 0, ',', '.') }} {{ $item->unit }}</td>
                                    <td style="text-align: right; font-family: monospace; color: #334155;">R$ {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                                    <td style="text-align: right; padding-right: 14px; font-family: monospace; font-weight: 700; color: #0f172a;">R$ {{ number_format($item->total_price, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Resumo Financeiro da Venda -->
                <div style="display: flex; justify-content: flex-end;">
                    <div style="width: 320px;">
                        <div class="flex justify-between items-center mb-2" style="font-size: 13px; color: #64748b;">
                            <span>Subtotal Itens</span>
                            <span style="font-weight: 600; color: #334155;">R$ {{ number_format($sale->items_amount, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between items-center mb-2" style="font-size: 13px; color: #64748b;">
                            <span>Valor do Frete</span>
                            <span style="font-weight: 600; color: #334155;">R$ {{ number_format($sale->freight_price ?? 0.00, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between items-center mb-3" style="font-size: 13px; color: #ef4444;">
                            <span>Desconto Aplicado</span>
                            <span style="font-weight: 600;">- R$ {{ number_format($sale->discount_amount, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between items-center pt-3 border-t" style="font-size: 14px; font-weight: 700; color: #0f172a;">
                            <span>Total Líquido</span>
                            <span style="color: #10b981; font-size: 20px; font-weight: 800;">R$ {{ number_format($sale->total_amount + ($sale->freight_price ?? 0.00), 2, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dados de Expedição, Transporte e Logística -->
            <div class="card shadow-sm mb-4" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
                <h3 class="font-bold mb-3 flex items-center gap-2" style="font-size: 15px; color: #1e293b;">
                    <x-heroicon-o-truck class="w-5 h-5 text-indigo-600"/> Dados de Transporte & Logística
                </h3>
                
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; font-size: 13px; line-height: 1.6;">
                    <div>
                        <span style="color: #64748b;">Transportadora</span>
                        <div style="font-weight: 600; color: #334155;">{{ $sale->carrier ?? '—' }}</div>
                    </div>
                    <div>
                        <span style="color: #64748b;">Modalidade do Frete</span>
                        <div style="font-weight: 600; color: #334155;">
                            {{ [
                                0 => 'Por conta do Emitente (CIF)',
                                1 => 'Por conta do Destinatário (FOB)',
                                2 => 'Por conta de Terceiros',
                                3 => 'Próprio Emitente',
                                4 => 'Próprio Destinatário',
                                9 => 'Sem Frete'
                            ][$sale->freight_type] ?? 'Sem Frete' }}
                        </div>
                    </div>
                    <div>
                        <span style="color: #64748b;">Valor do Frete</span>
                        <div style="font-weight: 600; color: #334155;">R$ {{ number_format($sale->freight_price ?? 0, 2, ',', '.') }}</div>
                    </div>
                    <div>
                        <span style="color: #64748b;">Volumes</span>
                        <div style="font-weight: 600; color: #334155;">{{ $sale->volume ? number_format($sale->volume, 2, ',', '.') : '—' }}</div>
                    </div>
                    <div>
                        <span style="color: #64748b;">Peso Bruto</span>
                        <div style="font-weight: 600; color: #334155;">{{ $sale->weight_gross ? number_format($sale->weight_gross, 3, ',', '.') . ' kg' : '—' }}</div>
                    </div>
                    <div>
                        <span style="color: #64748b;">Peso Líquido</span>
                        <div style="font-weight: 600; color: #334155;">{{ $sale->weight_net ? number_format($sale->weight_net, 3, ',', '.') . ' kg' : '—' }}</div>
                    </div>
                    <div>
                        <span style="color: #64748b;">Prazo de Entrega</span>
                        <div style="font-weight: 600; color: #334155;">{{ $sale->delivery_deadline ?? '—' }}</div>
                    </div>
                    <div>
                        <span style="color: #64748b;">Garantia</span>
                        <div style="font-weight: 600; color: #334155;">{{ $sale->warranty ?? '—' }}</div>
                    </div>
                    <div>
                        <span style="color: #64748b;">Validade da Proposta</span>
                        <div style="font-weight: 600; color: #334155;">{{ $sale->validity ?? '—' }}</div>
                    </div>
                </div>
            </div>

            <!-- Anexos Inline (Propostas, Comprovantes, etc.) -->
            <div class="card shadow-sm mb-4" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px;">
                <h3 class="font-bold mb-3 flex items-center gap-2" style="font-size: 15px; color: #1e293b;">
                    <x-heroicon-o-paper-clip class="w-5 h-5 text-indigo-600"/> Anexos e Documentos
                </h3>

                @if($sale->attachments->isNotEmpty())
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 12px; margin-bottom: 20px;">
                        @foreach($sale->attachments as $att)
                            <div style="position: relative; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: #f8fafc; font-size: 12px;">
                                @if($att->isImage())
                                    <img src="{{ $att->url }}" alt="{{ $att->original_name }}" style="width: 100%; aspect-ratio: 1; object-fit: cover; display: block;">
                                @else
                                    <div style="width: 100%; aspect-ratio: 1; display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 4px; background: #f1f5f9;">
                                        <x-heroicon-o-document class="w-8 h-8" style="color: #94a3b8;"/>
                                        <span style="font-size: 10px; color: #64748b; font-weight: 700; text-transform: uppercase;">{{ pathinfo($att->original_name, PATHINFO_EXTENSION) }}</span>
                                    </div>
                                @endif
                                <div style="padding: 8px;">
                                    <div style="font-weight: 600; color: #475569; overflow: hidden; white-space: nowrap; text-overflow: ellipsis;" title="{{ $att->original_name }}">{{ $att->original_name }}</div>
                                    <div style="font-size: 10px; color: #94a3b8; margin-top: 2px;">{{ $att->formatted_size }}</div>
                                </div>
                                <div style="display: flex; gap: 4px; padding: 0 8px 8px;">
                                    <a href="{{ $att->url }}" target="_blank" class="btn btn-secondary btn-sm" style="flex: 1; justify-content: center; padding: 3px; font-size: 11px;">Ver</a>
                                    @can('update', $sale)
                                        <form method="POST" action="{{ route('sales.attachments.destroy', [$sale, $att]) }}" onsubmit="return confirm('Remover anexo?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-secondary btn-sm" style="padding: 3px 6px; color: #dc2626;"><x-heroicon-o-trash class="w-3.5 h-3.5"/></button>
                                        </form>
                                    @endcan
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                @can('update', $sale)
                    @if($sale->status !== App\Enums\SaleStatus::Cancelled)
                        <form method="POST" action="{{ route('sales.attachments.store', $sale) }}" enctype="multipart/form-data">
                            @csrf
                            <label style="display: block; border: 2px dashed #cbd5e1; border-radius: 8px; padding: 20px; text-align: center; cursor: pointer; color: #64748b; font-size: 13px;" id="upload-label">
                                <x-heroicon-o-cloud-arrow-up class="w-8 h-8" style="margin: 0 auto 6px; display: block; color: #94a3b8;"/>
                                Clique para selecionar imagens, propostas ou comprovantes de recebimento
                                <input type="file" name="attachments[]" multiple accept="image/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" style="display: none;" id="upload-input">
                            </label>
                            <div id="upload-preview" style="display: none; margin-top: 8px; font-size: 12px; color: #475569; font-weight: 600;"></div>
                            <button type="submit" class="btn btn-primary w-full mt-3" style="justify-content: center;">Enviar Anexos</button>
                        </form>
                    @endif
                @endcan
            </div>
        </div>

        <!-- COLUNA LATERAL (DIREITA) -->
        <div>
            <!-- Card Consolidado do Cliente -->
            <div class="card shadow-sm mb-4" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px;">
                <h3 style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;">Dados do Cliente</h3>
                <div style="font-weight: 700; color: #0f172a; font-size: 15px;">{{ $sale->client->name }}</div>
                <div style="font-size: 12px; color: #64748b; margin-top: 2px;">CNPJ/CPF: {{ $sale->client->formatted_document }}</div>
                @if($sale->client->email)
                    <div style="font-size: 12px; color: #475569; margin-top: 4px;">E-mail: {{ $sale->client->email }}</div>
                @endif
                @if($sale->client->phone)
                    <div style="font-size: 12px; color: #475569; margin-top: 2px;">Telefone: {{ $sale->client->phone }}</div>
                @endif

                <div style="border-top: 1px solid #f1f5f9; margin-top: 12px; padding-top: 12px;">
                    <div style="font-size: 11px; color: #94a3b8; font-weight: 600; text-transform: uppercase;">Endereço de Entrega</div>
                    <div style="font-size: 12px; color: #334155; margin-top: 4px; line-height: 1.4;">
                        {{ $sale->clientAddress->street }}, {{ $sale->clientAddress->number }}<br>
                        {{ $sale->clientAddress->neighborhood }}<br>
                        {{ $sale->clientAddress->city }} / {{ $sale->clientAddress->state }}
                    </div>
                </div>
            </div>

            <!-- Notas de Observação ou Origem do Orçamento -->
            @if($sale->quote)
                <div class="card shadow-sm mb-4" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px;">
                    <h3 style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">Orçamento Origem</h3>
                    <a href="{{ route('quotes.show', $sale->quote) }}" class="flex items-center gap-2" style="font-weight: 700; color: #4f46e5; font-size: 13px; text-decoration: none;">
                        <x-heroicon-o-document-text class="w-5 h-5"/> {{ $sale->quote->code }}
                    </a>
                </div>
            @endif

            <!-- Formas de Pagamento e Parcelas do Contas a Receber -->
            <div class="card shadow-sm mb-4" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px;">
                <h3 style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;">Condições de Pagamento</h3>
                
                @if($sale->payments->isEmpty())
                    <p style="font-size: 12px; color: #64748b; margin-bottom: 12px;">Nenhum pagamento definido para esta venda.</p>
                    @if($sale->status === App\Enums\SaleStatus::Pending)
                        <a href="{{ route('sales.payment', $sale) }}" class="btn btn-primary w-full" style="justify-content: center; border-radius: 8px;">
                            Faturar / Definir Pagamento
                        </a>
                    @endif
                @else
                    <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px;">
                        @foreach($sale->payments as $payment)
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; font-size: 12px;">
                                <div class="flex justify-between font-semibold text-slate-800">
                                    <span>{{ $payment->payment_method }}</span>
                                    <span>R$ {{ number_format($payment->amount, 2, ',', '.') }}</span>
                                </div>
                                <div class="text-xs text-slate-500 mt-1">
                                    {{ $payment->installments_count }}x · Vencimento 1ª parc: {{ $payment->first_due_date->format('d/m/Y') }}
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Listagem das Parcelas do Contas a Receber -->
                    <h4 style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">Parcelas Financeiras</h4>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        @forelse($sale->installments as $inst)
                            <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; background: #fff; font-size: 12px;" class="flex items-center justify-between">
                                <div>
                                    <div class="font-semibold text-slate-700">Parcela {{ $inst->installment_number }}</div>
                                    <div class="text-xs text-slate-500 mt-0.5">Venc: {{ $inst->due_date->format('d/m/Y') }}</div>
                                    <div class="font-bold text-slate-900 mt-1">R$ {{ number_format($inst->amount, 2, ',', '.') }}</div>
                                </div>
                                <div style="text-align: right;">
                                    @if($inst->status === 'paid' || $inst->paid_at)
                                        <span class="badge badge-success" style="font-size: 10px; padding: 3px 8px;">Pago</span>
                                        <div style="font-size: 10px; color: #64748b; margin-top: 4px;">Em {{ $inst->paid_at->format('d/m/Y') }}</div>
                                    @else
                                        <span class="badge badge-warning" style="font-size: 10px; padding: 3px 8px; margin-bottom: 4px; display: inline-block;">Pendente</span>
                                        @can('update', $sale)
                                            <div>
                                                <button type="button" onclick="openPaymentModal({{ $inst->id }}, {{ $inst->amount }}, {{ $sale->receivable->id }})" class="btn btn-primary btn-sm" style="font-size: 10px; padding: 3px 8px; border-radius: 6px; justify-content: center; width: 100%;">
                                                    Baixar
                                                </button>
                                            </div>
                                        @endcan
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p style="font-size: 12px; color: #64748b;">Nenhuma parcela gerada no contas a receber.</p>
                        @endforelse
                    </div>
                @endif
            </div>

            <!-- Observações da Proposta -->
            @if($sale->notes)
                <div class="card shadow-sm" style="border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px; background: #faf5ff;">
                    <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px; color: #6b21a8; font-weight: 700; font-size: 12px;">
                        <x-heroicon-o-chat-bubble-bottom-center-text class="w-4 h-4"/> Observações da Proposta
                    </div>
                    <p style="font-size: 12px; color: #581c87; line-height: 1.5; margin: 0;">{{ $sale->notes }}</p>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- MODAL INLINE DE BAIXA DE PARCELA -->
<div id="payment-modal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 16px;">
    <div style="background: #fff; border-radius: 12px; width: 100%; max-width: 400px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
        <h3 style="font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 16px;">Baixar Parcela</h3>
        
        <form id="payment-form" method="POST" action="">
            @csrf
            <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
            
            <div class="form-group mb-3">
                <label class="form-label" style="font-weight: 600;">Valor Recebido</label>
                <input type="number" step="0.01" name="paid_amount" id="modal-paid-amount" class="form-control" required>
            </div>

            <div class="form-group mb-3">
                <label class="form-label" style="font-weight: 600;">Data do Recebimento</label>
                <input type="date" name="paid_at" value="{{ date('Y-m-d') }}" class="form-control" required>
            </div>

            <div class="form-group mb-3">
                <label class="form-label" style="font-weight: 600;">Meio de Pagamento</label>
                <select name="payment_method" class="form-control" required>
                    @foreach($paymentMethods as $method)
                        <option value="{{ $method->value }}">{{ $method->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group mb-4">
                <label class="form-label" style="font-weight: 600;">Conta Financeira Destino</label>
                <select name="financial_account_id" class="form-control" required>
                    @foreach($accounts as $acc)
                        <option value="{{ $acc->id }}">{{ $acc->name }} (Saldo: R$ {{ number_format($acc->balance, 2, ',', '.') }})</option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2">
                <button type="button" onclick="closePaymentModal()" class="btn btn-secondary flex-1" style="justify-content: center; border-radius: 8px;">Cancelar</button>
                <button type="submit" class="btn btn-primary flex-1" style="justify-content: center; border-radius: 8px;">Confirmar Baixa</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Preview do upload de arquivos
    const uploadInput = document.getElementById('upload-input');
    const uploadLabel = document.getElementById('upload-label');
    const uploadPreview = document.getElementById('upload-preview');
    if (uploadInput) {
        uploadInput.addEventListener('change', function () {
            if (this.files.length > 0) {
                uploadPreview.style.display = 'block';
                uploadPreview.textContent = this.files.length + ' arquivo(s) selecionado(s)';
                uploadLabel.style.borderColor = '#6366f1';
            }
        });
    }

    // Gerenciador do Modal de Baixa de Parcela
    const modal = document.getElementById('payment-modal');
    const form = document.getElementById('payment-form');
    const amountInput = document.getElementById('modal-paid-amount');

    function openPaymentModal(installmentId, amount, receivableId) {
        amountInput.value = amount;
        form.action = `/receivables/${receivableId}/installments/${installmentId}/pay`;
        modal.style.display = 'flex';
    }

    function closePaymentModal() {
        modal.style.display = 'none';
    }
</script>
@endpush
