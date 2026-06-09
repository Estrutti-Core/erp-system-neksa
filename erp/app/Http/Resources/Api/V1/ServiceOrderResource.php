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
            'status'              => $this->status->slug,
            'status_label'        => $this->status->name,
            'status_color'        => $this->status->color,
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

            'equipment' => $this->whenLoaded('equipment', fn () => $this->equipment ? [
                'id'            => $this->equipment->id,
                'name'          => $this->equipment->name,
                'brand'         => $this->equipment->brand,
                'model'         => $this->equipment->model,
                'serial_number' => $this->equipment->serial_number,
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
            'signature'     => $this->whenLoaded('signature', fn () => $this->signature ? [
                'signer_name'      => $this->signature->signer_name,
                'signer_document'  => $this->signature->signer_document,
                'url'              => $this->signature->url,
                'signed_at'        => $this->signature->signed_at->toIso8601String(),
            ] : null),

            'attachments' => $this->whenLoaded('attachments', fn () =>
                $this->attachments->map(fn ($att) => [
                    'id'            => $att->id,
                    'original_name' => $att->original_name,
                    'url'           => $att->url,
                    'type'          => $att->type,
                    'caption'       => $att->caption,
                    'mime_type'     => $att->mime_type,
                    'size'          => $att->size,
                ])
            ),

            'checklists' => $this->whenLoaded('checklists', fn () =>
                $this->checklists->map(fn ($checklist) => [
                    'id'          => $checklist->id,
                    'name'        => $checklist->template->name,
                    'is_inactive' => (bool) $checklist->is_inactive,
                    'is_filled'   => $checklist->isFilled(),
                    'filled_at'   => $checklist->filled_at?->toIso8601String(),
                    'questions'   => $checklist->instancedQuestions->map(fn ($q) => [
                        'id'            => $q->id,
                        'question_text' => $q->question_text,
                        'question_type' => $q->question_type,
                        'is_required'   => (bool) $q->is_required,
                        'options'       => $q->options_json,
                        'answer'        => $q->answer ? [
                            'value'      => $q->answer->answer_value,
                            'photo_url'  => $q->answer->photo_url,
                        ] : null,
                    ]),
                ])
            ),

            'checkins' => $this->whenLoaded('checkins', fn () =>
                $this->checkins->map(fn ($ci) => [
                    'id'         => $ci->id,
                    'type'       => $ci->type,
                    'latitude'   => $ci->latitude,
                    'longitude'  => $ci->longitude,
                    'notes'      => $ci->notes,
                    'checked_at' => $ci->checked_at->toIso8601String(),
                    'user_name'  => $ci->user->name,
                ])
            ),
        ];
    }
}
