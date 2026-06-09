<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Client;
use App\Models\Supplier;
use App\Models\Receivable;
use App\Models\ReceivableInstallment;
use App\Models\Payable;
use App\Models\PayableInstallment;
use App\Models\FinancialEvent;
use App\Models\User;
use App\Enums\PaymentStatus;
use App\Enums\InstallmentStatus;
use App\Enums\PaymentMethod;
use App\Services\FinancialService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancialWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;
    protected Client $client;
    protected Supplier $supplier;
    protected FinancialService $financialService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);

        $this->company = Company::create([
            'name' => 'Neksa Corp',
            'allow_negative_stock' => false,
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

        $this->financialService = app(FinancialService::class);
    }

    /**
     * Testa criação de Receivable e geração de códigos sequenciais.
     */
    public function test_receivable_creation_and_coding(): void
    {
        $receivable = $this->financialService->createReceivable([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'description' => 'Serviço de Manutenção',
            'total_amount' => 300.00,
            'competence_date' => Carbon::today(),
        ], [
            [
                'installment_number' => 1,
                'due_date' => Carbon::today()->addDays(30)->toDateString(),
                'amount' => 300.00,
            ]
        ], $this->admin);

        $this->assertDatabaseHas('receivables', [
            'id' => $receivable->id,
            'total_amount' => 300.00,
            'status' => PaymentStatus::Pending->value,
        ]);

        $this->assertCount(1, $receivable->installments);

        // O código deve conter o ano atual
        $year = now()->year;
        $this->assertStringStartsWith("REC-{$year}-", $receivable->code);

        // Criando outro para validar incremental
        $second = $this->financialService->createReceivable([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'description' => 'Serviço de Manutenção 2',
            'total_amount' => 100.00,
            'competence_date' => Carbon::today(),
        ], [
            [
                'installment_number' => 1,
                'due_date' => Carbon::today()->toDateString(),
                'amount' => 100.00,
            ]
        ], $this->admin);

        // Extrai o sequencial
        $firstParts = explode('-', $receivable->code);
        $secondParts = explode('-', $second->code);
        $this->assertEquals((int)end($firstParts) + 1, (int)end($secondParts));
    }

    /**
     * Testa cálculo dinâmico de Overdue para parcelas.
     */
    public function test_installment_overdue_getter(): void
    {
        $receivable = $this->financialService->createReceivable([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'description' => 'Venda de Equipamento',
            'total_amount' => 150.00,
        ], [
            [
                'installment_number' => 1,
                'due_date' => Carbon::yesterday()->toDateString(), // vencido ontem
                'amount' => 150.00,
            ]
        ], $this->admin);

        $installment = $receivable->installments->first();
        $this->assertEquals('overdue', $installment->status_label);
        $this->assertTrue($installment->isOverdue());
    }

    /**
     * Testa fluxo completo de Baixa Parcial e Quitação.
     */
    public function test_partial_and_full_payment_flow(): void
    {
        $receivable = $this->financialService->createReceivable([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'description' => 'Contrato Anual',
            'total_amount' => 500.00,
        ], [
            [
                'installment_number' => 1,
                'due_date' => Carbon::today()->addDays(10)->toDateString(),
                'amount' => 500.00,
            ]
        ], $this->admin);

        $installment = $receivable->installments->first();

        // 1. Baixa Parcial: R$ 200 de R$ 500
        $this->financialService->payReceivableInstallment(
            $installment,
            200.00,
            PaymentMethod::Pix,
            Carbon::now(),
            0.00, // discount
            0.00, // interest
            $this->admin
        );

        $installment = $installment->fresh();
        $receivable = $receivable->fresh();

        $this->assertEquals(200.00, $installment->paid_amount);
        $this->assertEquals(InstallmentStatus::Pending, $installment->status);
        $this->assertEquals(PaymentStatus::PartiallyPaid, $receivable->status);

        // Verifica que o evento de auditoria foi gerado com tipo partial_payment
        $this->assertDatabaseHas('financial_events', [
            'entity_type' => ReceivableInstallment::class,
            'entity_id' => $installment->id,
            'event_type' => 'partial_payment',
        ]);

        // 2. Baixa Total (restante R$ 300)
        $this->financialService->payReceivableInstallment(
            $installment,
            300.00,
            PaymentMethod::Pix,
            Carbon::now(),
            0.00,
            0.00,
            $this->admin
        );

        $installment = $installment->fresh();
        $receivable = $receivable->fresh();

        $this->assertEquals(500.00, $installment->paid_amount);
        $this->assertEquals(InstallmentStatus::Paid, $installment->status);
        $this->assertEquals(PaymentStatus::Paid, $receivable->status);

        $this->assertDatabaseHas('financial_events', [
            'entity_type' => ReceivableInstallment::class,
            'entity_id' => $installment->id,
            'event_type' => 'full_payment',
        ]);
    }

    /**
     * Testa bloqueio de deleção de títulos financeiros (anti-fraude).
     */
    public function test_blocks_receivable_and_payable_deletion(): void
    {
        $receivable = $this->financialService->createReceivable([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'description' => 'Serviço Avulso',
            'total_amount' => 100.00,
        ], [
            [
                'installment_number' => 1,
                'due_date' => Carbon::today()->toDateString(),
                'amount' => 100.00,
            ]
        ], $this->admin);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Nao e permitido deletar registros financeiros do contas a receber por diretrizes de auditoria. Cancele o titulo se necessario.");

        $receivable->delete();
    }

    /**
     * Testa imutabilidade absoluta dos eventos de auditoria financeira.
     */
    public function test_financial_event_immutability(): void
    {
        $event = FinancialEvent::create([
            'entity_type' => Receivable::class,
            'entity_id' => 1,
            'event_type' => 'created',
            'old_data' => null,
            'new_data' => ['test' => true],
            'user_id' => $this->admin->id,
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('financial_events', ['id' => $event->id]);

        // Tenta atualizar
        try {
            $event->update(['event_type' => 'updated']);
            $this->fail("Deveria ter lançado exceção ao atualizar log de auditoria financeira.");
        } catch (\Exception $e) {
            $this->assertStringContainsString("Nao e permitido alterar logs de auditoria financeira.", $e->getMessage());
        }

        // Tenta deletar
        try {
            $event->delete();
            $this->fail("Deveria ter lançado exceção ao deletar log de auditoria financeira.");
        } catch (\Exception $e) {
            $this->assertStringContainsString("Nao e permitido excluir logs de auditoria financeira.", $e->getMessage());
        }
    }
}
