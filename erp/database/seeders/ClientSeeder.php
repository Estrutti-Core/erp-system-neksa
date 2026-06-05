<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\ClientAddress;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        $clients = [
            ['name' => 'Supermercado Bom Preço Ltda', 'doc' => '12.345.678/0001-99', 'type' => 'cnpj', 'phone' => '(11) 3333-1001', 'email' => 'contato@bompreco.com.br',
             'street' => 'Av. Paulista', 'number' => '1500', 'neighborhood' => 'Bela Vista', 'city' => 'São Paulo', 'state' => 'SP', 'zip' => '01310-100', 'lat' => -23.5614, 'lng' => -46.6558],

            ['name' => 'Roberto Fernandes', 'doc' => '123.456.789-00', 'type' => 'cpf', 'phone' => '(11) 99876-5432', 'email' => 'roberto@email.com',
             'street' => 'Rua das Flores', 'number' => '42', 'neighborhood' => 'Jardim América', 'city' => 'São Paulo', 'state' => 'SP', 'zip' => '01435-010', 'lat' => -23.5668, 'lng' => -46.6747],

            ['name' => 'Clínica Saúde Total', 'doc' => '98.765.432/0001-10', 'type' => 'cnpj', 'phone' => '(11) 3201-5555', 'email' => 'recepcao@saudetotal.com.br',
             'street' => 'Rua Vergueiro', 'number' => '234', 'neighborhood' => 'Liberdade', 'city' => 'São Paulo', 'state' => 'SP', 'zip' => '01504-000', 'lat' => -23.5734, 'lng' => -46.6354],

            ['name' => 'Construtora Alves & Silva', 'doc' => '55.444.333/0001-22', 'type' => 'cnpj', 'phone' => '(11) 3456-7890', 'email' => 'obras@alvessilva.com.br',
             'street' => 'Av. Brigadeiro Faria Lima', 'number' => '3477', 'neighborhood' => 'Itaim Bibi', 'city' => 'São Paulo', 'state' => 'SP', 'zip' => '04538-133', 'lat' => -23.5780, 'lng' => -46.6868],

            ['name' => 'Padaria Pão Quente', 'doc' => '11.222.333/0001-44', 'type' => 'cnpj', 'phone' => '(11) 2223-4455', 'email' => 'contato@paoquente.com.br',
             'street' => 'Rua Oscar Freire', 'number' => '900', 'neighborhood' => 'Cerqueira César', 'city' => 'São Paulo', 'state' => 'SP', 'zip' => '01426-001', 'lat' => -23.5604, 'lng' => -46.6684],

            ['name' => 'Ana Carolina Martins', 'doc' => '987.654.321-55', 'type' => 'cpf', 'phone' => '(11) 97654-3210', 'email' => 'ana.martins@gmail.com',
             'street' => 'Rua Tutóia', 'number' => '321', 'neighborhood' => 'Paraíso', 'city' => 'São Paulo', 'state' => 'SP', 'zip' => '04007-006', 'lat' => -23.5815, 'lng' => -46.6485],

            ['name' => 'Hotel Grand Palace', 'doc' => '77.888.999/0001-33', 'type' => 'cnpj', 'phone' => '(11) 3099-8800', 'email' => 'manutencao@grandpalace.com.br',
             'street' => 'Rua Augusta', 'number' => '700', 'neighborhood' => 'Consolação', 'city' => 'São Paulo', 'state' => 'SP', 'zip' => '01305-000', 'lat' => -23.5509, 'lng' => -46.6560],

            ['name' => 'Escola Municipal São João', 'doc' => '33.444.555/0001-66', 'type' => 'cnpj', 'phone' => '(11) 2010-5050', 'email' => 'diretor@escolasaojoao.edu.br',
             'street' => 'Av. Rangel Pestana', 'number' => '1234', 'neighborhood' => 'Brás', 'city' => 'São Paulo', 'state' => 'SP', 'zip' => '03001-000', 'lat' => -23.5447, 'lng' => -46.6179],

            ['name' => 'Farmácia Popular São Paulo', 'doc' => '22.333.444/0001-77', 'type' => 'cnpj', 'phone' => '(11) 3344-5566', 'email' => 'gerencia@farmaciasp.com.br',
             'street' => 'Av. São João', 'number' => '456', 'neighborhood' => 'República', 'city' => 'São Paulo', 'state' => 'SP', 'zip' => '01035-000', 'lat' => -23.5430, 'lng' => -46.6453],

            ['name' => 'Marcelo Augusto Pereira', 'doc' => '456.123.789-00', 'type' => 'cpf', 'phone' => '(11) 95555-1234', 'email' => 'marcelo.pereira@outlook.com',
             'street' => 'Rua Jaguaribe', 'number' => '55', 'neighborhood' => 'Vila Buarque', 'city' => 'São Paulo', 'state' => 'SP', 'zip' => '01224-001', 'lat' => -23.5381, 'lng' => -46.6497],

            ['name' => 'Restaurante Sabor do Sul', 'doc' => '44.555.666/0001-88', 'type' => 'cnpj', 'phone' => '(11) 2911-3344', 'email' => 'contato@sabordosul.com.br',
             'street' => 'Rua Boa Vista', 'number' => '200', 'neighborhood' => 'Centro', 'city' => 'São Paulo', 'state' => 'SP', 'zip' => '01014-000', 'lat' => -23.5465, 'lng' => -46.6335],

            ['name' => 'Condomínio Edifício Horizonte', 'doc' => '66.777.888/0001-55', 'type' => 'cnpj', 'phone' => '(11) 3878-2211', 'email' => 'sindico@edhorizonte.com.br',
             'street' => 'Av. Ibirapuera', 'number' => '900', 'neighborhood' => 'Moema', 'city' => 'São Paulo', 'state' => 'SP', 'zip' => '04029-000', 'lat' => -23.5997, 'lng' => -46.6641],
        ];

        foreach ($clients as $data) {
            $client = Client::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'          => $data['name'],
                    'document'      => preg_replace('/\D/', '', $data['doc']),
                    'document_type' => $data['type'],
                    'phone'         => $data['phone'],
                    'is_active'     => true,
                ]
            );

            ClientAddress::firstOrCreate(
                ['client_id' => $client->id, 'is_primary' => true],
                [
                    'label'        => 'Principal',
                    'street'       => $data['street'],
                    'number'       => $data['number'],
                    'neighborhood' => $data['neighborhood'],
                    'city'         => $data['city'],
                    'state'        => $data['state'],
                    'zip_code'     => $data['zip'],
                    'latitude'     => $data['lat'],
                    'longitude'    => $data['lng'],
                ]
            );
        }
    }
}
