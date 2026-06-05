<?php

namespace App\Console\Commands;

use App\Events\ServiceOrderSlaExceeded;
use App\Models\ServiceOrderStatusHistory;
use Illuminate\Console\Command;

class CheckServiceOrderSlaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'service-orders:check-sla';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica se as Ordens de Serviço excederam o SLA (tempo máximo de permanência) do status atual e dispara alertas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando verificação de SLA de Ordens de Serviço...');

        // Busca registros de histórico de status ativos (left_at é null)
        // onde o status de destino tenha tempo máximo definido
        $activeHistories = ServiceOrderStatusHistory::whereNull('left_at')
            ->whereHas('toStatus', function ($query) {
                $query->whereNotNull('max_stay_minutes');
            })
            ->where('sla_alert_sent', false)
            ->with(['serviceOrder', 'toStatus'])
            ->get();

        $alertsSent = 0;

        foreach ($activeHistories as $history) {
            $serviceOrder = $history->serviceOrder;
            $status = $history->toStatus;

            if (!$serviceOrder || !$status) {
                continue;
            }

            $timeSpentMinutes = (int) ((now()->timestamp - $history->entered_at->timestamp) / 60);
            
            if ($timeSpentMinutes > $status->max_stay_minutes) {
                $minutesExceeded = $timeSpentMinutes - $status->max_stay_minutes;

                // Marca como enviado para evitar duplicados
                $history->update(['sla_alert_sent' => true]);

                // Dispara o evento de SLA excedido
                event(new ServiceOrderSlaExceeded($serviceOrder, $status, $minutesExceeded));

                $this->warn("SLA Excedido: OS {$serviceOrder->code} no status '{$status->name}' por {$minutesExceeded} minutos.");
                $alertsSent++;
            }
        }

        $this->info("Verificação concluída. Alertas disparados: {$alertsSent}");

        return Command::SUCCESS;
    }
}
