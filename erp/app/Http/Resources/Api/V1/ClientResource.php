<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'document' => $this->document,
            'formatted_document' => $this->formatted_document,
            'document_type' => $this->document_type,
            'phone' => $this->phone,
            'phone_secondary' => $this->phone_secondary,
            'email' => $this->email,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),

            'addresses' => $this->whenLoaded('addresses', fn () => $this->addresses->map(fn ($addr) => [
                'id' => $addr->id,
                'label' => $addr->label,
                'zip_code' => $addr->zip_code,
                'street' => $addr->street,
                'number' => $addr->number,
                'complement' => $addr->complement,
                'neighborhood' => $addr->neighborhood,
                'city' => $addr->city,
                'state' => $addr->state,
                'is_primary' => $addr->is_primary,
                'latitude' => $addr->latitude,
                'longitude' => $addr->longitude,
            ])),
        ];
    }
}
