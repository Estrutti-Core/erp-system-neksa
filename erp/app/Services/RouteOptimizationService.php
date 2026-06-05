<?php

namespace App\Services;

use App\Models\ServiceOrder;
use Illuminate\Support\Collection;

class RouteOptimizationService
{
    /**
     * Otimiza a sequência de OS usando o algoritmo do Vizinho mais Próximo (Nearest Neighbor).
     * Parte de um ponto de origem e encontra a OS mais próxima a cada passo.
     *
     * @param  Collection<ServiceOrder>  $serviceOrders
     * @param  float  $originLat  Latitude do ponto de partida
     * @param  float  $originLng  Longitude do ponto de partida
     * @return array{order: array, total_distance_km: float, waypoints: array}
     */
    public function optimize(Collection $serviceOrders, float $originLat, float $originLng): array
    {
        $unvisited  = $serviceOrders->filter(fn ($os) => $os->clientAddress?->hasCoordinates())->values();
        $visited    = collect();
        $totalDist  = 0.0;
        $waypoints  = [];

        $currentLat = $originLat;
        $currentLng = $originLng;

        while ($unvisited->isNotEmpty()) {
            $nearest  = null;
            $minDist  = PHP_FLOAT_MAX;
            $nearestIdx = null;

            foreach ($unvisited as $idx => $os) {
                $lat  = $os->clientAddress->latitude;
                $lng  = $os->clientAddress->longitude;
                $dist = $this->haversine($currentLat, $currentLng, $lat, $lng);

                if ($dist < $minDist) {
                    $minDist    = $dist;
                    $nearest    = $os;
                    $nearestIdx = $idx;
                }
            }

            $visited->push($nearest);
            $unvisited->forget($nearestIdx);
            $totalDist  += $minDist;
            $currentLat  = $nearest->clientAddress->latitude;
            $currentLng  = $nearest->clientAddress->longitude;

            $waypoints[] = [
                'service_order_id'        => $nearest->id,
                'code'                    => $nearest->code,
                'client_name'             => $nearest->client->name,
                'address'                 => $nearest->clientAddress->short_address,
                'latitude'                => $currentLat,
                'longitude'               => $currentLng,
                'distance_from_prev_km'   => round($minDist, 2),
                'estimated_minutes_from_prev' => $this->estimateMinutes($minDist),
            ];
        }

        return [
            'order'              => $visited->pluck('id')->toArray(),
            'total_distance_km'  => round($totalDist, 2),
            'estimated_minutes'  => $this->estimateMinutes($totalDist),
            'waypoints'          => $waypoints,
        ];
    }

    /**
     * Calcula a distância entre dois pontos usando a fórmula de Haversine (km).
     */
    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Estima o tempo de deslocamento em minutos (velocidade média urbana: 30 km/h).
     */
    private function estimateMinutes(float $distanceKm): int
    {
        return (int) ceil(($distanceKm / 30) * 60);
    }
}
