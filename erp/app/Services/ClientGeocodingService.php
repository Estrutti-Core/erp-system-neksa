<?php

namespace App\Services;

use App\Models\ClientAddress;

class ClientGeocodingService
{
    private string $nominatimUrl = 'https://nominatim.openstreetmap.org/search';

    /**
     * Geocodifica um endereço usando Nominatim (OpenStreetMap) — sem necessidade de API key.
     * Caso GOOGLE_MAPS_API_KEY esteja configurado, usa a API do Google como alternativa.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public function geocode(ClientAddress $address): ?array
    {
        if (config('services.google_maps.key')) {
            return $this->geocodeWithGoogle($address);
        }

        return $this->geocodeWithNominatim($address);
    }

    public function geocodeAndSave(ClientAddress $address): bool
    {
        $coordinates = $this->geocode($address);

        if (! $coordinates) {
            return false;
        }

        $address->update([
            'latitude'  => $coordinates['latitude'],
            'longitude' => $coordinates['longitude'],
        ]);

        return true;
    }

    private function geocodeWithNominatim(ClientAddress $address): ?array
    {
        $query = implode(', ', array_filter([
            $address->street . ' ' . $address->number,
            $address->neighborhood,
            $address->city,
            $address->state,
            'Brasil',
        ]));

        try {
            $response = \Http::withHeaders([
                'User-Agent' => 'NeksaERP/1.0 (contact@neksa.com.br)',
            ])->get($this->nominatimUrl, [
                'q'      => $query,
                'format' => 'json',
                'limit'  => 1,
            ]);

            $results = $response->json();

            if (empty($results)) {
                return null;
            }

            return [
                'latitude'  => (float) $results[0]['lat'],
                'longitude' => (float) $results[0]['lon'],
            ];
        } catch (\Exception) {
            return null;
        }
    }

    private function geocodeWithGoogle(ClientAddress $address): ?array
    {
        $fullAddress = $address->full_address;
        $apiKey      = config('services.google_maps.key');

        try {
            $response = \Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $fullAddress,
                'key'     => $apiKey,
            ]);

            $data = $response->json();

            if ($data['status'] !== 'OK' || empty($data['results'])) {
                return null;
            }

            $location = $data['results'][0]['geometry']['location'];

            return [
                'latitude'  => $location['lat'],
                'longitude' => $location['lng'],
            ];
        } catch (\Exception) {
            return null;
        }
    }
}
