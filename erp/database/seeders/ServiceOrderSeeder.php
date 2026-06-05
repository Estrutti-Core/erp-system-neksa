<?php

namespace Database\Seeders;

use App\Enums\ServiceOrderPriority;
use App\Models\Client;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderHistory;
use App\Models\ServiceOrderItem;
use App\Models\ServiceOrderStatus;
use App\Models\ServiceOrderStatusHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ServiceOrderSeeder extends Seeder
{
    public function run(): void
    {
        $clients    = Client::with('addresses')->get();
        $technicians = User::role('technician')->get();
        $admin       = User::role('admin')->first();

        if ($clients->isEmpty() || $technicians->isEmpty()) {
            return;
        }

        $descriptions = [
            'Instalação de ar-condicionado split 12.000 BTUs',
            'Manutenção preventiva de sistema de câmeras de segurança',
            'Reparo em quadro elétrico com disjuntores queimados',
            'Instalação de rede elétrica para novos equipamentos',
            'Troca de luminárias por modelos LED',
            'Manutenção de sistema de alarme antifurto',
            'Instalação de tomadas industriais',
            'Reparo em sistema de CFTV com imagem distorcida',
            'Configuração e instalação de rede Wi-Fi empresarial',
            'Instalação de No-break e estabilizador',
            'Manutenção de gerador de energia a diesel',
            'Revisão geral da instalação elétrica predial',
        ];

        $statusesMap = ServiceOrderStatus::all()->keyBy('slug');

        $dbStatuses = [
            $statusesMap['open'],
            $statusesMap['open'],
            $statusesMap['open'],
            $statusesMap['in_route'],
            $statusesMap['in_service'],
            $statusesMap['completed'],
            $statusesMap['completed'],
            $statusesMap['completed'],
            $statusesMap['cancelled'],
        ];

        $priorities = ServiceOrderPriority::cases();

        $year  = now()->year;
        $count = 0;

        foreach ($clients as $client) {
            $address = $client->addresses->first();
            $numOrders = rand(2, 5);

            for ($i = 0; $i < $numOrders; $i++) {
                $count++;
                $status = $dbStatuses[array_rand($dbStatuses)];
                $priority = $priorities[array_rand($priorities)];
                $technician = $technicians->random();
                $scheduledAt = Carbon::now()->subDays(rand(0, 30))->addHours(rand(7, 17));

                $so = ServiceOrder::create([
                    'code'          => sprintf('OS-%s-%05d', $year, $count),
                    'client_id'     => $client->id,
                    'client_address_id' => $address?->id,
                    'technician_id' => $technician->id,
                    'created_by'    => $admin->id,
                    'status_id'     => $status->id,
                    'priority'      => $priority->value,
                    'description'   => $descriptions[array_rand($descriptions)],
                    'scheduled_at'  => $scheduledAt,
                    'started_at'    => in_array($status->slug, ['in_service', 'completed'])
                        ? $scheduledAt->copy()->addMinutes(rand(5, 30)) : null,
                    'completed_at'  => $status->slug === 'completed'
                        ? $scheduledAt->copy()->addHours(rand(1, 3)) : null,
                    'service_amount' => rand(200, 2000),
                    'parts_amount'   => rand(50, 500),
                    'total_amount'   => 0,
                ]);

                $so->total_amount = $so->service_amount + $so->parts_amount;
                $so->save();

                // Itens
                ServiceOrderItem::create([
                    'service_order_id' => $so->id,
                    'type'             => 'service',
                    'description'      => 'Mão de obra técnica especializada',
                    'quantity'         => rand(1, 4),
                    'unit'             => 'hora',
                    'unit_price'       => rand(100, 300),
                    'total_price'      => $so->service_amount,
                ]);

                ServiceOrderItem::create([
                    'service_order_id' => $so->id,
                    'type'             => 'part',
                    'description'      => 'Material e peças utilizadas',
                    'quantity'         => rand(1, 5),
                    'unit'             => 'un',
                    'unit_price'       => rand(20, 150),
                    'total_price'      => $so->parts_amount,
                ]);

                // Histórico de status dedicado
                ServiceOrderStatusHistory::create([
                    'service_order_id' => $so->id,
                    'from_status_id'   => null,
                    'to_status_id'     => $status->id,
                    'changed_by'       => $admin->id,
                    'entered_at'       => $scheduledAt,
                    'left_at'          => $status->slug === 'completed' ? $scheduledAt->copy()->addHours(2) : null,
                    'duration_minutes' => $status->slug === 'completed' ? 120 : null,
                    'notes'            => 'Histórico de status semeado.',
                    'created_at'       => $scheduledAt,
                    'updated_at'       => $scheduledAt,
                ]);

                // Histórico inicial de timeline
                ServiceOrderHistory::create([
                    'service_order_id' => $so->id,
                    'user_id'          => $admin->id,
                    'event'            => 'created',
                    'to_status'        => $status->slug,
                    'description'      => 'Ordem de Serviço criada.',
                    'created_at'       => $scheduledAt->copy()->subDays(rand(1, 5)),
                ]);

                if (! $so->isOpen()) {
                    ServiceOrderHistory::create([
                        'service_order_id' => $so->id,
                        'user_id'          => $admin->id,
                        'event'            => 'technician_assigned',
                        'description'      => "Técnico '{$technician->name}' atribuído à OS.",
                    ]);
                }
            }
        }
    }
}
