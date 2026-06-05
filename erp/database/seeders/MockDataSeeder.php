<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\ClientEquipment;
use App\Models\Product;
use App\Models\Service;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Enums\ProductType;
use App\Enums\QuoteStatus;
use App\Enums\SaleStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class MockDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Products (Produtos Físicos/Peças)
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
            ],
            [
                'name' => 'SSD Kingston KC3000 1TB M.2 NVMe',
                'description' => 'Unidade de estado sólido interna PCIe 4.0 NVMe de alta velocidade',
                'sku' => 'PROD-KNG-KC3000-1T',
                'barcode' => '7891234560066',
                'ncm' => '85235110',
                'cfop' => '5102',
                'cst' => '40',
                'commercial_unit' => 'UN',
                'taxable_unit' => 'UN',
                'cost_price' => 320.00,
                'sale_price' => 580.00,
                'stock' => 20.000,
                'is_active' => true,
                'type' => ProductType::Product,
                'is_stock_controlled' => true,
            ],
            [
                'name' => 'Memória RAM Kingston Fury Beast 16GB DDR4 3200MHz',
                'description' => 'Módulo de memória RAM de alto desempenho para servidores e desktops',
                'sku' => 'PROD-KNG-16GD4',
                'barcode' => '7891234560073',
                'ncm' => '84733042',
                'cfop' => '5102',
                'cst' => '40',
                'commercial_unit' => 'UN',
                'taxable_unit' => 'UN',
                'cost_price' => 180.00,
                'sale_price' => 320.00,
                'stock' => 30.000,
                'is_active' => true,
                'type' => ProductType::Product,
                'is_stock_controlled' => true,
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
            ],
            [
                'name' => 'Instalação e Configuração de Câmeras CFTV IP',
                'description' => 'Instalação física de câmeras IP, configuração no gravador NVR e liberação de acesso externo',
                'sku' => 'SERV-INST-CFTV',
                'price' => 180.00,
                'cfop' => '5933',
                'cst' => '01',
                'iss_rate' => 4.00,
                'iss_withheld' => false,
                'pis_retention_rate' => 0.65,
                'cofins_retention_rate' => 3.00,
                'csll_retention_rate' => 1.00,
                'inss_retention_rate' => 1.50,
                'municipal_service_code' => '14.02',
                'is_active' => true,
            ],
            [
                'name' => 'Manutenção Preventiva de Servidor Físico',
                'description' => 'Limpeza interna do servidor, teste de backup, verificação de integridade de discos/RAID e atualização de firmware',
                'sku' => 'SERV-MANT-SERV',
                'price' => 400.00,
                'cfop' => '5933',
                'cst' => '01',
                'iss_rate' => 5.00,
                'iss_withheld' => false,
                'pis_retention_rate' => 0.65,
                'cofins_retention_rate' => 3.00,
                'csll_retention_rate' => 1.00,
                'inss_retention_rate' => 0.00,
                'municipal_service_code' => '01.03',
                'is_active' => true,
            ]
        ];

        $services = [];
        foreach ($servicesData as $data) {
            $services[] = Service::updateOrCreate(['sku' => $data['sku']], $data);
        }

        // 3. Fetch seeded Clients to associate with equipments, quotes and sales
        $clients = Client::with('addresses')->get();
        if ($clients->isEmpty()) {
            return; // Safety check
        }

        // Associate Client Equipments for active clients
        $equipmentsData = [
            'Supermercado Bom Preço Ltda' => [
                ['name' => 'Servidor Dell PowerEdge R440', 'brand' => 'Dell', 'model' => 'R440', 'serial_number' => 'DEL-XP99-BP1', 'notes' => 'Servidor de banco de dados e caixa principal'],
                ['name' => 'Roteador Cisco RV340', 'brand' => 'Cisco', 'model' => 'RV340-K9', 'serial_number' => 'CS-8877-BP2', 'notes' => 'Firewall da rede interna e VPN'],
            ],
            'Clínica Saúde Total' => [
                ['name' => 'Central Telefônica IP Grandstream', 'brand' => 'Grandstream', 'model' => 'UCM6202', 'serial_number' => 'GS-6677-ST1', 'notes' => 'Responsável por toda a telefonia e ramais da clínica'],
                ['name' => 'NVR Intelbras 32 Canais', 'brand' => 'Intelbras', 'model' => 'NVD 7132', 'serial_number' => 'IB-5544-ST2', 'notes' => 'Gravador de câmeras da portaria e consultórios'],
            ],
            'Hotel Grand Palace' => [
                ['name' => 'Switch Core HP Aruba 24p', 'brand' => 'HP Aruba', 'model' => '2530-24G', 'serial_number' => 'AR-4433-GP1', 'notes' => 'Switch central do rack do hotel'],
                ['name' => 'Controladora Wi-Fi UniFi Cloud Key Gen2', 'brand' => 'Ubiquiti', 'model' => 'UCK-G2-PLUS', 'serial_number' => 'UB-3322-GP2', 'notes' => 'Gerenciamento dos access points dos quartos'],
            ],
            'Padaria Pão Quente' => [
                ['name' => 'Balancete Digital Integrado Toledo', 'brand' => 'Toledo', 'model' => 'Prix 5 Plus', 'serial_number' => 'TL-2211-PQ1', 'notes' => 'Balança de frios com integração ao PDV'],
            ]
        ];

        foreach ($equipmentsData as $clientName => $eqs) {
            $client = $clients->firstWhere('name', $clientName);
            if ($client) {
                foreach ($eqs as $eq) {
                    ClientEquipment::updateOrCreate(
                        ['client_id' => $client->id, 'serial_number' => $eq['serial_number']],
                        [
                            'name' => $eq['name'],
                            'brand' => $eq['brand'],
                            'model' => $eq['model'],
                            'notes' => $eq['notes'],
                        ]
                    );
                }
            }
        }

        // 4. Seed Quotes (Orçamentos)
        $quoteClients = $clients->take(4);
        
        // Quote 1: Draft - Mixed Products and Services
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
            'product_id' => $products[4]->id, // Ubiquiti AP
            'description' => $products[4]->name,
            'quantity' => 2.000,
            'unit' => $products[4]->commercial_unit,
            'unit_price' => $products[4]->sale_price,
            'type' => ProductType::Product,
        ]);

        QuoteItem::create([
            'quote_id' => $q1->id,
            'product_id' => $products[2]->id, // Nexans Cable
            'description' => $products[2]->name,
            'quantity' => 1.000,
            'unit' => $products[2]->commercial_unit,
            'unit_price' => $products[2]->sale_price,
            'type' => ProductType::Product,
        ]);

        QuoteItem::create([
            'quote_id' => $q1->id,
            'service_id' => $services[0]->id, // Roteador config
            'description' => $services[0]->name,
            'quantity' => 1.000,
            'unit' => 'UN',
            'unit_price' => $services[0]->price,
            'type' => ProductType::Service,
        ]);

        $q1->recalculateTotals();

        // Quote 2: Sent - Services Only
        $c2 = $quoteClients->get(1);
        $addr2 = $c2->addresses->first();
        $q2 = Quote::create([
            'client_id' => $c2->id,
            'client_address_id' => $addr2?->id,
            'status' => QuoteStatus::Sent,
            'valid_until' => Carbon::now()->addDays(10),
            'notes' => 'Consultoria técnica para reestruturação física do rack principal de telecomunicações.',
            'internal_notes' => 'Enviado por e-mail no dia de ontem.',
            'discount_amount' => 0.00,
        ]);

        QuoteItem::create([
            'quote_id' => $q2->id,
            'service_id' => $services[2]->id, // Consultoria
            'description' => $services[2]->name,
            'quantity' => 5.000, // 5 horas
            'unit' => 'UN',
            'unit_price' => $services[2]->price,
            'type' => ProductType::Service,
        ]);

        QuoteItem::create([
            'quote_id' => $q2->id,
            'service_id' => $services[4]->id, // Preventiva Servidor
            'description' => $services[4]->name,
            'quantity' => 1.000,
            'unit' => 'UN',
            'unit_price' => $services[4]->price,
            'type' => ProductType::Service,
        ]);

        $q2->recalculateTotals();

        // Quote 3: Approved - Products Only (Ready to convert to Sale)
        $c3 = $quoteClients->get(2);
        $addr3 = $c3->addresses->first();
        $q3 = Quote::create([
            'client_id' => $c3->id,
            'client_address_id' => $addr3?->id,
            'status' => QuoteStatus::Approved,
            'valid_until' => Carbon::now()->addDays(5),
            'notes' => 'Aquisição de memórias e SSDs para upgrade das estações de trabalho da recepção.',
            'internal_notes' => 'Aprovado pelo gerente de compras.',
            'discount_amount' => 100.00,
        ]);

        QuoteItem::create([
            'quote_id' => $q3->id,
            'product_id' => $products[5]->id, // SSD
            'description' => $products[5]->name,
            'quantity' => 4.000,
            'unit' => $products[5]->commercial_unit,
            'unit_price' => $products[5]->sale_price,
            'type' => ProductType::Product,
        ]);

        QuoteItem::create([
            'quote_id' => $q3->id,
            'product_id' => $products[6]->id, // RAM
            'description' => $products[6]->name,
            'quantity' => 8.000,
            'unit' => $products[6]->commercial_unit,
            'unit_price' => $products[6]->sale_price,
            'type' => ProductType::Product,
        ]);

        $q3->recalculateTotals();

        // Quote 4: Converted - Services Only
        $c4 = $quoteClients->get(3);
        $addr4 = $c4->addresses->first();
        $q4 = Quote::create([
            'client_id' => $c4->id,
            'client_address_id' => $addr4?->id,
            'status' => QuoteStatus::Converted,
            'valid_until' => Carbon::now()->subDays(2),
            'notes' => 'Instalação de pontos de rede adicionais para novas gavetas de atendimento.',
            'internal_notes' => 'Convertido com sucesso em Ordem de Serviço.',
            'discount_amount' => 0.00,
            'converted_at' => Carbon::now()->subDays(1),
        ]);

        QuoteItem::create([
            'quote_id' => $q4->id,
            'service_id' => $services[1]->id, // Instalação Cabo
            'description' => $services[1]->name,
            'quantity' => 8.000, // 8 pontos
            'unit' => 'UN',
            'unit_price' => $services[1]->price,
            'type' => ProductType::Service,
        ]);

        $q4->recalculateTotals();

        // 5. Seed Sales (Vendas Comerciais)
        // Sale 1: Pending - Linked to Quote 3 (Approved Products Only Quote)
        $s1 = Sale::create([
            'client_id' => $q3->client_id,
            'client_address_id' => $q3->client_address_id,
            'quote_id' => $q3->id,
            'status' => SaleStatus::Pending,
            'discount_amount' => $q3->discount_amount,
            'items_amount' => $q3->items_amount,
            'total_amount' => $q3->total_amount,
            'notes' => 'Venda comercial gerada através do orçamento ' . $q3->code . '.',
        ]);

        foreach ($q3->items as $item) {
            SaleItem::create([
                'sale_id' => $s1->id,
                'product_id' => $item->product_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'total_price' => $item->total_price,
            ]);
        }

        // Sale 2: Completed (Faturada) - Not linked to any Quote, direct sale
        $s2 = Sale::create([
            'client_id' => $c1->id,
            'client_address_id' => $addr1?->id,
            'status' => SaleStatus::Completed,
            'discount_amount' => 0.00,
            'items_amount' => 0.00,
            'total_amount' => 0.00,
            'notes' => 'Venda direta de switch MikroTik de emergência.',
        ]);

        SaleItem::create([
            'sale_id' => $s2->id,
            'product_id' => $products[0]->id, // MikroTik Router
            'description' => $products[0]->name,
            'quantity' => 1.000,
            'unit' => $products[0]->commercial_unit,
            'unit_price' => $products[0]->sale_price,
        ]);

        $s2->recalculateTotals();

        // Sale 3: Cancelled - Linked to Quote 1 (Draft simulation converted & cancelled)
        $s3 = Sale::create([
            'client_id' => $c2->id,
            'client_address_id' => $addr2?->id,
            'status' => SaleStatus::Cancelled,
            'discount_amount' => 0.00,
            'items_amount' => 0.00,
            'total_amount' => 0.00,
            'notes' => 'Venda cancelada por solicitação do financeiro do cliente.',
        ]);

        SaleItem::create([
            'sale_id' => $s3->id,
            'product_id' => $products[1]->id, // Switch TP Link
            'description' => $products[1]->name,
            'quantity' => 1.000,
            'unit' => $products[1]->commercial_unit,
            'unit_price' => $products[1]->sale_price,
        ]);

        $s3->recalculateTotals();
    }
}
