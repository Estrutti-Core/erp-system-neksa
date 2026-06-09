<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Client;
use App\Models\Supplier;
use App\Models\Company;
use App\Models\Receivable;
use App\Models\Payable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialUiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $operator;
    private Company $company;
    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'operator']);

        $this->company = Company::create([
            'id' => 1,
            'name' => 'Neksa ERP',
        ]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->operator = User::factory()->create();
        $this->operator->assignRole('operator');
    }

    /** @test */
    public function guests_are_redirected_to_login()
    {
        $this->get(route('receivables.index'))->assertRedirect(route('login'));
        $this->get(route('payables.index'))->assertRedirect(route('login'));
        $this->get(route('financial.cash-flow'))->assertRedirect(route('login'));
        $this->get(route('financial.audit'))->assertRedirect(route('login'));
    }

    /** @test */
    public function admin_can_access_all_financial_screens()
    {
        $this->actingAs($this->admin);

        $this->get(route('receivables.index'))->assertStatus(200);
        $this->get(route('receivables.create'))->assertStatus(200);
        $this->get(route('payables.index'))->assertStatus(200);
        $this->get(route('payables.create'))->assertStatus(200);
        $this->get(route('financial.cash-flow'))->assertStatus(200);
        $this->get(route('financial.audit'))->assertStatus(200);
    }

    /** @test */
    public function operator_cannot_access_audit_logs()
    {
        $this->actingAs($this->operator);

        $this->get(route('receivables.index'))->assertStatus(200);
        $this->get(route('receivables.create'))->assertStatus(200);
        $this->get(route('financial.cash-flow'))->assertStatus(200);
        
        // Audit logs are admin-only
        $this->get(route('financial.audit'))->assertStatus(403);
    }

    /** @test */
    public function can_store_receivable_manually_via_post()
    {
        $this->actingAs($this->admin);

        $client = Client::create([
            'name' => 'Cliente Teste UI',
            'document' => '12345678909',
            'document_type' => 'cpf',
            'is_active' => true,
        ]);

        $response = $this->post(route('receivables.store'), [
            'client_id' => $client->id,
            'competence_date' => now()->toDateString(),
            'description' => 'Serviços Manuais de Teste',
            'total_amount' => 1500.00,
            'installments' => [
                ['due_date' => now()->toDateString(), 'amount' => 1500.00]
            ]
        ]);

        $response->assertRedirect(route('receivables.index'));
        $this->assertDatabaseHas('receivables', [
            'client_id' => $client->id,
            'total_amount' => 1500.00,
            'description' => 'Serviços Manuais de Teste',
        ]);
    }

    /** @test */
    public function can_store_payable_manually_via_post()
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::create([
            'name' => 'Fornecedor Teste UI',
            'document' => '98765432101',
            'document_type' => 'cnpj',
            'is_active' => true,
        ]);

        $response = $this->post(route('payables.store'), [
            'supplier_id' => $supplier->id,
            'competence_date' => now()->toDateString(),
            'description' => 'Compra de insumos manuais',
            'total_amount' => 850.50,
            'installments' => [
                ['due_date' => now()->toDateString(), 'amount' => 850.50]
            ]
        ]);

        $response->assertRedirect(route('payables.index'));
        $this->assertDatabaseHas('payables', [
            'supplier_id' => $supplier->id,
            'total_amount' => 850.50,
            'description' => 'Compra de insumos manuais',
        ]);
    }
}
