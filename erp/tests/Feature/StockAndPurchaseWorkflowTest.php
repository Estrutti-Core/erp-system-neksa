<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\InventoryConference;
use App\Models\InventoryConferenceItem;
use App\Models\StockMovement;
use App\Models\Company;
use App\Models\User;
use App\Enums\PurchaseOrderStatus;
use App\Enums\InventoryConferenceStatus;
use App\Enums\StockMovementType;
use App\Enums\StockMovementSource;
use App\Services\StockMovementService;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockAndPurchaseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;
    protected Product $product;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);

        // Garante a existência da empresa ativa
        $this->company = Company::first() ?? Company::create([
            'name' => 'Neksa Teste',
            'allow_negative_stock' => false,
        ]);

        $this->product = Product::create([
            'name' => 'Parafuso Philips',
            'sku' => 'PAR-PH-01',
            'type' => \App\Enums\ProductType::Product,
            'cost_price' => 1.50,
            'sale_price' => 3.00,
            'stock' => 10.000,
            'is_stock_controlled' => true,
            'is_active' => true,
            'fiscal_origin' => 0,
            'commercial_unit' => 'UN',
            'taxable_unit' => 'UN',
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Fornecedor de Fixadores S/A',
            'document' => '12345678000199', // CNPJ válido fictício
            'document_type' => 'cnpj',
            'email' => 'vendas@fixadores.com',
            'phone' => '1133334444',
        ]);
    }

    /**
     * Testa bloqueio de estoque negativo.
     */
    public function test_blocks_negative_stock_when_not_allowed(): void
    {
        // Garante que a empresa não permite estoque negativo
        $this->company->update(['allow_negative_stock' => false]);

        $stockService = app(StockMovementService::class);

        // Tentativa de tirar 15 unidades do estoque (saldo atual é 10)
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Estoque insuficiente para o produto 'Parafuso Philips'. Saldo atual: 10, solicitado: 15.");

        $stockService->move(
            $this->product,
            -15.0,
            StockMovementType::Output,
            StockMovementSource::Manual,
            null,
            $this->admin,
            'Ajuste manual para teste'
        );
    }

    /**
     * Testa permissão de estoque negativo quando ativado na empresa.
     */
    public function test_allows_negative_stock_when_configured(): void
    {
        // Permite estoque negativo
        $this->company->update(['allow_negative_stock' => true]);

        $stockService = app(StockMovementService::class);

        $movement = $stockService->move(
            $this->product,
            -15.0,
            StockMovementType::Output,
            StockMovementSource::Manual,
            null,
            $this->admin,
            'Ajuste manual com estoque negativo permitido'
        );

        $this->assertNotNull($movement);
        $this->assertEquals(-15.0, $movement->quantity);
        $this->assertEquals(10.0, $movement->stock_before);
        $this->assertEquals(-5.0, $movement->stock_after);
        $this->assertEquals(-5.0, $this->product->fresh()->stock);
    }

    /**
     * Testa a imutabilidade absoluta de registros de stock_movements.
     */
    public function test_stock_movements_are_absolutely_immutable(): void
    {
        $stockService = app(StockMovementService::class);

        $movement = $stockService->move(
            $this->product,
            -2.0,
            StockMovementType::Output,
            StockMovementSource::Manual,
            null,
            $this->admin,
            'Movimentação de teste imutabilidade'
        );

        $this->assertNotNull($movement);

        // Tentativa de update deve lançar exceção ou ser bloqueada
        $this->expectException(\Exception::class);
        $movement->update(['quantity' => -5.0]);
    }

    /**
     * Testa exclusão bloqueada de stock_movements.
     */
    public function test_stock_movements_cannot_be_deleted(): void
    {
        $stockService = app(StockMovementService::class);

        $movement = $stockService->move(
            $this->product,
            -2.0,
            StockMovementType::Output,
            StockMovementSource::Manual,
            null,
            $this->admin,
            'Movimentação de teste imutabilidade'
        );

        $this->assertNotNull($movement);

        // Tentativa de delete deve lançar exceção
        $this->expectException(\Exception::class);
        $movement->delete();
    }

    /**
     * Testa recebimentos parciais e múltiplas conferências de compra.
     */
    public function test_partial_receiving_and_multiple_conferences_workflow(): void
    {
        $purchaseService = app(PurchaseOrderService::class);

        // 1. Criar Pedido de Compra de 100 unidades
        $order = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrderStatus::Draft,
            'total_amount' => 150.00,
            'created_by' => $this->admin->id,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $order->id,
            'product_id' => $this->product->id,
            'description' => $this->product->name,
            'quantity' => 100.0,
            'unit' => 'UN',
            'unit_cost' => 1.50,
            'total_cost' => 150.00,
        ]);

        // Envia/Emite o pedido
        $order = $purchaseService->order($order, $this->admin);
        $this->assertEquals(PurchaseOrderStatus::Ordered, $order->status);

        // 2. Criar Primeira Conferência (Recebimento Parcial de 60 unidades)
        $conf1 = $purchaseService->createConference($order, $this->admin);
        $this->assertEquals(InventoryConferenceStatus::Pending, $conf1->status);
        $this->assertEquals(100.0, $conf1->items()->first()->quantity_ordered);

        // Finaliza primeira conferência com 60 unidades recebidas
        $conf1 = $purchaseService->completeConference($conf1, $this->admin, [
            $this->product->id => 60.0
        ], 'Primeira remessa entregue parcialmente');

        $this->assertEquals(InventoryConferenceStatus::Divergent, $conf1->status); // Recebeu 60 de 100 (divergência/parcial)
        $this->assertEquals(PurchaseOrderStatus::PartiallyReceived, $order->fresh()->status);
        $this->assertEquals(70.0, $this->product->fresh()->stock); // Saldo anterior 10 + 60 recebidos = 70

        // 3. Criar Segunda Conferência para o saldo restante (40 unidades)
        $pendingBalances = $purchaseService->getPendingBalances($order->fresh());
        $this->assertEquals(40.0, $pendingBalances[$this->product->id]);

        $conf2 = $purchaseService->createConference($order->fresh(), $this->admin);
        $this->assertEquals(InventoryConferenceStatus::Pending, $conf2->status);
        $this->assertEquals(40.0, $conf2->items()->first()->quantity_ordered); // Esperado agora é o saldo restante!

        // Finaliza segunda conferência recebendo as 40 unidades restantes
        $conf2 = $purchaseService->completeConference($conf2, $this->admin, [
            $this->product->id => 40.0
        ], 'Segunda remessa contendo saldo restante');

        $this->assertEquals(InventoryConferenceStatus::Completed, $conf2->status); // Sem divergências contra o saldo esperado (40)
        $this->assertEquals(PurchaseOrderStatus::Received, $order->fresh()->status); // Totalmente atendido!
        $this->assertEquals(110.0, $this->product->fresh()->stock); // Saldo anterior 70 + 40 recebidos = 110
    }
}
