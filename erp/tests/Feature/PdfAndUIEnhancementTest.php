<?php

namespace Tests\Feature;

use App\Enums\ProductType;
use App\Enums\QuoteStatus;
use App\Enums\SaleStatus;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfAndUIEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Client $client;
    protected ClientAddress $address;
    protected Product $productItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);

        $this->client = Client::create([
            'name' => 'Cliente Teste Ltda',
            'email' => 'teste@neksa.com',
            'phone' => '11999999999',
            'document' => '12345678000199',
            'is_active' => true,
        ]);

        $this->address = ClientAddress::create([
            'client_id' => $this->client->id,
            'street' => 'Av. Paulista',
            'number' => '1000',
            'neighborhood' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'SP',
            'zip_code' => '01310-100',
        ]);

        $this->productItem = Product::create([
            'name' => 'Roteador Wi-Fi 6',
            'sku' => 'ROT-WF6',
            'type' => ProductType::Product,
            'cost_price' => 200.00,
            'sale_price' => 450.00,
            'stock' => 20,
            'is_stock_controlled' => true,
            'is_active' => true,
            'fiscal_origin' => 0,
            'commercial_unit' => 'UN',
            'taxable_unit' => 'UN',
        ]);
    }

    public function test_can_download_service_order_pdf(): void
    {
        $os = ServiceOrder::create([
            'code' => 'OS-2026-00001',
            'client_id' => $this->client->id,
            'client_address_id' => $this->address->id,
            'technician_id' => $this->admin->id,
            'created_by' => $this->admin->id,
            'description' => 'Instalação física de rede local',
            'scheduled_at' => now()->addDays(2),
        ]);

        ServiceOrderItem::create([
            'service_order_id' => $os->id,
            'description' => 'Serviço de cabeamento estruturado',
            'quantity' => 1,
            'unit' => 'UN',
            'unit_price' => 300.00,
            'type' => 'service',
        ]);

        $response = $this->get(route('service-orders.pdf', $os));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('CLIENTE-TESTE-LTDA-OS-2026-00001.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_can_download_quote_pdf(): void
    {
        $quote = Quote::create([
            'code' => 'ORC-2026-00001',
            'client_id' => $this->client->id,
            'client_address_id' => $this->address->id,
            'status' => QuoteStatus::Draft,
            'discount_amount' => 50.00,
        ]);

        QuoteItem::create([
            'quote_id' => $quote->id,
            'product_id' => $this->productItem->id,
            'description' => 'Roteador Wi-Fi 6',
            'quantity' => 2,
            'unit' => 'UN',
            'unit_price' => 450.00,
            'type' => ProductType::Product,
        ]);

        $response = $this->get(route('quotes.pdf', $quote));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('CLIENTE-TESTE-LTDA-ORC-2026-00001.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_can_download_sale_pdf(): void
    {
        $sale = Sale::create([
            'code' => 'VEN-2026-00001',
            'client_id' => $this->client->id,
            'client_address_id' => $this->address->id,
            'status' => SaleStatus::Completed,
            'discount_amount' => 10.00,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $this->productItem->id,
            'description' => 'Roteador Wi-Fi 6',
            'quantity' => 1,
            'unit' => 'UN',
            'unit_price' => 450.00,
        ]);

        $response = $this->get(route('sales.pdf', $sale));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('CLIENTE-TESTE-LTDA-VENDA-2026-00001.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_can_store_and_update_quote_with_equipment(): void
    {
        $equipment = \App\Models\ClientEquipment::create([
            'client_id' => $this->client->id,
            'name' => 'Ar Condicionado Split',
            'brand' => 'Midea',
            'model' => 'Liva',
            'serial_number' => 'SN12345678',
        ]);

        $postData = [
            'client_id' => $this->client->id,
            'client_address_id' => $this->address->id,
            'equipment_id' => $equipment->id,
            'valid_until' => now()->addDays(15)->format('Y-m-d'),
            'notes' => 'Observações do orçamento',
            'discount_amount' => '0,00',
            'items' => [
                [
                    'product_id' => $this->productItem->id,
                    'description' => 'Roteador Wi-Fi 6',
                    'quantity' => '1',
                    'unit_price' => '450,00',
                ]
            ]
        ];

        $response = $this->post(route('quotes.store'), $postData);
        $response->assertRedirect();

        $quote = Quote::latest('id')->first();
        $this->assertEquals($equipment->id, $quote->equipment_id);

        // Atualizar tirando o equipamento
        $postData['equipment_id'] = '';
        $response = $this->put(route('quotes.update', $quote), $postData);
        $response->assertRedirect();

        $quote->refresh();
        $this->assertNull($quote->equipment_id);
    }

    public function test_conversion_copies_equipment_id_to_service_order(): void
    {
        $equipment = \App\Models\ClientEquipment::create([
            'client_id' => $this->client->id,
            'name' => 'Ar Condicionado Split',
            'brand' => 'Midea',
            'model' => 'Liva',
            'serial_number' => 'SN12345678',
        ]);

        $quote = Quote::create([
            'code' => 'ORC-2026-00099',
            'client_id' => $this->client->id,
            'client_address_id' => $this->address->id,
            'equipment_id' => $equipment->id,
            'status' => QuoteStatus::Draft,
            'discount_amount' => 0.00,
        ]);

        $service = \App\Models\Service::create([
            'name' => 'Instalação física',
            'sku' => 'SRV-INST',
            'price' => 200.00,
            'is_active' => true,
        ]);

        QuoteItem::create([
            'quote_id' => $quote->id,
            'service_id' => $service->id,
            'description' => 'Instalação física',
            'quantity' => 1,
            'unit' => 'un',
            'unit_price' => 200.00,
            'type' => ProductType::Service,
        ]);

        $response = $this->post(route('quotes.convert', $quote), [
            'destination_type' => 'service_order',
        ]);
        $response->assertRedirect();

        $os = ServiceOrder::latest('id')->first();
        $this->assertEquals($equipment->id, $os->equipment_id);
    }

    public function test_ui_contains_pdf_and_edit_links(): void
    {
        $os = ServiceOrder::create([
            'code' => 'OS-2026-00002',
            'client_id' => $this->client->id,
            'client_address_id' => $this->address->id,
            'created_by' => $this->admin->id,
            'description' => 'Instalação física de rede local',
            'scheduled_at' => now()->addDays(2),
        ]);

        $response = $this->get(route('service-orders.index'));
        $response->assertStatus(200);
        $response->assertSee(route('service-orders.pdf', $os));
        $response->assertSee(route('service-orders.edit', $os));
    }
}
