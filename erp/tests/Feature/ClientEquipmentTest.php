<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientEquipment;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientEquipmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and permissions
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->actingAs($this->admin);

        $this->client = Client::create([
            'name'          => 'Cliente de Teste',
            'document'      => '12345678909',
            'document_type' => 'cpf',
            'phone'         => '11988887777',
            'email'         => 'teste@cliente.com',
            'is_active'     => true,
        ]);
    }

    public function test_can_create_client_equipment(): void
    {
        $response = $this->post(route('clients.equipments.store', $this->client), [
            'name'          => 'Ar Condicionado Split 12k BTU',
            'brand'         => 'Daikin',
            'model'         => 'Eco-Split',
            'serial_number' => 'DK123456789',
            'notes'         => 'Localizado na recepção principal.',
        ]);

        $response->assertRedirect(route('clients.show', $this->client));
        $this->assertDatabaseHas('client_equipments', [
            'client_id'     => $this->client->id,
            'name'          => 'Ar Condicionado Split 12k BTU',
            'brand'         => 'Daikin',
            'model'         => 'Eco-Split',
            'serial_number' => 'DK123456789',
            'notes'         => 'Localizado na recepção principal.',
        ]);
    }

    public function test_can_update_client_equipment(): void
    {
        $equipment = ClientEquipment::create([
            'client_id'     => $this->client->id,
            'name'          => 'Notebook Dell Vostro',
            'brand'         => 'Dell',
            'model'         => 'Vostro 3400',
            'serial_number' => 'DELL-9876',
            'notes'         => 'Notebook da recepção.',
        ]);

        $response = $this->put(route('equipments.update', $equipment), [
            'name'          => 'Notebook Dell Vostro Atualizado',
            'brand'         => 'Dell Corporation',
            'model'         => 'Vostro 3400 Premium',
            'serial_number' => 'DELL-9876-A',
            'notes'         => 'Com upgrade de memória.',
        ]);

        $response->assertRedirect(route('clients.show', $this->client));
        $this->assertDatabaseHas('client_equipments', [
            'id'            => $equipment->id,
            'name'          => 'Notebook Dell Vostro Atualizado',
            'brand'         => 'Dell Corporation',
            'model'         => 'Vostro 3400 Premium',
            'serial_number' => 'DELL-9876-A',
        ]);
    }

    public function test_can_delete_client_equipment(): void
    {
        $equipment = ClientEquipment::create([
            'client_id' => $this->client->id,
            'name'      => 'Impressora Laser HP',
        ]);

        $response = $this->delete(route('equipments.destroy', $equipment));

        $response->assertRedirect(route('clients.show', $this->client));
        $this->assertSoftDeleted('client_equipments', [
            'id' => $equipment->id,
        ]);
    }

    public function test_can_list_client_equipments_json(): void
    {
        $equipment = ClientEquipment::create([
            'client_id'     => $this->client->id,
            'name'          => 'Roteador MikroTik',
            'brand'         => 'MikroTik',
            'model'         => 'hEX lite',
            'serial_number' => 'MK-777',
        ]);

        $response = $this->getJson(route('clients.equipments.json', $this->client));

        $response->assertOk()
            ->assertJsonFragment([
                'id'            => $equipment->id,
                'name'          => 'Roteador MikroTik',
                'brand'         => 'MikroTik',
                'model'         => 'hEX lite',
                'serial_number' => 'MK-777',
            ]);
    }

    public function test_can_create_service_order_with_equipment(): void
    {
        $equipment = ClientEquipment::create([
            'client_id' => $this->client->id,
            'name'      => 'Servidor PowerEdge',
        ]);

        $response = $this->post(route('service-orders.store'), [
            'client_id'    => $this->client->id,
            'equipment_id' => $equipment->id,
            'priority'     => 'high',
            'description'  => 'Servidor não liga e apita no boot.',
        ]);

        $this->assertDatabaseHas('service_orders', [
            'client_id'    => $this->client->id,
            'equipment_id' => $equipment->id,
            'priority'     => 'high',
            'description'  => 'Servidor não liga e apita no boot.',
        ]);
    }

    public function test_can_duplicate_service_order(): void
    {
        $equipment = ClientEquipment::create([
            'client_id' => $this->client->id,
            'name'      => 'Servidor PowerEdge',
        ]);

        $completedStatus = ServiceOrderStatus::where('slug', 'completed')->firstOrFail();

        $original = ServiceOrder::create([
            'code'              => 'OS-2024-00005',
            'client_id'         => $this->client->id,
            'equipment_id'      => $equipment->id,
            'created_by'        => $this->admin->id,
            'status_id'         => $completedStatus->id,
            'priority'          => 'high',
            'description'       => 'Servidor com problemas de hardware.',
            'services_performed'=> 'Troca de memória RAM realizada.',
            'total_amount'      => 350.00,
            'service_amount'    => 200.00,
            'parts_amount'      => 150.00,
        ]);

        // Add an item to the original
        $original->items()->create([
            'type'        => 'part',
            'description' => 'Pente RAM 16GB',
            'quantity'    => 1,
            'unit'        => 'UN',
            'unit_price'  => 150.00,
            'total_price' => 150.00,
        ]);

        $response = $this->post(route('service-orders.duplicate', $original));

        // Should redirect to the new service order show view
        $newOrder = ServiceOrder::where('code', '!=', 'OS-2024-00005')->first();
        $this->assertNotNull($newOrder);
        
        $response->assertRedirect(route('service-orders.show', $newOrder));

        // Check new order properties
        $this->assertEquals('open', $newOrder->status->slug);
        $this->assertEquals('high', $newOrder->priority->value);
        $this->assertEquals($equipment->id, $newOrder->equipment_id);
        $this->assertNull($newOrder->services_performed);
        $this->assertNull($newOrder->started_at);
        $this->assertNull($newOrder->completed_at);
        
        // Items must be duplicated
        $this->assertCount(1, $newOrder->items);
        $this->assertEquals('Pente RAM 16GB', $newOrder->items->first()->description);
    }

    public function test_service_order_creation_resolves_primary_address(): void
    {
        $address = \App\Models\ClientAddress::create([
            'client_id'    => $this->client->id,
            'label'        => 'Escritório',
            'street'       => 'Av. Paulista',
            'number'       => '1000',
            'neighborhood' => 'Bela Vista',
            'city'         => 'São Paulo',
            'state'        => 'SP',
            'zip_code'     => '01310-100',
            'is_primary'   => true,
        ]);

        $response = $this->post(route('service-orders.store'), [
            'client_id'    => $this->client->id,
            'priority'     => 'high',
            'description'  => 'Descrição de teste com tamanho adequado e caracteres.',
        ]);

        $os = ServiceOrder::where('client_id', $this->client->id)->first();
        $this->assertNotNull($os);
        $this->assertEquals($address->id, $os->client_address_id);
    }
}

