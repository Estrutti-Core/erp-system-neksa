<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Enums\PurchaseOrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupplierAndPurchaseUIWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed das roles
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'operator']);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->company = Company::first() ?? Company::create([
            'name' => 'Empresa Teste',
            'allow_negative_stock' => false,
        ]);
    }

    /**
     * Teste do CRUD de Fornecedores.
     */
    public function test_supplier_crud_screens_and_actions(): void
    {
        $this->actingAs($this->adminUser);

        // Tela de listagem
        $response = $this->get(route('suppliers.index'));
        $response->assertStatus(200);

        // Tela de criação
        $response = $this->get(route('suppliers.create'));
        $response->assertStatus(200);

        // Store
        $response = $this->post(route('suppliers.store'), [
            'name' => 'Distribuidora Alpha',
            'document_type' => 'cnpj',
            'document' => '12345678000199',
            'phone' => '11999998888',
            'email' => 'alpha@dist.com',
        ]);
        $response->assertRedirect(route('suppliers.index'));
        $this->assertDatabaseHas('suppliers', ['name' => 'Distribuidora Alpha']);

        $supplier = Supplier::where('name', 'Distribuidora Alpha')->first();

        // Tela de detalhes
        $response = $this->get(route('suppliers.show', $supplier));
        $response->assertStatus(200);

        // Tela de edição
        $response = $this->get(route('suppliers.edit', $supplier));
        $response->assertStatus(200);

        // Update
        $response = $this->put(route('suppliers.update', $supplier), [
            'name' => 'Distribuidora Alpha LTDA',
            'document_type' => 'cnpj',
            'document' => '12345678000199',
            'phone' => '11999997777',
            'email' => 'contato@alpha.com',
        ]);
        $response->assertRedirect(route('suppliers.show', $supplier));
        $this->assertDatabaseHas('suppliers', ['name' => 'Distribuidora Alpha LTDA', 'phone' => '11999997777']);

        // Destroy
        $response = $this->delete(route('suppliers.destroy', $supplier));
        $response->assertRedirect(route('suppliers.index'));
        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
    }

    /**
     * Teste do CRUD de Pedidos de Compra.
     */
    public function test_purchase_order_crud_screens_and_actions(): void
    {
        $this->actingAs($this->adminUser);

        $supplier = Supplier::create([
            'name' => 'Fornecedor Beta',
            'document_type' => 'cnpj',
            'document' => '98765432000188',
            'phone' => '11988887777',
            'email' => 'beta@fornecedor.com',
        ]);

        $product = Product::create([
            'name' => 'Parafuso Allen',
            'sku' => 'PAR-AL-02',
            'type' => \App\Enums\ProductType::Product,
            'cost_price' => 150.00,
            'sale_price' => 250.00,
            'stock' => 10.0,
            'is_stock_controlled' => true,
            'is_active' => true,
            'fiscal_origin' => 0,
            'commercial_unit' => 'UN',
            'taxable_unit' => 'UN',
        ]);

        // Tela de listagem
        $response = $this->get(route('purchase-orders.index'));
        $response->assertStatus(200);

        // Tela de criação
        $response = $this->get(route('purchase-orders.create'));
        $response->assertStatus(200);

        // Store
        $response = $this->post(route('purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'unit_cost' => 140.00,
                ]
            ]
        ]);
        $response->assertRedirect(route('purchase-orders.index'));
        $this->assertDatabaseHas('purchase_orders', ['supplier_id' => $supplier->id]);

        $order = PurchaseOrder::latest()->first();

        // Tela de detalhes
        $response = $this->get(route('purchase-orders.show', $order));
        $response->assertStatus(200);

        // Tela de edição
        $response = $this->get(route('purchase-orders.edit', $order));
        $response->assertStatus(200);

        // Update
        $response = $this->put(route('purchase-orders.update', $order), [
            'supplier_id' => $supplier->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 15,
                    'unit_cost' => 135.00,
                ]
            ]
        ]);
        $response->assertRedirect(route('purchase-orders.show', $order));
        
        $order->refresh();
        $this->assertEquals(2025.00, $order->total_amount); // 15 * 135 = 2025
    }

    /**
     * Teste do fluxo completo de emissão e recebimento físico.
     */
    public function test_purchase_order_flow_order_and_receive(): void
    {
        $this->actingAs($this->adminUser);

        $supplier = Supplier::create([
            'name' => 'Fornecedor Gama',
            'document_type' => 'cnpj',
            'document' => '11223344000155',
            'phone' => '11977776666',
            'email' => 'gama@fornecedor.com',
        ]);

        $product = Product::create([
            'name' => 'Porca Sextavada',
            'sku' => 'POR-SX-03',
            'type' => \App\Enums\ProductType::Product,
            'cost_price' => 100.00,
            'sale_price' => 180.00,
            'stock' => 5.0,
            'is_stock_controlled' => true,
            'is_active' => true,
            'fiscal_origin' => 0,
            'commercial_unit' => 'UN',
            'taxable_unit' => 'UN',
        ]);

        $order = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Draft,
            'total_amount' => 500.00,
            'created_by' => $this->adminUser->id,
        ]);

        $orderItem = $order->items()->create([
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => 5,
            'unit' => 'UN',
            'unit_cost' => 100.00,
            'total_cost' => 500.00,
        ]);

        // Emitir o Pedido (Draft -> Ordered)
        $response = $this->post(route('purchase-orders.order', $order));
        $response->assertRedirect(route('purchase-orders.show', $order));

        $order->refresh();
        $this->assertEquals(PurchaseOrderStatus::Ordered, $order->status);

        // Iniciar recebimento físico
        $response = $this->post(route('inventory-conferences.store'), [
            'purchase_order_id' => $order->id,
        ]);
        
        $conference = $order->refresh()->inventoryConferences()->latest()->first();
        $response->assertRedirect(route('inventory-conferences.show', $conference));

        // Tela de digitação da conferência
        $response = $this->get(route('inventory-conferences.show', $conference));
        $response->assertStatus(200);

        // Concluir conferência com contagem correta
        $confItem = $conference->items()->first();
        $response = $this->post(route('inventory-conferences.complete', $conference), [
            'counts' => [
                $confItem->id => 5, // Recebeu as 5 unidades pedidas
            ],
            'notes' => 'Tudo conferido de acordo com a nota fiscal.',
        ]);
        $response->assertRedirect(route('purchase-orders.show', $order));

        // Validações pós-conferência
        $order->refresh();
        $conference->refresh();
        $product->refresh();

        $this->assertEquals(PurchaseOrderStatus::Received, $order->status);
        $this->assertNotNull($conference->completed_at);
        $this->assertEquals(10.0, (float) $product->stock); // 5 antigos + 5 recebidos (o campo é stock em Product!)
    }
}
