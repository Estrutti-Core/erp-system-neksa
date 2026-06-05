<?php

namespace Tests\Feature;

use App\Events\ServiceOrderSlaExceeded;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderStatus;
use App\Models\ServiceOrderStatusHistory;
use App\Models\User;
use App\Services\ServiceOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ServiceOrderStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected ServiceOrderStatus $openStatus;
    protected ServiceOrderStatus $completedStatus;
    protected ServiceOrderStatus $cancelledStatus;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and permissions
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);

        // Recupera status padrão semeados pelas migrations
        $this->openStatus = ServiceOrderStatus::where('slug', 'open')->firstOrFail();
        $this->completedStatus = ServiceOrderStatus::where('slug', 'completed')->firstOrFail();
        $this->cancelledStatus = ServiceOrderStatus::where('slug', 'cancelled')->firstOrFail();
    }

    /**
     * Testa listagem de status.
     */
    public function test_admin_can_list_statuses(): void
    {
        $response = $this->get(route('settings.statuses.index'));

        $response->assertStatus(200);
        $response->assertSee($this->openStatus->name);
        $response->assertSee($this->completedStatus->name);
    }

    /**
     * Testa criação de novo status com transições.
     */
    public function test_admin_can_create_status_with_transitions(): void
    {
        $response = $this->post(route('settings.statuses.store'), [
            'name' => 'Aguardando Aprovação',
            'color' => 'amber',
            'is_open_state' => '1',
            'expected_time_minutes' => 120,
            'max_stay_minutes' => 1440,
            'allowed_transitions' => [$this->openStatus->id, $this->completedStatus->id],
        ]);

        $response->assertRedirect(route('settings.statuses.index'));
        $this->assertDatabaseHas('service_order_statuses', [
            'slug' => 'aguardando-aprovacao',
            'color' => 'amber',
            'is_open_state' => true,
            'expected_time_minutes' => 120,
            'max_stay_minutes' => 1440,
        ]);

        $newStatus = ServiceOrderStatus::where('slug', 'aguardando-aprovacao')->firstOrFail();
        $this->assertTrue($newStatus->canTransitionTo($this->openStatus));
        $this->assertTrue($newStatus->canTransitionTo($this->completedStatus));
        $this->assertFalse($newStatus->canTransitionTo($this->cancelledStatus));
    }

    /**
     * Testa garantia de apenas um estado concluído.
     */
    public function test_enforces_single_completed_state(): void
    {
        // Cria novo status definido como conclusão
        $response = $this->post(route('settings.statuses.store'), [
            'name' => 'Entregue e Validado',
            'color' => 'green',
            'is_completed_state' => '1',
        ]);

        $response->assertRedirect(route('settings.statuses.index'));

        // Verifica que o novo status virou o concluído
        $newStatus = ServiceOrderStatus::where('slug', 'entregue-e-validado')->firstOrFail();
        $this->assertTrue($newStatus->is_completed_state);

        // E o antigo status 'completed' perdeu a flag
        $this->assertFalse($this->completedStatus->fresh()->is_completed_state);
    }

    /**
     * Testa bloqueio de exclusão de status do sistema.
     */
    public function test_blocks_deleting_system_statuses(): void
    {
        $response = $this->delete(route('settings.statuses.destroy', $this->openStatus));

        $response->assertRedirect();
        $this->assertDatabaseHas('service_order_statuses', ['id' => $this->openStatus->id]);
    }

    /**
     * Testa regras rígidas de transição de status no ServiceOrderService.
     */
    public function test_enforces_strict_status_transitions(): void
    {
        $client = \App\Models\Client::create([
            'name' => 'Client Test',
            'document' => '12345678909',
            'is_active' => true,
        ]);
        $address = \App\Models\ClientAddress::create([
            'client_id' => $client->id,
            'street' => 'Street Name',
            'number' => '123',
            'neighborhood' => 'Neighborhood',
            'city' => 'City',
            'state' => 'SP',
            'zip_code' => '01001-000',
        ]);

        $os = ServiceOrder::create([
            'code'             => 'OS-2026-99999',
            'client_id'        => $client->id,
            'client_address_id'=> $address->id,
            'status_id'        => $this->openStatus->id,
            'created_by'       => $this->admin->id,
            'description'      => 'Test transition',
        ]);

        // Define transições permitidas: 'open' só pode ir para 'completed'
        $this->openStatus->allowedTransitions()->sync([$this->completedStatus->id]);

        $service = new ServiceOrderService();

        // Satisfazer pré-requisitos de conclusão (ADR-008)
        // 1. Nenhum checklist ativo pendente (OS recém-criada sem serviços não tem checklists)
        // 2. Criar check-in obrigatório
        \App\Models\ServiceOrderCheckin::create([
            'service_order_id' => $os->id,
            'user_id'          => $this->admin->id,
            'type'             => 'checkin',
            'latitude'         => -23.5,
            'longitude'        => -46.6,
            'checked_at'       => now(),
        ]);
        // 3. Criar assinatura obrigatória
        $os->signature()->create([
            'signer_name' => 'Cliente Teste',
            'path'        => 'signatures/test.png',
            'disk'        => 'public',
            'signed_at'   => now(),
        ]);

        // Tentar ir de 'open' para 'completed' deve funcionar
        $service->changeStatus($os, $this->completedStatus, $this->admin, 'Finalizando OS');
        $this->assertEquals($this->completedStatus->id, $os->fresh()->status_id);

        // Tentar ir de 'completed' para 'cancelled' (sem transição cadastrada) deve falhar
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->changeStatus($os, $this->cancelledStatus, $this->admin, 'Cancelando do nada');
    }

    /**
     * Testa motor de SLA e disparo do evento ServiceOrderSlaExceeded.
     */
    public function test_sla_checker_command_triggers_event(): void
    {
        Event::fake([ServiceOrderSlaExceeded::class]);

        $client = \App\Models\Client::create([
            'name' => 'Client SLA Test',
            'document' => '12345678909',
            'is_active' => true,
        ]);
        $address = \App\Models\ClientAddress::create([
            'client_id' => $client->id,
            'street' => 'Street Name',
            'number' => '123',
            'neighborhood' => 'Neighborhood',
            'city' => 'City',
            'state' => 'SP',
            'zip_code' => '01001-000',
        ]);

        // Configura status com limite de permanência de 60 minutos
        $this->openStatus->update(['max_stay_minutes' => 60]);

        $os = ServiceOrder::create([
            'code' => 'OS-2026-88888',
            'client_id' => $client->id,
            'client_address_id' => $address->id,
            'status_id' => $this->openStatus->id,
            'created_by' => $this->admin->id,
            'description' => 'Test SLA duration',
        ]);

        // Simula que entrou no status há 90 minutos
        $hist = ServiceOrderStatusHistory::create([
            'service_order_id' => $os->id,
            'from_status_id' => null,
            'to_status_id' => $this->openStatus->id,
            'changed_by' => $this->admin->id,
            'entered_at' => now()->subMinutes(90),
            'sla_alert_sent' => false,
        ]);

        // Executa o comando de console
        $this->artisan('service-orders:check-sla')
            ->expectsOutputToContain('Iniciando verificação de SLA')
            ->expectsOutputToContain('Alertas disparados: 1')
            ->assertExitCode(0);

        // Verifica que o evento foi disparado com o excedente correto (90 - 60 = 30)
        Event::assertDispatched(ServiceOrderSlaExceeded::class, function ($event) use ($os) {
            return $event->serviceOrder->id === $os->id &&
                $event->status->id === $this->openStatus->id &&
                $event->minutesExceeded === 30;
        });

        // Verifica se a flag de alerta foi marcada no histórico
        $history = ServiceOrderStatusHistory::where('service_order_id', $os->id)->whereNull('left_at')->firstOrFail();
        $this->assertTrue($history->sla_alert_sent);
    }
}
