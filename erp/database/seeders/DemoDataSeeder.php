<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Supplier;
use App\Models\Company;
use App\Models\User;
use App\Models\Product;
use App\Models\Service;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderPayment;
use App\Models\ServiceOrderStatus;
use App\Models\Receivable;
use App\Models\ReceivableInstallment;
use App\Models\Payable;
use App\Models\PayableInstallment;
use App\Models\FinancialAccount;
use App\Models\FinancialAccountType;
use App\Enums\ProductType;
use App\Enums\QuoteStatus;
use App\Enums\SaleStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Certificar a existência da Empresa principal
        $company = Company::first() ?? Company::create(['id' => 1, 'name' => 'Neksa ERP']);

        // Criar Tipos de Contas Financeiras
        $typeChecking = FinancialAccountType::updateOrCreate(['slug' => 'checking'], ['name' => 'Conta Corrente']);
        $typeCash = FinancialAccountType::updateOrCreate(['slug' => 'cash'], ['name' => 'Caixa Físico']);

        // Contas Financeiras
        $accItau = FinancialAccount::updateOrCreate(
            ['name' => 'Itaú Unibanco Corrente'],
            [
                'type_id' => $typeChecking->id,
                'bank_name' => 'Itaú',
                'agency' => '0123',
                'account_number' => '45678-9',
                'balance' => 95200.50,
                'is_active' => true,
            ]
        );

        $accBb = FinancialAccount::updateOrCreate(
            ['name' => 'Banco do Brasil Corrente'],
            [
                'type_id' => $typeChecking->id,
                'bank_name' => 'Banco do Brasil',
                'agency' => '3210',
                'account_number' => '98765-4',
                'balance' => 32500.00,
                'is_active' => true,
            ]
        );

        // Usuário Administrador
        $adminUser = User::first() ?? User::create([
            'name' => 'Admin',
            'email' => 'admin@neksa.com.br',
            'password' => bcrypt('password'),
        ]);

        // Clientes e Fornecedores
        $client1 = Client::first() ?? Client::create(['name' => 'Tech Solutions Ltda', 'document' => '12345678000199', 'email' => 'financeiro@techsolutions.com', 'is_active' => true]);
        $client2 = Client::skip(1)->first() ?? Client::create(['name' => 'Comércio de Bebidas Alvorada', 'document' => '98765432000188', 'email' => 'contato@bebidasalvorada.com.br', 'is_active' => true]);
        $supplier = Supplier::first() ?? Supplier::create(['name' => 'Distribuidora Global de Peças', 'document' => '11223344000155', 'email' => 'vendas@globalpecas.com', 'phone' => '11999998888']);

        // Produtos e Serviços
        $product = Product::first() ?? Product::create([
            'name' => 'Roteador MikroTik RB750Gr3',
            'sku' => 'PROD-MT-RB750',
            'cost_price' => 250.00,
            'sale_price' => 450.00,
            'stock' => 50,
            'is_active' => true,
            'type' => ProductType::Product,
        ]);

        $service = Service::first() ?? Service::create([
            'name' => 'Configuração Avançada de Firewall',
            'sku' => 'SERV-CONF-FW',
            'price' => 350.00,
            'is_active' => true,
        ]);

        // Status de OS
        $statusCompleted = ServiceOrderStatus::where('slug', 'completed')->first() ?? ServiceOrderStatus::create(['name' => 'Concluída', 'slug' => 'completed', 'color' => 'green', 'is_completed_state' => true]);

        // Gerar dados históricos dos últimos 13 meses
        $now = Carbon::now();
        $startDate = Carbon::now()->subMonths(13)->startOfMonth();

        $this->command->info('Gerando dados financeiros e operacionais dos últimos 13 meses...');

        for ($date = $startDate->copy(); $date->lessThanOrEqualTo($now); $date->addMonth()) {
            $monthStr = $date->format('Y-m');
            
            // Criar Vendas no mês
            $v1 = Sale::create([
                'code' => 'VEN-DEMO-' . $date->format('Ym') . '-' . rand(1000, 9999) . '-' . rand(10, 99),
                'client_id' => $client1->id,
                'status' => SaleStatus::Completed,
                'items_amount' => 900.00,
                'total_amount' => 900.00,
                'created_at' => $date->copy()->day(10),
            ]);
            SaleItem::create([
                'sale_id' => $v1->id,
                'product_id' => $product->id,
                'description' => $product->name,
                'quantity' => 2,
                'unit_price' => 450.00,
                'total_price' => 900.00,
            ]);

            // Recebível da Venda
            $r1 = Receivable::create([
                'company_id' => $company->id,
                'client_id' => $client1->id,
                'code' => 'REC-SALE-' . $v1->id . '-' . rand(10, 99),
                'source_type' => Sale::class,
                'source_id' => $v1->id,
                'competence_date' => $date->copy()->day(10),
                'description' => 'Faturamento Venda ' . $v1->code,
                'total_amount' => 900.00,
                'net_amount' => 900.00,
                'status' => 'paid',
                'created_at' => $date->copy()->day(10),
            ]);
            ReceivableInstallment::create([
                'receivable_id' => $r1->id,
                'installment_number' => 1,
                'due_date' => $date->copy()->day(10),
                'amount' => 900.00,
                'net_amount' => 900.00,
                'paid_amount' => 900.00,
                'paid_at' => $date->copy()->day(10),
                'payment_method' => PaymentMethod::Pix->value,
                'status' => 'paid',
                'financial_account_id' => $accItau->id,
            ]);

            // Criar Ordens de Serviço no mês
            $os = ServiceOrder::create([
                'code' => sprintf('OS-DEMO-%s-%05d-%d', $date->format('Y'), rand(10000, 99999), rand(10, 99)),
                'client_id' => $client2->id,
                'created_by' => $adminUser->id,
                'status_id' => $statusCompleted->id,
                'description' => 'Manutenção corretiva e preventiva de firewall',
                'service_amount' => 700.00,
                'parts_amount' => 0.00,
                'total_amount' => 700.00,
                'completed_at' => $date->copy()->day(15),
                'created_at' => $date->copy()->day(12),
            ]);

            // Recebível da OS
            $r2 = Receivable::create([
                'company_id' => $company->id,
                'client_id' => $client2->id,
                'code' => 'REC-OS-' . $os->id . '-' . rand(10, 99),
                'source_type' => ServiceOrder::class,
                'source_id' => $os->id,
                'competence_date' => $date->copy()->day(15),
                'description' => 'Faturamento OS ' . $os->code,
                'total_amount' => 700.00,
                'net_amount' => 700.00,
                'status' => 'paid',
                'created_at' => $date->copy()->day(12),
            ]);
            ReceivableInstallment::create([
                'receivable_id' => $r2->id,
                'installment_number' => 1,
                'due_date' => $date->copy()->day(15),
                'amount' => 700.00,
                'net_amount' => 700.00,
                'paid_amount' => 700.00,
                'paid_at' => $date->copy()->day(16), // Pago no dia seguinte
                'payment_method' => PaymentMethod::Boleto->value,
                'status' => 'paid',
                'financial_account_id' => $accBb->id,
            ]);

            // Receita avulsa para encorpar RBT12 (ex: R$ 15.000,00 por mês)
            $rAvulsa = Receivable::create([
                'company_id' => $company->id,
                'client_id' => $client1->id,
                'code' => 'REC-AVULSA-' . $monthStr . '-01-' . rand(100, 999),
                'competence_date' => $date->copy()->day(5),
                'description' => 'Contrato de Suporte Mensal ' . $monthStr,
                'total_amount' => 15000.00,
                'net_amount' => 15000.00,
                'status' => 'paid',
                'created_at' => $date->copy()->day(1),
            ]);
            ReceivableInstallment::create([
                'receivable_id' => $rAvulsa->id,
                'installment_number' => 1,
                'due_date' => $date->copy()->day(5),
                'amount' => 15000.00,
                'net_amount' => 15000.00,
                'paid_amount' => 15000.00,
                'paid_at' => $date->copy()->day(5),
                'payment_method' => PaymentMethod::Pix->value,
                'status' => 'paid',
                'financial_account_id' => $accItau->id,
            ]);

            // Criar Despesa/Pagável no mês (ex: R$ 6.500,00 por mês)
            $p1 = Payable::create([
                'company_id' => $company->id,
                'supplier_id' => $supplier->id,
                'code' => 'PAG-DESP-' . $monthStr . '-01-' . rand(100, 999),
                'competence_date' => $date->copy()->day(1),
                'description' => 'Aluguel do Galpão Operacional ' . $monthStr,
                'total_amount' => 6500.00,
                'net_amount' => 6500.00,
                'status' => 'paid',
                'created_at' => $date->copy()->day(1),
            ]);
            PayableInstallment::create([
                'payable_id' => $p1->id,
                'installment_number' => 1,
                'due_date' => $date->copy()->day(5),
                'amount' => 6500.00,
                'net_amount' => 6500.00,
                'paid_amount' => 6500.00,
                'paid_at' => $date->copy()->day(5),
                'payment_method' => PaymentMethod::Pix->value,
                'status' => 'paid',
                'financial_account_id' => $accItau->id,
            ]);
        }

        // Criar dados específicos do Mês Atual para preencher os KPIs de Inadimplência e Contas do Dashboard
        $this->command->info('Gerando indicadores avançados para o painel atual...');

        // 1. Contas Vencidas (Inadimplência)
        $rVencida1 = Receivable::create([
            'company_id' => $company->id,
            'client_id' => $client1->id,
            'code' => 'REC-VENCIDO-01-' . rand(100, 999),
            'competence_date' => Carbon::now()->subDays(15),
            'description' => 'Serviço de Infraestrutura Vencido',
            'total_amount' => 3800.00,
            'net_amount' => 3800.00,
            'status' => 'pending',
            'created_at' => Carbon::now()->subDays(15),
        ]);
        ReceivableInstallment::create([
            'receivable_id' => $rVencida1->id,
            'installment_number' => 1,
            'due_date' => Carbon::now()->subDays(10), // Vencida há 10 dias
            'amount' => 3800.00,
            'net_amount' => 3800.00,
            'paid_amount' => 0.00,
            'status' => 'pending',
            'financial_account_id' => $accBb->id,
        ]);

        $rVencida2 = Receivable::create([
            'company_id' => $company->id,
            'client_id' => $client2->id,
            'code' => 'REC-VENCIDO-02-' . rand(100, 999),
            'competence_date' => Carbon::now()->subDays(25),
            'description' => 'Venda de Equipamentos Pendente',
            'total_amount' => 4200.00,
            'net_amount' => 4200.00,
            'status' => 'partially_paid',
            'created_at' => Carbon::now()->subDays(25),
        ]);
        ReceivableInstallment::create([
            'receivable_id' => $rVencida2->id,
            'installment_number' => 1,
            'due_date' => Carbon::now()->subDays(5), // Vencida há 5 dias
            'amount' => 4200.00,
            'net_amount' => 4200.00,
            'paid_amount' => 1200.00,
            'paid_at' => Carbon::now()->subDays(5),
            'status' => 'pending',
            'financial_account_id' => $accItau->id,
        ]);

        // 2. Contas vencendo hoje
        $rHoje = Receivable::create([
            'company_id' => $company->id,
            'client_id' => $client1->id,
            'code' => 'REC-HOJE-01-' . rand(100, 999),
            'competence_date' => Carbon::now()->subDays(5),
            'description' => 'Mensalidade de Contrato Técnico',
            'total_amount' => 2500.00,
            'net_amount' => 2500.00,
            'status' => 'pending',
            'created_at' => Carbon::now()->subDays(5),
        ]);
        ReceivableInstallment::create([
            'receivable_id' => $rHoje->id,
            'installment_number' => 1,
            'due_date' => Carbon::today(), // Vence hoje
            'amount' => 2500.00,
            'net_amount' => 2500.00,
            'paid_amount' => 0.00,
            'status' => 'pending',
            'financial_account_id' => $accBb->id,
        ]);

        // 3. Contas vencendo nos próximos 7 dias
        $rProx = Receivable::create([
            'company_id' => $company->id,
            'client_id' => $client2->id,
            'code' => 'REC-PROX-01-' . rand(100, 999),
            'competence_date' => Carbon::now()->subDays(3),
            'description' => 'Serviços de Manutenção Programada',
            'total_amount' => 1800.00,
            'net_amount' => 1800.00,
            'status' => 'pending',
            'created_at' => Carbon::now()->subDays(3),
        ]);
        ReceivableInstallment::create([
            'receivable_id' => $rProx->id,
            'installment_number' => 1,
            'due_date' => Carbon::now()->addDays(4), // Vence em 4 dias
            'amount' => 1800.00,
            'net_amount' => 1800.00,
            'paid_amount' => 0.00,
            'status' => 'pending',
            'financial_account_id' => $accItau->id,
        ]);

        $this->command->info('Massa de dados Demo semeada com sucesso!');
    }
}
