<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'code'                => $this->code,
            'status'              => $this->status->value,
            'status_label'        => $this->status->label(),
            'status_color'        => $this->status->color(),
            'priority'            => $this->priority->value,
            'priority_label'      => $this->priority->label(),
            'description'         => $this->description,
            'services_performed'  => $this->services_performed,
            'internal_notes'      => $this->internal_notes,
            'total_amount'        => (float) $this->total_amount,
            'service_amount'      => (float) $this->service_amount,
            'parts_amount'        => (float) $this->parts_amount,
            'scheduled_at'        => $this->scheduled_at?->toIso8601String(),
            'started_at'          => $this->started_at?->toIso8601String(),
            'completed_at'        => $this->completed_at?->toIso8601String(),
            'checkin_at'          => $this->checkin_at?->toIso8601String(),
            'checkin_latitude'    => $this->checkin_latitude,
            'checkin_longitude'   => $this->checkin_longitude,
            'created_at'          => $this->created_at->toIso8601String(),
            'updated_at'          => $this->updated_at->toIso8601String(),

            'client' => $this->whenLoaded('client', fn () => [
                'id'    => $this->client->id,
                'name'  => $this->client->name,
                'phone' => $this->client->phone,
                'email' => $this->client->email,
            ]),

            'client_address' => $this->whenLoaded('clientAddress', fn () => $this->clientAddress ? [
                'id'           => $this->clientAddress->id,
                'full_address' => $this->clientAddress->full_address,
                'latitude'     => $this->clientAddress->latitude,
                'longitude'    => $this->clientAddress->longitude,
            ] : null),

            'technician' => $this->whenLoaded('technician', fn () => $this->technician ? [
                'id'   => $this->technician->id,
                'name' => $this->technician->name,
            ] : null),

            'items' => $this->whenLoaded('items', fn () =>
                $this->items->map(fn ($item) => [
                    'id'          => $item->id,
                    'type'        => $item->type,
                    'description' => $item->description,
                    'quantity'    => (float) $item->quantity,
                    'unit'        => $item->unit,
                    'unit_price'  => (float) $item->unit_price,
                    'total_price' => (float) $item->total_price,
                ])
            ),

            'has_signature' => $this->whenLoaded('signature', fn () => $this->signature !== null),
            'photos_count'  => $this->whenLoaded('photos', fn () => $this->photos->count()),
        ];
    }
}
