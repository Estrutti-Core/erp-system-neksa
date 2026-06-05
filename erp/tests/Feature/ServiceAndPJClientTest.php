<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Cnae;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceAndPJClientTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed roles and permissions
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        
        $this->actingAs($this->admin);
    }

    public function test_can_create_service(): void
    {
        $response = $this->post(route('services.store'), [
            'name'                   => 'Instalação de Cabo de Rede',
            'sku'                    => 'SERV-INST-CABO',
            'description'            => 'Lançamento e conectorização de cabo CAT6',
            'price'                  => '150,00',
            'cfop'                   => '5933',
            'cst'                    => '01',
            'iss_rate'               => '5,00',
            'iss_withheld'           => '1',
            'pis_retention_rate'     => '0,65',
            'cofins_retention_rate'  => '3,00',
            'csll_retention_rate'    => '1,00',
            'inss_retention_rate'    => '1,50',
            'municipal_service_code' => '14.01',
            'is_active'              => '1',
        ]);

        $response->assertRedirect(route('services.index'));
        $this->assertDatabaseHas('services', [
            'sku'                    => 'SERV-INST-CABO',
            'price'                  => 150.00,
            'iss_rate'               => 5.00,
            'iss_withheld'           => true,
            'pis_retention_rate'     => 0.65,
            'cofins_retention_rate'  => 3.00,
            'csll_retention_rate'    => 1.00,
            'inss_retention_rate'    => 1.50,
        ]);
    }

    public function test_can_update_service(): void
    {
        $service = Service::create([
            'name'                   => 'Instalação de Câmeras',
            'sku'                    => 'SERV-CAM',
            'price'                  => 100.00,
            'iss_rate'               => 2.00,
            'pis_retention_rate'     => 0.00,
            'cofins_retention_rate'  => 0.00,
            'csll_retention_rate'    => 0.00,
            'inss_retention_rate'    => 0.00,
        ]);

        $response = $this->put(route('services.update', $service), [
            'name'                   => 'Instalação de Câmeras Premium',
            'sku'                    => 'SERV-CAM-PRM',
            'price'                  => '250,50',
            'iss_rate'               => '4,50',
            'pis_retention_rate'     => '0,65',
            'cofins_retention_rate'  => '3,00',
            'csll_retention_rate'    => '1,00',
            'inss_retention_rate'    => '1,50',
            'is_active'              => '1',
        ]);

        $response->assertRedirect(route('services.index'));
        $this->assertDatabaseHas('services', [
            'id'    => $service->id,
            'name'  => 'Instalação de Câmeras Premium',
            'sku'   => 'SERV-CAM-PRM',
            'price' => 250.50,
        ]);
    }

    public function test_can_search_services_autocomplete(): void
    {
        Service::create([
            'name'                   => 'Configuração de Roteador',
            'sku'                    => 'SERV-CONF-ROT',
            'price'                  => 120.00,
            'iss_rate'               => 2.00,
            'pis_retention_rate'     => 0.00,
            'cofins_retention_rate'  => 0.00,
            'csll_retention_rate'    => 0.00,
            'inss_retention_rate'    => 0.00,
        ]);

        $response = $this->getJson(route('services.search') . '?q=Roteador');

        $response->assertOk()
            ->assertJsonFragment([
                'name' => 'Configuração de Roteador',
                'sku' => 'SERV-CONF-ROT',
                'sale_price' => '120,00',
            ]);
    }

    public function test_can_create_pj_client_with_cnaes_and_contacts(): void
    {
        $response = $this->post(route('clients.store'), [
            'name'                   => 'Empresa Teste LTDA',
            'document'               => '12.345.678/0001-90',
            'document_type'          => 'cnpj',
            'phone'                  => '(11) 5555-5555',
            'email'                  => 'financeiro@empresa.com',
            'social_name'            => 'Empresa Teste Razao Social LTDA',
            'trade_name'             => 'Empresa Teste Fantasia',
            'sector'                 => 'Tecnologia da Informação',
            'opening_date'           => '2020-01-01',
            'capital_social'         => '100.000,00',
            'company_size'           => 'ME',
            'legal_nature'           => 'Sociedade Empresária Limitada',
            'registration_status'    => 'ATIVA',

            // CNAEs
            'main_cnae_code'         => '62.02-3-00',
            'main_cnae_description'  => 'Desenvolvimento de programas de computador sob encomenda',
            'secondary_cnaes'        => [
                ['code' => '62.03-1-00', 'description' => 'Desenvolvimento de programas de computador customizáveis'],
                ['code' => '62.09-1-00', 'description' => 'Suporte técnico, manutenção e outros serviços em tecnologia'],
            ],

            // Contatos
            'contacts'               => [
                [
                    'name'                => 'João do Suporte',
                    'email'               => 'joao@empresa.com',
                    'phone'               => '(11) 98888-7777',
                    'whatsapp'            => '(11) 98888-7777',
                    'role'                => 'Supervisor de TI',
                    'is_primary'          => '1',
                    'is_phone_blocked'    => '0',
                    'is_whatsapp_blocked' => '0',
                    'is_email_blocked'    => '1',
                ],
                [
                    'name'                => 'Maria do Financeiro',
                    'email'               => 'maria@empresa.com',
                    'phone'               => '(11) 97777-6666',
                    'whatsapp'            => '',
                    'role'                => 'Analista Financeiro',
                    'is_primary'          => '0',
                    'is_phone_blocked'    => '1',
                    'is_whatsapp_blocked' => '1',
                    'is_email_blocked' => '0',
                ]
            ],

            // Endereço
            'zip_code'               => '01311-200',
            'street'                 => 'Avenida Paulista',
            'number'                 => '1000',
            'complement'             => 'Andar 10',
            'neighborhood'           => 'Bela Vista',
            'city'                   => 'São Paulo',
            'state'                  => 'SP',
        ]);

        $response->assertRedirect(route('clients.index'));

        // Verifica o Cliente
        $client = Client::where('document', '12345678000190')->first();
        $this->assertNotNull($client);
        $this->assertEquals('Empresa Teste LTDA', $client->name);
        $this->assertEquals('Empresa Teste Razao Social LTDA', $client->social_name);
        $this->assertEquals('100000.00', $client->capital_social);

        // Verifica os CNAEs
        $this->assertDatabaseHas('cnaes', ['code' => '6202300']);
        $this->assertDatabaseHas('cnaes', ['code' => '6203100']);
        $this->assertDatabaseHas('cnaes', ['code' => '6209100']);

        // Verifica os vínculos na tabela pivô
        $this->assertDatabaseHas('client_cnaes', [
            'client_id' => $client->id,
            'is_primary' => true,
        ]);

        // Verifica os Contatos
        $this->assertDatabaseHas('client_contacts', [
            'client_id'        => $client->id,
            'name'             => 'João do Suporte',
            'role'             => 'Supervisor de TI',
            'is_primary'       => true,
            'is_email_blocked' => true,
        ]);

        $this->assertDatabaseHas('client_contacts', [
            'client_id'        => $client->id,
            'name'             => 'Maria do Financeiro',
            'is_primary'       => false,
            'is_phone_blocked' => true,
            'is_whatsapp_blocked' => true,
            'is_email_blocked' => false,
        ]);
    }

    public function test_can_lookup_cnpj_via_ajax(): void
    {
        // 00000000000191 é o CNPJ do Banco do Brasil que mockamos no CnpjaService
        $response = $this->getJson(route('clients.cnpj-lookup', '00000000000191'));

        $response->assertOk()
            ->assertJsonFragment([
                'document' => '00000000000191',
                'social_name' => 'NEKSA SOLUCOES TECNOLOGICAS LTDA',
                'trade_name' => 'NEKSA ERP',
            ]);
    }
    public function test_can_create_and_update_client_with_equipments(): void
    {
        // 1. Create client with equipments
        $response = $this->post(route('clients.store'), [
            'name'                   => 'Cliente Equipamentos S.A.',
            'document'               => '99.888.777/0001-66',
            'document_type'          => 'cnpj',
            'phone'                  => '(11) 4444-4444',
            'email'                  => 'equip@cliente.com',
            'social_name'            => 'Cliente Equipamentos SA',
            'street'                 => 'Rua Teste',
            'city'                   => 'São Paulo',
            'state'                  => 'SP',

            'equipments' => [
                [
                    'name'          => 'Servidor Rack Dell R740',
                    'brand'         => 'Dell',
                    'model'         => 'PowerEdge R740',
                    'serial_number' => 'S12345XYZ',
                    'notes'         => 'Servidor principal do datacenter',
                ],
                [
                    'name'          => 'Ar Condicionado LG 24k BTU',
                    'brand'         => 'LG',
                    'model'         => 'Split Inverter',
                    'serial_number' => 'LG-9988-77',
                    'notes'         => 'Equipamento da sala de TI',
                ]
            ]
        ]);

        $response->assertRedirect(route('clients.index'));

        $client = Client::where('document', '99888777000166')->first();
        $this->assertNotNull($client);
        
        $this->assertCount(2, $client->equipments);
        $this->assertDatabaseHas('client_equipments', [
            'client_id'     => $client->id,
            'name'          => 'Servidor Rack Dell R740',
            'serial_number' => 'S12345XYZ',
        ]);

        // 2. Update client: update one equipment, delete one, add a new one
        $eqDell = $client->equipments()->where('name', 'like', '%Dell%')->first();

        $response = $this->put(route('clients.update', $client), [
            'name'                   => 'Cliente Equipamentos S.A. Alterado',
            'document'               => '99.888.777/0001-66',
            'document_type'          => 'cnpj',
            'phone'                  => '(11) 4444-4444',
            'email'                  => 'equip@cliente.com',
            'social_name'            => 'Cliente Equipamentos SA',
            'street'                 => 'Rua Teste',
            'city'                   => 'São Paulo',
            'state'                  => 'SP',

            'equipments' => [
                [
                    'id'            => $eqDell->id,
                    'name'          => 'Servidor Rack Dell R740 V2',
                    'brand'         => 'Dell Enterprise',
                    'model'         => 'PowerEdge R740',
                    'serial_number' => 'S12345XYZ-V2',
                    'notes'         => 'Servidor principal atualizado',
                ],
                [
                    'name'          => 'Switch Cisco 24p POE',
                    'brand'         => 'Cisco',
                    'model'         => 'Catalyst 2960L',
                    'serial_number' => 'CS-4433-22',
                    'notes'         => 'Switch de distribuição',
                ]
            ]
        ]);

        $response->assertRedirect(route('clients.show', $client));

        $client->refresh();
        $this->assertCount(2, $client->equipments);
        
        // Dell should be updated
        $this->assertDatabaseHas('client_equipments', [
            'id'            => $eqDell->id,
            'name'          => 'Servidor Rack Dell R740 V2',
            'brand'         => 'Dell Enterprise',
            'serial_number' => 'S12345XYZ-V2',
        ]);

        // Cisco should be added
        $this->assertDatabaseHas('client_equipments', [
            'client_id'     => $client->id,
            'name'          => 'Switch Cisco 24p POE',
            'serial_number' => 'CS-4433-22',
        ]);

        // LG should be deleted (removed from payload)
        $this->assertSoftDeleted('client_equipments', [
            'name' => 'Ar Condicionado LG 24k BTU',
        ]);
    }
}

