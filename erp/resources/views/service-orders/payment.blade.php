@extends('layouts.app')
@section('title', 'Conclusão e Faturamento da OS ' . $serviceOrder->code)

@section('content')
<div class="card mb-4" style="max-width: 900px; margin: 0 auto;" x-data="invoicingForm()">
    <div class="flex justify-between items-center mb-6 pb-4 border-b">
        <div>
            <h2 class="text-lg font-bold">Conclusão e Faturamento da OS</h2>
            <p class="text-sm text-muted">Cliente: <strong>{{ $serviceOrder->client->name ?? 'Cliente Avulso' }}</strong></p>
            <p class="text-xs text-muted">Novo Status: <span class="badge badge-indigo">{{ $newStatus->name }}</span></p>
        </div>
        <div class="text-right">
            <span class="text-sm text-muted block">Valor Total da OS</span>
            <div class="text-2xl font-black text-primary">R$ {{ number_format($serviceOrder->total_amount, 2, ',', '.') }}</div>
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger mb-4" style="background-color:#fee2e2;color:#991b1b;padding:12px;border-radius:6px;font-size:14px">
            {{ session('error') }}
        </div>
    @endif

    <form action="{{ route('service-orders.change-status', $serviceOrder) }}" method="POST" id="payment-form" @submit="handleSubmit($event)">
        @csrf
        <input type="hidden" name="status" value="{{ $newStatus->slug }}">

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 p-4 rounded-xl" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
            <!-- Condição de Pagamento -->
            <div>
                <label class="block text-xs font-bold uppercase text-muted mb-1">Condição de Pagto.</label>
                <select x-model="paymentConditionId" @change="applyPaymentCondition()" class="form-control text-sm w-full">
                    <option value="">Selecione...</option>
                    @foreach($paymentConditions as $pc)
                        <option value="{{ $pc->id }}" 
                                data-installments="{{ $pc->installments_count }}" 
                                data-interval="{{ $pc->interval_days }}"
                                data-method="{{ $pc->default_payment_method }}"
                                data-account="{{ $pc->default_financial_account_id }}">
                            {{ $pc->name }} ({{ $pc->installments_count }}x)
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Data Base -->
            <div>
                <label class="block text-xs font-bold uppercase text-muted mb-1">Data Base</label>
                <input type="date" x-model="baseDate" @change="recalculateDueDates()" class="form-control text-sm w-full" />
            </div>

            <!-- Método de Pagamento Padrão -->
            <div>
                <label class="block text-xs font-bold uppercase text-muted mb-1">Forma de Pagto. Padrão</label>
                <select x-model="defaultMethod" @change="applyDefaultsToInstallments('method')" class="form-control text-sm w-full">
                    @foreach($methods as $m)
                        <option value="{{ $m->value }}">{{ $m->label() }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Conta Destino Padrão -->
            <div>
                <label class="block text-xs font-bold uppercase text-muted mb-1">Conta Destino Padrão</label>
                <select x-model="defaultAccount" @change="applyDefaultsToInstallments('account')" class="form-control text-sm w-full">
                    @foreach($accounts as $acc)
                        <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="flex justify-between items-center mb-4">
            <h3 class="text-md font-semibold text-gray-800">Detalhamento das Parcelas</h3>
            <button type="button" @click="addInstallment()" class="btn btn-secondary btn-sm">
                + Adicionar Parcela Manual
            </button>
        </div>

        <div class="overflow-x-auto mb-6">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-200" style="background-color: #f8fafc;">
                        <th class="py-3 px-3 text-xs font-bold uppercase text-muted" style="width: 80px;">Parcela</th>
                        <th class="py-3 px-3 text-xs font-bold uppercase text-muted">Vencimento</th>
                        <th class="py-3 px-3 text-xs font-bold uppercase text-muted" style="width: 160px;">Valor (R$)</th>
                        <th class="py-3 px-3 text-xs font-bold uppercase text-muted">Forma de Pagamento</th>
                        <th class="py-3 px-3 text-xs font-bold uppercase text-muted">Conta de Destino</th>
                        <th class="py-3 px-3 text-xs font-bold uppercase text-muted text-center" style="width: 80px;">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(inst, index) in installments" :key="index">
                        <tr class="border-b border-gray-100 hover:bg-slate-50">
                            <!-- N° Parcela -->
                            <td class="py-2 px-3">
                                <span class="text-sm font-semibold text-gray-700" x-text="index + 1"></span>
                            </td>

                            <!-- Vencimento -->
                            <td class="py-2 px-3">
                                <input type="date" x-model="inst.due_date" :name="'installments['+index+'][due_date]'" class="form-control text-sm py-1" required />
                            </td>

                            <!-- Valor -->
                            <td class="py-2 px-3">
                                <input type="number" step="0.01" x-model.number="inst.amount" :name="'installments['+index+'][amount]'" @input="calculateRemaining()" class="form-control text-sm py-1" required />
                            </td>

                            <!-- Forma de Pagamento -->
                            <td class="py-2 px-3">
                                <select x-model="inst.payment_method" :name="'installments['+index+'][payment_method]'" class="form-control text-sm py-1" required>
                                    @foreach($methods as $m)
                                        <option value="{{ $m->value }}">{{ $m->label() }}</option>
                                    @endforeach
                                </select>
                            </td>

                            <!-- Conta Destino -->
                            <td class="py-2 px-3">
                                <select x-model="inst.financial_account_id" :name="'installments['+index+'][financial_account_id]'" class="form-control text-sm py-1" required>
                                    @foreach($accounts as $acc)
                                        <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                                    @endforeach
                                </select>
                            </td>

                            <!-- Ação -->
                            <td class="py-2 px-3 text-center">
                                <button type="button" @click="removeInstallment(index)" class="text-red-600 hover:text-red-900 font-semibold text-xs" style="background: none; border: none; cursor: pointer;">
                                    Remover
                                </button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="installments.length === 0">
                        <td colspan="6" class="py-6 text-center text-muted text-sm">
                            Nenhuma parcela cadastrada. Escolha uma condição de pagamento ou adicione manualmente.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Painel de Conferência de Totais -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4 rounded-xl mb-6" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
            <div>
                <span class="text-xs font-bold uppercase text-muted block">Total Proposto:</span>
                <span class="text-lg font-bold text-gray-800" x-text="formatCurrency(totalAmount)"></span>
            </div>
            <div>
                <span class="text-xs font-bold uppercase text-muted block">Total das Parcelas:</span>
                <span class="text-lg font-bold" :class="isSumCorrect ? 'text-green-600' : 'text-amber-600'" x-text="formatCurrency(totalInformed)"></span>
            </div>
            <div>
                <span class="text-xs font-bold uppercase text-muted block">Diferença / Restante:</span>
                <span class="text-lg font-bold" :class="isSumCorrect ? 'text-green-600' : 'text-red-600'" x-text="formatCurrency(remainingAmount)"></span>
            </div>
        </div>

        <!-- Observações Opcionais -->
        <div class="form-group mb-6">
            <label for="note" class="form-label text-sm font-semibold text-gray-800 mb-1">Observações Internas / Histórico (Opcional)</label>
            <textarea name="note" id="note" class="form-control" rows="3" placeholder="Ex: Cliente solicitou alteração no parcelamento..."></textarea>
        </div>

        <div class="flex gap-3 mt-6">
            <button type="submit" :disabled="!isSumCorrect" class="btn btn-primary btn-lg flex-1 md:flex-initial" style="padding: 12px 32px;">
                Confirmar e Concluir OS
            </button>
            <a href="{{ route('service-orders.show', $serviceOrder) }}" class="btn btn-secondary btn-lg" style="padding: 12px 32px;">
                Voltar
            </a>
        </div>
    </form>
</div>

<script>
function invoicingForm() {
    return {
        totalAmount: parseFloat("{{ $serviceOrder->total_amount }}"),
        installments: [],
        paymentConditionId: '',
        baseDate: new Date().toISOString().substring(0, 10),
        defaultMethod: 'pix',
        defaultAccount: '{{ $accounts->first()->id ?? "" }}',
        totalInformed: 0,
        remainingAmount: 0,
        isSumCorrect: false,

        init() {
            // Inicializa com 1 parcela correspondendo ao valor total
            this.addInstallment(this.totalAmount);
            this.calculateRemaining();
        },

        addInstallment(amount = 0) {
            const index = this.installments.length;
            
            // Calcula vencimento (por padrão 30 dias após a última parcela, ou após a data base se for a primeira)
            let dueDate = this.baseDate;
            if (index > 0) {
                const lastDate = new Date(this.installments[index - 1].due_date);
                lastDate.setDate(lastDate.getDate() + 30);
                dueDate = lastDate.toISOString().substring(0, 10);
            }

            this.installments.push({
                due_date: dueDate,
                amount: parseFloat(amount) || 0,
                payment_method: this.defaultMethod,
                financial_account_id: this.defaultAccount
            });
            this.calculateRemaining();
        },

        removeInstallment(index) {
            this.installments.splice(index, 1);
            this.calculateRemaining();
        },

        applyPaymentCondition() {
            if (!this.paymentConditionId) return;

            const select = document.querySelector('select[x-model="paymentConditionId"]');
            const option = select.selectedOptions[0];
            if (!option) return;

            const installmentsCount = parseInt(option.dataset.installments) || 1;
            const intervalDays = parseInt(option.dataset.interval) || 30;
            const defaultMethod = option.dataset.method || this.defaultMethod;
            const defaultAccount = option.dataset.account || this.defaultAccount;

            this.defaultMethod = defaultMethod;
            if (defaultAccount) {
                this.defaultAccount = defaultAccount;
            }

            // Divide o valor em parcelas iguais
            const baseAmount = Math.floor((this.totalAmount / installmentsCount) * 100) / 100;
            const remainder = Math.round((this.totalAmount - (baseAmount * installmentsCount)) * 100) / 100;

            this.installments = [];
            const baseDateObj = new Date(this.baseDate);

            for (let i = 0; i < installmentsCount; i++) {
                const nextDate = new Date(baseDateObj);
                nextDate.setDate(baseDateObj.getDate() + (i * intervalDays));
                
                const installmentAmount = (i === installmentsCount - 1) ? (baseAmount + remainder) : baseAmount;

                this.installments.push({
                    due_date: nextDate.toISOString().substring(0, 10),
                    amount: parseFloat(installmentAmount.toFixed(2)),
                    payment_method: this.defaultMethod,
                    financial_account_id: this.defaultAccount
                });
            }

            this.calculateRemaining();
        },

        recalculateDueDates() {
            if (!this.paymentConditionId) return;

            const select = document.querySelector('select[x-model="paymentConditionId"]');
            const option = select.selectedOptions[0];
            if (!option) return;

            const intervalDays = parseInt(option.dataset.interval) || 30;
            const baseDateObj = new Date(this.baseDate);

            this.installments.forEach((inst, i) => {
                const nextDate = new Date(baseDateObj);
                nextDate.setDate(baseDateObj.getDate() + (i * intervalDays));
                inst.due_date = nextDate.toISOString().substring(0, 10);
            });
        },

        applyDefaultsToInstallments(type) {
            this.installments.forEach(inst => {
                if (type === 'method') {
                    inst.payment_method = this.defaultMethod;
                } else if (type === 'account') {
                    inst.financial_account_id = this.defaultAccount;
                }
            });
        },

        calculateRemaining() {
            const sum = this.installments.reduce((t, inst) => t + (parseFloat(inst.amount) || 0), 0);
            this.totalInformed = parseFloat(sum.toFixed(2));
            this.remainingAmount = parseFloat((this.totalAmount - this.totalInformed).toFixed(2));
            this.isSumCorrect = Math.abs(this.remainingAmount) < 0.01;
        },

        formatCurrency(value) {
            return 'R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        handleSubmit(e) {
            this.calculateRemaining();
            if (!this.isSumCorrect) {
                e.preventDefault();
                alert('A soma das parcelas deve ser exatamente igual ao valor total.');
            }
        }
    }
}
</script>
@endsection
