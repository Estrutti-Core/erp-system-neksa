<?php
// erp/database/seeders/MockDataSeeder.php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\ClientEquipment;
use App\Models\Product;
use App\Models\Service;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\FinancialAccount;
use App\Models\FinancialAccountType;
use App\Models\Receivable;
use App\Models\ReceivableInstallment;
use App\Models\SalePayment;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderPayment;
use App\Models\Company;
use App\Models\User;
use App\Enums\ProductType;
use App\Enums\QuoteStatus;
use App\Enums\SaleStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class MockDataSeeder extends Seeder
{
    public function run(): void
    {
        // Certificar a existência da Empresa principal
        $company = Company::first();
        if (!$company) {
            $company = Company::create([
                'id' => 1,
                'name' => 'Neksa ERP',
            ]);
        }

        // Criar Tipos de Contas Financeiras
        $typeChecking = FinancialAccountType::updateOrCreate(
            ['slug' => 'checking'],
            ['name' => 'Conta Corrente']
        );

        $typeCash = FinancialAccountType::updateOrCreate(
            ['slug' => 'cash'],
            ['name' => 'Caixa Físico']
        );

        // Criar Contas Financeiras com saldos iniciais para movimentações
        $accItau = FinancialAccount::updateOrCreate(
            ['name' => 'Itaú Unibanco Corrente'],
            [
                'type_id' => $typeChecking->id,
                'bank_name' => 'Itaú',
                'agency' => '0123',
                'account_number' => '45678-9',
                'balance' => 45200.50,
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
                'balance' => 12500.00,
                'is_active' => true,
            ]
        );

        $accCaixa = FinancialAccount::updateOrCreate(
            ['name' => 'Caixa Físico Interno'],
            [
                'type_id' => $typeCash->id,
                'balance' => 1200.00,
                'is_active' => true,
            ]
        );

        // 1. Seed Products (Produtos Físicos/Peças com dados logísticos)
        $productsData = [
            [
                'name' => 'Roteador MikroTik RB750Gr3',
                'description' => 'Roteador Gigabit de 5 portas para pequenas e médias empresas',
                'sku' => 'PROD-MT-RB750',
                'barcode' => '7891234560011',
                'ncm' => '85176277',
                'cfop' => '5102',
                'cst' => '00',
                'commercial_unit' => 'UN',
                'taxable_unit' => 'UN',
                'cost_price' => 250.00,
                'sale_price' => 450.00,
                'stock' => 15.000,
                'is_active' => true,
                'type' => ProductType::Product,
                'is_stock_controlled' => true,
                'weight_gross' => 0.4500,
                'weight_net' => 0.3800,
                'height' => 10.00,
                'width' => 12.00,
                'length' => 5.00,
                'volume' => 0.0006,
                'logistic_unit' => 'UN',
            ],
            [
                'name' => 'Switch Gigabit TP-Link 24 Portas TL-SG1024D',
                'description' => 'Switch de mesa/rack de 24 portas Gigabit',
                'sku' => 'PROD-TPL-SG24',
                'barcode' => '7891234560028',
                'ncm' => '85176272',
                'cfop' => '5102',
                'cst' => '00',
                'commercial_unit' => 'UN',
                'taxable_unit' => 'UN',
                'cost_price' => 400.00,
                'sale_price' => 750.00,
                'stock' => 8.000,
                'is_active' => true,
                'type' => ProductType::Product,
                'is_stock_controlled' => true,
                'weight_gross' => 1.8500,
                'weight_net' => 1.6000,
                'height' => 15.00,
                'width' => 44.00,
                'length' => 18.00,
                'volume' => 0.0118,
                'logistic_unit' => 'UN',
            ],
            [
                'name' => 'Cabo de Rede Nexans Cat6 U/UTP 305m',
                'description' => 'Caixa de cabo de rede Cat6 azul, alta qualidade',
                'sku' => 'PROD-NEX-CAT6',
                'barcode' => '7891234560035',
                'ncm' => '85444900',
                'cfop' => '5102',
                'cst' => '00',
                'commercial_unit' => 'CX',
                'taxable_unit' => 'CX',
                'cost_price' => 380.00,
                'sale_price' => 690.00,
                'stock' => 25.000,
                'is_active' => true,
                'type' => ProductType::Product,
                'is_stock_controlled' => true,
                'weight_gross' => 11.2000,
                'weight_net' => 10.5000,
                'height' => 35.00,
                'width' => 35.00,
                'length' => 35.00,
                'volume' => 0.0428,
                'logistic_unit' => 'CX',
            ],
            [
                'name' => 'Conector RJ45 Macho Cat6 Blindado (Pacote 100 un)',
                'description' => 'Conectores modulares RJ45 banhados a ouro para cabo Cat6',
                'sku' => 'PROD-RJ45-CAT6',
                'barcode' => '7891234560042',
                'ncm' => '85366990',
                'cfop' => '5102',
                'cst' => '00',
                'commercial_unit' => 'PCT',
                'taxable_unit' => 'PCT',
                'cost_price' => 45.00,
                'sale_price' => 120.00,
                'stock' => 50.000,
                'is_active' => true,
                'type' => ProductType::Product,
                'is_stock_controlled' => true,
                'weight_gross' => 0.2000,
                'weight_net' => 0.1800,
                'height' => 5.00,
                'width' => 8.00,
                'length' => 12.00,
                'volume' => 0.0004,
                'logistic_unit' => 'PCT',
            ],
            [
                'name' => 'Access Point Ubiquiti UniFi U6+ Dual-Band Wi-Fi 6',
                'description' => 'Ponto de acesso Wi-Fi 6 corporativo de alta performance',
                'sku' => 'PROD-UBNT-U6P',
                'barcode' => '7891234560059',
                'ncm' => '85176277',
                'cfop' => '5102',
                'cst' => '00',
                'commercial_unit' => 'UN',
                'taxable_unit' => 'UN',
                'cost_price' => 650.00,
                'sale_price' => 1190.00,
                'stock' => 12.000,
                'is_active' => true,
                'type' => ProductType::Product,
                'is_stock_controlled' => true,
                'weight_gross' => 0.6000,
                'weight_net' => 0.5100,
                'height' => 18.00,
                'width' => 18.00,
                'length' => 6.00,
                'volume' => 0.0019,
                'logistic_unit' => 'UN',
            ]
        ];

        $products = [];
        foreach ($productsData as $data) {
            $products[] = Product::updateOrCreate(['sku' => $data['sku']], $data);
        }

        // 2. Seed Services (Serviços com Dados Fiscais da Fase A)
        $servicesData = [
            [
                'name' => 'Configuração Avançada de Roteador / Firewall',
                'description' => 'Configuração de VLANs, VPNs, regras de firewall e failover de links',
                'sku' => 'SERV-CONF-FW',
                'price' => 350.00,
                'cfop' => '5933',
                'cst' => '01',
                'iss_rate' => 5.00,
                'iss_withheld' => false,
                'pis_retention_rate' => 0.65,
                'cofins_retention_rate' => 3.00,
                'csll_retention_rate' => 1.00,
                'inss_retention_rate' => 0.00,
                'municipal_service_code' => '01.01',
                'is_active' => true,
            ],
            [
                'name' => 'Instalação e Conectorização de Cabos de Rede (por ponto)',
                'description' => 'Lançamento de cabos, fixação de canaletas/infraestrutura e conectorização de tomadas RJ45',
                'sku' => 'SERV-INST-CABO',
                'price' => 90.00,
                'cfop' => '5933',
                'cst' => '01',
                'iss_rate' => 5.00,
                'iss_withheld' => true,
                'pis_retention_rate' => 0.65,
                'cofins_retention_rate' => 3.00,
                'csll_retention_rate' => 1.00,
                'inss_retention_rate' => 11.00,
                'municipal_service_code' => '14.01',
                'is_active' => true,
            ],
            [
                'name' => 'Consultoria Técnica em Arquitetura de Redes (por hora)',
                'description' => 'Análise técnica, mapeamento de vulnerabilidades e especificação de projetos de rede',
                'sku' => 'SERV-CONS-REDE',
                'price' => 200.00,
                'cfop' => '5933',
                'cst' => '01',
                'iss_rate' => 2.00,
                'iss_withheld' => false,
                'pis_retention_rate' => 0.65,
                'cofins_retention_rate' => 3.00,
                'csll_retention_rate' => 1.00,
                'inss_retention_rate' => 0.00,
                'municipal_service_code' => '01.02',
                'is_active' => true,
            ]
        ];

        $services = [];
        foreach ($servicesData as $data) {
            $services[] = Service::updateOrCreate(['sku' => $data['sku']], $data);
        }

        // 3. Clientes
        $clients = Client::with('addresses')->get();
        if ($clients->isEmpty()) {
            return; // Safety check
        }

        // 4. Seed Quotes (Orçamentos)
        $quoteClients = $clients->take(4);
        
        $c1 = $quoteClients->get(0);
        $addr1 = $c1->addresses->first();
        $q1 = Quote::create([
            'client_id' => $c1->id,
            'client_address_id' => $addr1?->id,
            'status' => QuoteStatus::Draft,
            'valid_until' => Carbon::now()->addDays(15),
            'notes' => 'Orçamento inicial para implantação de Wi-Fi no escritório principal.',
            'internal_notes' => 'Cliente solicitou desconto especial, mas mantivemos a tabela padrão por enquanto.',
            'discount_amount' => 50.00,
        ]);

        QuoteItem::create([
            'quote_id' => $q1->id,
            'product_id' => $products[4]->id,
            'description' => $products[4]->name,
            'quantity' => 2.000,
            'unit' => $products[4]->commercial_unit,
            'unit_price' => $products[4]->sale_price,
            'type' => ProductType::Product,
        ]);

        $q1->recalculateTotals();

        // 5. Seed Sales
        // Sale 1: Pending (Sem pagamento)
        $s1 = Sale::create([
            'client_id' => $c1->id,
            'client_address_id' => $addr1?->id,
            'status' => SaleStatus::Pending,
            'discount_amount' => 0.00,
            'items_amount' => 1200.00,
            'total_amount' => 1200.00,
            'notes' => 'Venda de switches pendente de faturamento.',
            'carrier' => 'Braspress Transportes',
            'freight_price' => 120.00,
            'volume' => 2.00,
            'weight_gross' => 3.700,
            'weight_net' => 3.200,
            'freight_type' => 1,
            'delivery_deadline' => '5 dias úteis',
            'warranty' => '1 ano contra defeito de fabricação',
            'validity' => '10 dias',
        ]);

        SaleItem::create([
            'sale_id' => $s1->id,
            'product_id' => $products[1]->id,
            'description' => $products[1]->name,
            'quantity' => 2.000,
            'unit' => $products[1]->commercial_unit,
            'unit_price' => 600.00,
            'total_price' => 1200.00,
        ]);

        $s1->recalculateTotals();

        // Sale 2: Completed (Faturada + Recebível Gerado + Parcelas com baixas)
        $s2 = Sale::create([
            'client_id' => $c1->id,
            'client_address_id' => $addr1?->id,
            'status' => SaleStatus::Completed,
            'discount_amount' => 50.00,
            'items_amount' => 1190.00,
            'total_amount' => 1140.00,
            'notes' => 'Venda faturada de AP corporativo.',
            'carrier' => 'Correios PAC',
            'freight_price' => 30.00,
            'volume' => 1.00,
            'weight_gross' => 0.600,
            'weight_net' => 0.510,
            'freight_type' => 0,
            'delivery_deadline' => '3 dias úteis',
            'warranty' => '2 anos direto com fabricante',
            'validity' => 'Expirada',
        ]);

        SaleItem::create([
            'sale_id' => $s2->id,
            'product_id' => $products[4]->id,
            'description' => $products[4]->name,
            'quantity' => 1.000,
            'unit' => $products[4]->commercial_unit,
            'unit_price' => 1190.00,
            'total_price' => 1190.00,
        ]);

        $s2->recalculateTotals();

        // Registrar pagamentos no SalePayment
        SalePayment::create([
            'sale_id' => $s2->id,
            'payment_method' => PaymentMethod::Boleto->value,
            'amount' => 1140.00,
            'installments_count' => 2,
            'first_due_date' => Carbon::now()->addDays(5),
            'financial_account_id' => $accBb->id,
            'notes' => 'Dividido em 2 boletos para o financeiro do cliente.',
        ]);

        // Criar o recebível correspondente no contas a receber
        $receivable = Receivable::create([
            'company_id' => $company->id,
            'client_id' => $c1->id,
            'code' => 'REC-SALE-' . $s2->id,
            'source_type' => Sale::class,
            'source_id' => $s2->id,
            'source_snapshot' => json_encode([
                'client_name' => $c1->name,
                'total_amount' => $s2->total_amount,
            ]),
            'competence_date' => Carbon::now(),
            'description' => 'Faturamento Venda ' . $s2->code,
            'total_amount' => 1140.00,
            'net_amount' => 1140.00,
            'status' => 'pending',
            'notes' => 'Gerado automaticamente pelo seeder.',
        ]);

        // Parcela 1: Paga
        ReceivableInstallment::create([
            'receivable_id' => $receivable->id,
            'installment_number' => 1,
            'due_date' => Carbon::now()->subDays(2),
            'amount' => 570.00,
            'net_amount' => 570.00,
            'paid_amount' => 570.00,
            'paid_at' => Carbon::now()->subDays(2),
            'payment_method' => PaymentMethod::Boleto->value,
            'status' => 'paid',
            'financial_account_id' => $accBb->id,
            'user_id' => 1,
        ]);

        // Parcela 2: Pendente (Para baixa inline!)
        ReceivableInstallment::create([
            'receivable_id' => $receivable->id,
            'installment_number' => 2,
            'due_date' => Carbon::now()->addDays(28),
            'amount' => 570.00,
            'net_amount' => 570.00,
            'paid_amount' => 0.00,
            'status' => 'pending',
            'financial_account_id' => $accBb->id,
        ]);

        // 6. Seed Ordens de Serviço Concluídas com Financeiro associado
        $completedSo = ServiceOrder::whereHas('status', function($q) {
            $q->where('slug', 'completed');
        })->get();

        foreach ($completedSo as $so) {
            // Se já não tiver pagamentos, cria as formas e recebíveis correspondentes!
            if ($so->payments->isEmpty()) {
                ServiceOrderPayment::create([
                    'service_order_id' => $so->id,
                    'payment_method' => PaymentMethod::Pix->value,
                    'amount' => $so->total_amount,
                    'installments_count' => 1,
                    'first_due_date' => $so->completed_at ?? Carbon::now(),
                    'financial_account_id' => $accItau->id,
                    'notes' => 'Faturado via Pix na conclusão da OS.',
                ]);

                // Criar recebível correspondente
                $recSo = Receivable::create([
                    'company_id' => $company->id,
                    'client_id' => $so->client_id,
                    'code' => 'REC-OS-' . $so->id,
                    'source_type' => ServiceOrder::class,
                    'source_id' => $so->id,
                    'source_snapshot' => json_encode([
                        'client_name' => $so->client->name,
                        'total_amount' => $so->total_amount,
                    ]),
                    'competence_date' => $so->completed_at ?? Carbon::now(),
                    'description' => 'Faturamento OS ' . $so->code,
                    'total_amount' => $so->total_amount,
                    'net_amount' => $so->total_amount,
                    'status' => 'pending',
                    'notes' => 'Semeado automaticamente para a OS.',
                ]);

                // Parcela pendente para demonstração de baixa inline
                ReceivableInstallment::create([
                    'receivable_id' => $recSo->id,
                    'installment_number' => 1,
                    'due_date' => Carbon::now()->addDays(5),
                    'amount' => $so->total_amount,
                    'net_amount' => $so->total_amount,
                    'paid_amount' => 0.00,
                    'status' => 'pending',
                    'financial_account_id' => $accItau->id,
                ]);
            }
        }

        // 7. Seed Purchase Orders & Inventory Conferences (Inputs/Entradas)
        $supplier = \App\Models\Supplier::first() ?? \App\Models\Supplier::create([
            'name' => 'Distribuidora Global de Peças',
            'document' => '11223344000155',
            'email' => 'vendas@globalpecas.com',
            'phone' => '11999998888'
        ]);

        $product1 = Product::where('sku', 'PROD-MT-RB750')->first();
        $product2 = Product::where('sku', 'PROD-TPL-SG24')->first();
        $product3 = Product::where('sku', 'PROD-NEX-CAT6')->first();

        // Draft Purchase Order
        $poDraft = \App\Models\PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'status' => \App\Enums\PurchaseOrderStatus::Draft,
            'total_amount' => 0,
            'notes' => 'Rascunho de pedido de compra para reposição de estoque.',
            'created_by' => 1,
        ]);
        \App\Models\PurchaseOrderItem::create([
            'purchase_order_id' => $poDraft->id,
            'product_id' => $product1->id,
            'description' => $product1->name,
            'quantity' => 10,
            'unit' => $product1->commercial_unit,
            'unit_cost' => $product1->cost_price,
            'total_cost' => $product1->cost_price * 10,
        ]);
        $poDraft->recalculateTotal();

        // Ordered Purchase Order
        $poOrdered = \App\Models\PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'status' => \App\Enums\PurchaseOrderStatus::Ordered,
            'total_amount' => 0,
            'notes' => 'Pedido enviado para o fornecedor. Aguardando entrega física.',
            'created_by' => 1,
        ]);
        \App\Models\PurchaseOrderItem::create([
            'purchase_order_id' => $poOrdered->id,
            'product_id' => $product2->id,
            'description' => $product2->name,
            'quantity' => 5,
            'unit' => $product2->commercial_unit,
            'unit_cost' => $product2->cost_price,
            'total_cost' => $product2->cost_price * 5,
        ]);
        $poOrdered->recalculateTotal();

        // Pending Inventory Conference (Entrada) for the Ordered Purchase Order
        \App\Models\InventoryConference::create([
            'purchase_order_id' => $poOrdered->id,
            'status' => \App\Enums\InventoryConferenceStatus::Pending,
            'checked_by' => 1,
            'notes' => 'Aguardando chegada das caixas no estoque.',
        ]);

        // Received Purchase Order
        $poReceived = \App\Models\PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'status' => \App\Enums\PurchaseOrderStatus::Received,
            'total_amount' => 0,
            'notes' => 'Compra recebida na totalidade sem divergências.',
            'created_by' => 1,
        ]);
        \App\Models\PurchaseOrderItem::create([
            'purchase_order_id' => $poReceived->id,
            'product_id' => $product3->id,
            'description' => $product3->name,
            'quantity' => 15,
            'unit' => $product3->commercial_unit,
            'unit_cost' => $product3->cost_price,
            'total_cost' => $product3->cost_price * 15,
        ]);
        $poReceived->recalculateTotal();

        // Completed Inventory Conference (Entrada) for Received Purchase Order
        $confCompleted = \App\Models\InventoryConference::create([
            'purchase_order_id' => $poReceived->id,
            'status' => \App\Enums\InventoryConferenceStatus::Completed,
            'checked_by' => 1,
            'completed_at' => Carbon::now()->subDays(1),
            'notes' => 'Todas as 15 caixas conferidas e cadastradas no estoque.',
        ]);
        \App\Models\InventoryConferenceItem::create([
            'inventory_conference_id' => $confCompleted->id,
            'product_id' => $product3->id,
            'quantity_ordered' => 15,
            'quantity_received' => 15,
        ]);

        // Partially Received Purchase Order
        $poPartial = \App\Models\PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'status' => \App\Enums\PurchaseOrderStatus::PartiallyReceived,
            'total_amount' => 0,
            'notes' => 'Pedido com entrega parcial / divergência registrada.',
            'created_by' => 1,
        ]);
        \App\Models\PurchaseOrderItem::create([
            'purchase_order_id' => $poPartial->id,
            'product_id' => $product1->id,
            'description' => $product1->name,
            'quantity' => 20,
            'unit' => $product1->commercial_unit,
            'unit_cost' => $product1->cost_price,
            'total_cost' => $product1->cost_price * 20,
        ]);
        $poPartial->recalculateTotal();

        // Divergent Inventory Conference (Entrada) for Partially Received Purchase Order
        $confDivergent = \App\Models\InventoryConference::create([
            'purchase_order_id' => $poPartial->id,
            'status' => \App\Enums\InventoryConferenceStatus::Divergent,
            'checked_by' => 1,
            'completed_at' => Carbon::now()->subHours(4),
            'notes' => 'Faltaram 2 unidades no envio.',
        ]);
        \App\Models\InventoryConferenceItem::create([
            'inventory_conference_id' => $confDivergent->id,
            'product_id' => $product1->id,
            'quantity_ordered' => 20,
            'quantity_received' => 18,
        ]);
    }
}
