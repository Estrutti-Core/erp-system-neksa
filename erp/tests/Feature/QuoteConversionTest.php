<?php

namespace Tests\Feature;

use App\Actions\ConvertQuoteAction;
use App\Enums\ProductType;
use App\Enums\QuoteStatus;
use App\Enums\SaleStatus;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\User;
use App\Models\ServiceOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Exception;

class QuoteConversionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Client $client;
    protected ClientAddress $address;
    protected Product $productItem;
    protected Product $serviceItem;

    protected function setUp(): void
    {
        parent::setUp();

        // Criar usuário e autenticar
        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);

        // Criar cliente e endereço
        $this->client = Client::create([
            'name' => 'Cliente Teste',
            'email' => 'teste@neksa.com',
            'phone' => '11999999999',
            'document' => '12345678909',
            'is_active' => true,
        ]);

        $this->address = ClientAddress::create([
            'client_id' => $this->client->id,
            'street' => 'Rua das Flores',
            'number' => '123',
            'neighborhood' => 'Centro',
            'city' => 'São Paulo',
            'state' => 'SP',
            'zip_code' => '01001-000',
        ]);

        // Criar produtos e serviços no banco
        $this->productItem = Product::create([
            'name' => 'Câmera IP',
            'sku' => 'CAM-IP-01',
            'type' => ProductType::Product,
            'cost_price' => 100.00,
            'sale_price' => 250.00,
            'stock' => 10,
            'is_stock_controlled' => true,
            'is_active' => true,
            'fiscal_origin' => 0,
            'commercial_unit' => 'UN',
            'taxable_unit' => 'UN',
        ]);

        $this->serviceItem = Product::create([
            'name' => 'Instalação de Câmera',
            'sku' => 'SRV-INST-01',
            'type' => ProductType::Service,
            'cost_price' => 50.00,
            'sale_price' => 150.00,
            'is_stock_controlled' => false,
            'is_active' => true,
            'fiscal_origin' => 0,
            'commercial_unit' => 'SV',
            'taxable_unit' => 'SV',
        ]);
    }

    /**
     * Testa conversão com sucesso para Venda (apenas produtos).
     */
    public function test_can_convert_quote_with_only_products_to_sale(): void
    {
        $quote = Quote::create([
            'client_id' => $this->client->id,
            'client_address_id' => $this->address->id,
            'status' => QuoteStatus::Draft,
            'discount_amount' => 50.00,
        ]);

        QuoteItem::create([
            'quote_id' => $quote->id,
            'product_id' => $this->productItem->id,
            'description' => 'Câmera IP de Monitoramento',
            'quantity' => 2,
            'unit' => 'UN',
            'unit_price' => 250.00,
            'type' => ProductType::Product,
        ]);

        $quote->recalculateTotals();

        $action = new ConvertQuoteAction();
        $sale = $action->execute($quote, 'sale');

        $this->assertNotNull($sale);
        $this->assertEquals(SaleStatus::Pending, $sale->status);
        $this->assertEquals(450.00, $sale->total_amount); // 500 subtotal - 50 discount
        $this->assertEquals(QuoteStatus::Converted, $quote->fresh()->status);
        
        // Verifica estoque decrementado (de 10 para 8)
        $this->assertEquals(8, $this->productItem->fresh()->stock);

        // Verifica itens da venda criados
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $this->productItem->id,
            'quantity' => 2,
            'total_price' => 500.00,
        ]);
    }

    /**
     * Testa bloqueio de conversão para Venda quando contém Serviços.
     */
    public function test_blocks_converting_quote_with_services_to_sale(): void
    {
        $quote = Quote::create([
            'client_id' => $this->client->id,
            'client_address_id' => $this->address->id,
            'status' => QuoteStatus::Draft,
        ]);

        QuoteItem::create([
            'quote_id' => $quote->id,
            'product_id' => $this->serviceItem->id,
            'description' => 'Serviço de Instalação Técnica',
            'quantity' => 1,
            'unit' => 'SV',
            'unit_price' => 150.00,
            'type' => ProductType::Service,
        ]);

        $quote->recalculateTotals();

        $action = new ConvertQuoteAction();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Não é possível converter em Venda um orçamento que contenha Serviços.");

        $action->execute($quote, 'sale');
    }

    /**
     * Testa conversão com sucesso para Ordem de Serviço (contém serviço).
     */
    public function test_can_convert_quote_with_services_to_service_order(): void
    {
        $quote = Quote::create([
            'client_id' => $this->client->id,
            'client_address_id' => $this->address->id,
            'status' => QuoteStatus::Draft,
            'discount_amount' => 0.00,
        ]);

        // Adiciona 1 produto e 1 serviço
        QuoteItem::create([
            'quote_id' => $quote->id,
            'product_id' => $this->productItem->id,
            'description' => 'Câmera IP',
            'quantity' => 1,
            'unit' => 'UN',
            'unit_price' => 250.00,
            'type' => ProductType::Product,
        ]);

        QuoteItem::create([
            'quote_id' => $quote->id,
            'product_id' => $this->serviceItem->id,
            'description' => 'Instalação de Câmera',
            'quantity' => 1,
            'unit' => 'SV',
            'unit_price' => 150.00,
            'type' => ProductType::Service,
        ]);

        $quote->recalculateTotals();

        $action = new ConvertQuoteAction();
        $so = $action->execute($quote, 'service_order');

        $this->assertNotNull($so);
        $this->assertEquals('open', $so->status->slug);
        $this->assertEquals(400.00, $so->total_amount);
        $this->assertEquals(150.00, $so->service_amount);
        $this->assertEquals(250.00, $so->parts_amount);
        $this->assertEquals(QuoteStatus::Converted, $quote->fresh()->status);
        
        // Verifica estoque de produto decrementado (de 10 para 9)
        $this->assertEquals(9, $this->productItem->fresh()->stock);

        // Verifica itens da OS criados
        $this->assertDatabaseHas('service_order_items', [
            'service_order_id' => $so->id,
            'product_id' => $this->serviceItem->id,
            'type' => 'service',
            'total_price' => 150.00,
        ]);

        $this->assertDatabaseHas('service_order_items', [
            'service_order_id' => $so->id,
            'product_id' => $this->productItem->id,
            'type' => 'part',
            'total_price' => 250.00,
        ]);
    }

    /**
     * Testa bloqueio de conversão para OS quando NÃO contém Serviços.
     */
    public function test_blocks_converting_quote_without_services_to_service_order(): void
    {
        $quote = Quote::create([
            'client_id' => $this->client->id,
            'client_address_id' => $this->address->id,
            'status' => QuoteStatus::Draft,
        ]);

        QuoteItem::create([
            'quote_id' => $quote->id,
            'product_id' => $this->productItem->id,
            'description' => 'Câmera IP',
            'quantity' => 1,
            'unit' => 'UN',
            'unit_price' => 250.00,
            'type' => ProductType::Product,
        ]);

        $quote->recalculateTotals();

        $action = new ConvertQuoteAction();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Não é possível gerar uma Ordem de Serviço sem nenhum Serviço cadastrado.");

        $action->execute($quote, 'service_order');
    }
}
