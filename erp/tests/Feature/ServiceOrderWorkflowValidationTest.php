<?php

namespace Tests\Feature;

use App\Models\ChecklistTemplate;
use App\Models\ChecklistQuestion;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderCheckin;
use App\Models\ServiceOrderStatus;
use App\Models\User;
use App\Services\ServiceOrderChecklistService;
use App\Services\ServiceOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServiceOrderWorkflowValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private ServiceOrderStatus $openStatus;
    private ServiceOrderStatus $completedStatus;
    private Client $client;
    private ClientAddress $address;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin',      'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'technician', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'operator',   'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->openStatus = ServiceOrderStatus::create([
            'name' => 'Aberto', 'slug' => 'open_wf',
            'color' => 'blue', 'order' => 1,
        ]);
        $this->completedStatus = ServiceOrderStatus::create([
            'name' => 'Concluído', 'slug' => 'completed_wf',
            'color' => 'green', 'order' => 2,
            'is_completed_state' => true,
        ]);
        $this->openStatus->allowedTransitions()->sync([$this->completedStatus->id]);

        $this->client  = Client::create(['name' => 'Cliente Teste WF', 'document' => '11122233344', 'is_active' => true]);
        $this->address = ClientAddress::create([
            'client_id' => $this->client->id, 'street' => 'Rua A',
            'number' => '1', 'neighborhood' => 'B', 'city' => 'C', 'state' => 'SP', 'zip_code' => '01001-000',
        ]);
    }

    private function makeOS(): ServiceOrder
    {
        return ServiceOrder::create([
            'code'              => 'OS-WF-' . uniqid(),
            'client_id'         => $this->client->id,
            'client_address_id' => $this->address->id,
            'status_id'         => $this->openStatus->id,
            'created_by'        => $this->admin->id,
            'description'       => 'Teste workflow',
        ]);
    }

    private function satisfyPrerequisites(ServiceOrder $os): void
    {
        ServiceOrderCheckin::create([
            'service_order_id' => $os->id, 'user_id' => $this->admin->id,
            'type' => 'checkin', 'latitude' => -23.5, 'longitude' => -46.6, 'checked_at' => now(),
        ]);
        $os->signature()->create([
            'signer_name' => 'Fulano', 'path' => 'sigs/test.png', 'disk' => 'public', 'signed_at' => now(),
        ]);
    }

    // ── Testes de bloqueio de conclusão ───────────────────────────────────────

    public function test_blocks_completion_without_checkin(): void
    {
        $os  = $this->makeOS();
        $svc = new ServiceOrderService(new ServiceOrderChecklistService());
        $os->signature()->create(['signer_name'=>'X','path'=>'p.png','disk'=>'public','signed_at'=>now()]);

        $this->expectException(ValidationException::class);
        $svc->changeStatus($os, $this->completedStatus, $this->admin);
    }

    public function test_blocks_completion_without_signature(): void
    {
        $os  = $this->makeOS();
        $svc = new ServiceOrderService(new ServiceOrderChecklistService());
        ServiceOrderCheckin::create([
            'service_order_id'=>$os->id,'user_id'=>$this->admin->id,
            'type'=>'checkin','checked_at'=>now(),
        ]);

        $this->expectException(ValidationException::class);
        $svc->changeStatus($os, $this->completedStatus, $this->admin);
    }

    public function test_blocks_completion_with_unfilled_active_checklist(): void
    {
        $template = ChecklistTemplate::create(['name' => 'Template Bloqueio']);
        ChecklistQuestion::create([
            'checklist_template_id' => $template->id,
            'question_text' => 'Pergunta obrigatória?',
            'question_type' => 'text',
            'is_required'   => true,
            'order'         => 0,
        ]);

        $service = Service::create(['name' => 'Srv WF', 'sku' => 'SRV-WF', 'price' => 100]);
        $service->checklistTemplates()->attach($template->id);

        $os = $this->makeOS();
        $os->items()->create([
            'type' => 'service', 'service_id' => $service->id,
            'description' => 'Srv WF', 'quantity' => 1, 'unit' => 'un',
            'unit_price' => 100, 'total_price' => 100,
        ]);

        (new ServiceOrderChecklistService())->syncRequiredChecklists($os);
        $this->satisfyPrerequisites($os);

        $svc = new ServiceOrderService(new ServiceOrderChecklistService());
        $this->expectException(ValidationException::class);
        $svc->changeStatus($os, $this->completedStatus, $this->admin);
    }

    public function test_completes_successfully_when_all_prerequisites_met(): void
    {
        $os  = $this->makeOS();
        $svc = new ServiceOrderService(new ServiceOrderChecklistService());
        $this->satisfyPrerequisites($os);

        $svc->changeStatus($os, $this->completedStatus, $this->admin, 'Tudo ok');
        $this->assertEquals($this->completedStatus->id, $os->fresh()->status_id);
    }

    // ── Testes de snapshot ────────────────────────────────────────────────────

    public function test_checklist_snapshot_is_immutable(): void
    {
        $template = ChecklistTemplate::create(['name' => 'Template Original']);
        $question = ChecklistQuestion::create([
            'checklist_template_id' => $template->id,
            'question_text' => 'Pergunta Original',
            'question_type' => 'text', 'is_required' => true, 'order' => 0,
        ]);

        $service = Service::create(['name' => 'Srv Snap', 'sku' => 'SRV-SNAP', 'price' => 100]);
        $service->checklistTemplates()->attach($template->id);

        $os = $this->makeOS();
        $os->items()->create([
            'type' => 'service', 'service_id' => $service->id,
            'description' => 'Srv Snap', 'quantity' => 1, 'unit' => 'un',
            'unit_price' => 100, 'total_price' => 100,
        ]);

        (new ServiceOrderChecklistService())->syncRequiredChecklists($os);

        // Verificar que o snapshot foi criado
        $checklist = $os->checklists()->first();
        $this->assertNotNull($checklist);
        $instancedQ = $checklist->instancedQuestions()->first();
        $this->assertEquals('Pergunta Original', $instancedQ->question_text);

        // Alterar o template original
        $question->update(['question_text' => 'Pergunta Modificada']);

        // Snapshot deve permanecer intacto
        $this->assertEquals('Pergunta Original', $instancedQ->fresh()->question_text);
    }

    // ── Testes de preservação de evidências ──────────────────────────────────

    public function test_filled_checklists_are_preserved_on_service_removal(): void
    {
        $template = ChecklistTemplate::create(['name' => 'Template Evidência']);
        $service  = Service::create(['name' => 'Srv Evid', 'sku' => 'SRV-EVID', 'price' => 100]);
        $service->checklistTemplates()->attach($template->id);

        $os   = $this->makeOS();
        $item = $os->items()->create([
            'type' => 'service', 'service_id' => $service->id,
            'description' => 'Srv Evid', 'quantity' => 1, 'unit' => 'un',
            'unit_price' => 100, 'total_price' => 100,
        ]);

        $checklistSvc = new ServiceOrderChecklistService();
        $checklistSvc->syncRequiredChecklists($os);

        // Simular preenchimento
        $checklist = $os->checklists()->first();
        $checklist->update(['filled_at' => now(), 'filled_by' => $this->admin->id]);

        // Remover o item de serviço e ressincronizar
        $item->delete();
        $checklistSvc->syncRequiredChecklists($os);

        // Checklist preenchido deve estar inativo, não deletado
        $checklist->refresh();
        $this->assertTrue($checklist->is_inactive);
        $this->assertNotNull($checklist->filled_at); // evidência preservada
    }

    // ── Testes de tipos de pergunta ───────────────────────────────────────────

    public function test_checklist_supports_all_six_question_types(): void
    {
        $template = ChecklistTemplate::create(['name' => 'Template Tipos']);
        $types    = ['text', 'checkbox', 'select', 'photo', 'drawing', 'label'];

        foreach ($types as $i => $type) {
            ChecklistQuestion::create([
                'checklist_template_id' => $template->id,
                'question_text'         => "Pergunta {$type}",
                'question_type'         => $type,
                'options_json'          => $type === 'select' ? ['Op1', 'Op2'] : null,
                'is_required'           => false,
                'order'                 => $i,
            ]);
        }

        $this->assertEquals(6, $template->questions()->count());

        $service = Service::create(['name' => 'Srv Tipos', 'sku' => 'SRV-TIPOS', 'price' => 100]);
        $service->checklistTemplates()->attach($template->id);

        $os = $this->makeOS();
        $os->items()->create([
            'type' => 'service', 'service_id' => $service->id,
            'description' => 'Srv Tipos', 'quantity' => 1, 'unit' => 'un',
            'unit_price' => 100, 'total_price' => 100,
        ]);

        (new ServiceOrderChecklistService())->syncRequiredChecklists($os);

        $checklist       = $os->checklists()->first();
        $instancedTypes  = $checklist->instancedQuestions()->pluck('question_type')->toArray();

        foreach ($types as $type) {
            $this->assertContains($type, $instancedTypes);
        }

        // Verificar snapshot de options_json para select
        $selectQ = $checklist->instancedQuestions()->where('question_type', 'select')->first();
        $this->assertEquals(['Op1', 'Op2'], $selectQ->options_json);
    }
}
