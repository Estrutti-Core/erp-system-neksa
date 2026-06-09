<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Client;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderStatus;
use App\Models\Sale;
use App\Enums\SaleStatus;
use App\Models\PurchaseOrder;
use App\Enums\PurchaseOrderStatus;
use App\Models\InventoryConference;
use App\Models\Receivable;
use App\Models\Payable;
use App\Models\User;
use App\Enums\PaymentStatus;
use App\Enums\InstallmentStatus;
use App\Enums\PaymentMethod;
use App\Services\ServiceOrderService;
use App\Services\SaleService;
use App\Services\PurchaseOrderService;
use App\Services\FinancialService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancialIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;
    protected Client $client;
    protected Supplier $supplier;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);

        $this->company = Company::create([
            'name' => 'Neksa Integracao S/A',
            'allow_negative_stock' => true,
        ]);

        $this->client = Client::create([
            'name' => 'Cliente Neksa',
            'document' => '12345678901',
            'document_type' => 'cpf',
            'email' => 'cliente@neksa.com',
            'phone' => '11999998888',
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Fornecedor Neksa',
            'document' => '12345678000199',
            'document_type' => 'cnpj',
            'email' => 'forn@neksa.com',
            'phone' => '1133334444',
        ]);

        $this->product = Product::create([
            'name' => 'Parafuso',
            'sku' => 'PAR-01',
            'type' => \App\Enums\ProductType::Product,
            'cost_price' => 1.00,
            'sale_price' => 2.00,
            'stock' => 100.0,
            'is_stock_controlled' => true,
            'is_active' => true,
            'fiscal_origin' => 0,
            'commercial_unit' => 'UN',
            'taxable_unit' => 'UN',
        ]);

        // Garante os status operacionais básicos de OS
        $this->ensureServiceOrderStatus('open', 'Aberto', false, false);
        $this->ensureServiceOrderStatus('completed', 'Concluido', true, false);
        $this->ensureServiceOrderStatus('cancelled', 'Cancelado', false, true);
    }

    private function ensureServiceOrderStatus(string $slug, string $name, bool $isCompleted, bool $isCancelled): ServiceOrderStatus
    {
        $status = ServiceOrderStatus::where('slug', $slug)->first();
        if (!$status) {
            $status = ServiceOrderStatus::create([
                'slug' => $slug,
                'name' => $name,
                'is_completed_state' => $isCompleted,
                'is_cancelled_state' => $isCancelled,
                'is_system' => true,
            ]);
        } else {
            $status->update([
                'is_completed_state' => $isCompleted,
                'is_cancelled_state' => $isCancelled,
            ]);
        }
        return $status;
    }

    /**
     * Teste de faturamento automático e bloqueio bidirecional em OS.
     */
    public function test_service_order_financial_integration_and_bidirection_block(): void
    {
        $openStatus = ServiceOrderStatus::where('slug', 'open')->firstOrFail();
        $completedStatus = ServiceOrderStatus::where('slug', 'completed')->firstOrFail();
        $cancelledStatus = ServiceOrderStatus::where('slug', 'cancelled')->firstOrFail();

        // Configura as transições pivot Many-to-Many
        $openStatus->allowedTransitions()->detach();
        $completedStatus->allowedTransitions()->detach();
        $openStatus->allowedTransitions()->attach([$completedStatus->id, $cancelledStatus->id]);
        $completedStatus->allowedTransitions()->attach([$cancelledStatus->id]);

        $so = ServiceOrder::create([
            'code' => 'OS-2026-99999',
            'client_id' => $this->client->id,
            'status_id' => $openStatus->id,
            'total_amount' => 500.00,
            'service_amount' => 500.00,
            'parts_amount' => 0.00,
            'created_by' => $this->admin->id,
            'priority' => 'normal',
            'description' => 'Serviço de Teste Integrado',
        ]);

        // Mock signatures, checklists, checkins to allow completion
        $so->checkins()->create([
            'type' => 'checkin',
            'latitude' => 0.0,
            'longitude' => 0.0,
            'user_id' => $this->admin->id,
            'checked_at' => now(),
        ]);
        $so->checkins()->create([
            'type' => 'checkout',
            'latitude' => 0.0,
            'longitude' => 0.0,
            'user_id' => $this->admin->id,
            'checked_at' => now(),
        ]);
        
        $so->signature()->create([
            'signer_name' => 'Cliente Neksa Signature',
            'path' => 'signatures/test.png',
            'signed_at' => now(),
        ]);

        $soService = app(ServiceOrderService::class);

        // 1. Conclui OS => Gera recebível
        $soService->changeStatus($so, $completedStatus, $this->admin);

        $this->assertDatabaseHas('receivables', [
            'source_type' => get_class($so),
            'source_id' => $so->id,
            'status' => PaymentStatus::Pending->value,
            'total_amount' => 500.00,
        ]);

        $receivable = Receivable::where('source_type', get_class($so))->where('source_id', $so->id)->firstOrFail();
        $installment = $receivable->installments->first();

        // 2. Baixa parcial da parcela
        app(FinancialService::class)->payReceivableInstallment(
            $installment,
            100.00,
            PaymentMethod::Pix,
            now(),
            0.00,
            0.00,
            $this->admin
        );

        // 3. Tenta cancelar a OS => Bloqueio bidirecional deve atuar
        try {
            $soService->changeStatus($so, $cancelledStatus, $this->admin);
            $this->fail('Deveria ter lançado exceção de cancelamento bloqueado por movimentação financeira liquidada.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Não é possível cancelar este documento porque existem movimentações financeiras já liquidadas.', $e->getMessage());
        }

        // Verifica que a OS continua Concluída
        $this->assertEquals($completedStatus->id, $so->fresh()->status_id);
    }

    /**
     * Teste de faturamento automático e cancelamento integrado em Vendas.
     */
    public function test_sale_financial_integration_and_cancellation(): void
    {
        $sale = Sale::create([
            'code' => 'V-999',
            'client_id' => $this->client->id,
            'total_amount' => 250.00,
            'status' => SaleStatus::Pending,
        ]);

        $saleService = app(SaleService::class);

        // 1. Conclui a Venda => Gera recebível
        $saleService->complete($sale, $this->admin);

        $this->assertDatabaseHas('receivables', [
            'source_type' => get_class($sale),
            'source_id' => $sale->id,
            'status' => PaymentStatus::Pending->value,
            'total_amount' => 250.00,
        ]);

        // 2. Cancela a Venda => Cancela recebível automaticamente
        $saleService->cancel($sale, $this->admin);

        $this->assertDatabaseHas('receivables', [
            'source_type' => get_class($sale),
            'source_id' => $sale->id,
            'status' => PaymentStatus::Cancelled->value,
        ]);
    }

    /**
     * Teste de faturamento automático e cancelamento integrado em Compras.
     */
    public function test_purchase_order_financial_integration_and_cancellation(): void
    {
        $po = PurchaseOrder::create([
            'code' => 'COMP-999',
            'supplier_id' => $this->supplier->id,
            'total_amount' => 400.00,
            'status' => PurchaseOrderStatus::Draft,
            'created_by' => $this->admin->id,
        ]);

        $po->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_cost' => 40.00,
            'description' => $this->product->name,
            'unit' => 'UN',
            'total_cost' => 400.00,
        ]);

        $poService = app(PurchaseOrderService::class);

        // 1. Envia pedido
        $poService->order($po, $this->admin);

        // 2. Cria conferência de recebimento
        $conf = $poService->createConference($po, $this->admin);

        // 3. Finaliza a conferência => Status vira Received => Gera Contas a Pagar
        $poService->completeConference($conf, $this->admin, [
            $this->product->id => 10.0,
        ]);

        $this->assertEquals(PurchaseOrderStatus::Received, $po->fresh()->status);

        $this->assertDatabaseHas('payables', [
            'source_type' => get_class($po),
            'source_id' => $po->id,
            'status' => PaymentStatus::Pending->value,
            'total_amount' => 400.00,
        ]);
    }
}
