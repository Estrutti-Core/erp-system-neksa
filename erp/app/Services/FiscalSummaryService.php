<?php

namespace App\Services;

use App\Models\ServiceOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;

class FiscalSummaryService
{
    /**
     * Gera os dados estruturados para o resumo fiscal de uma OS.
     */
    public function generate(ServiceOrder $serviceOrder): array
    {
        $serviceOrder->load(['client', 'clientAddress', 'items', 'technician']);

        $services = $serviceOrder->items->where('type', 'service');
        $parts    = $serviceOrder->items->where('type', 'part');
        $materials = $serviceOrder->items->where('type', 'material');

        return [
            'os_code'          => $serviceOrder->code,
            'os_date'          => $serviceOrder->completed_at?->format('d/m/Y') ?? $serviceOrder->updated_at->format('d/m/Y'),
            'client' => [
                'name'     => $serviceOrder->client->name,
                'document' => $serviceOrder->client->formatted_document,
                'email'    => $serviceOrder->client->email,
                'phone'    => $serviceOrder->client->phone,
                'address'  => $serviceOrder->clientAddress?->full_address ?? '—',
            ],
            'technician' => [
                'name'  => $serviceOrder->technician?->name ?? '—',
            ],
            'services'   => $services->map(fn ($i) => [
                'description' => $i->description,
                'quantity'    => (float) $i->quantity,
                'unit'        => $i->unit,
                'unit_price'  => (float) $i->unit_price,
                'total'       => (float) $i->total_price,
            ])->values()->toArray(),
            'parts'      => $parts->map(fn ($i) => [
                'description'  => $i->description,
                'product_code' => $i->product_code,
                'quantity'     => (float) $i->quantity,
                'unit'         => $i->unit,
                'unit_price'   => (float) $i->unit_price,
                'total'        => (float) $i->total_price,
            ])->values()->toArray(),
            'materials'  => $materials->map(fn ($i) => [
                'description' => $i->description,
                'quantity'    => (float) $i->quantity,
                'unit'        => $i->unit,
                'unit_price'  => (float) $i->unit_price,
                'total'       => (float) $i->total_price,
            ])->values()->toArray(),
            'totals' => [
                'services'  => (float) $serviceOrder->service_amount,
                'parts'     => (float) $serviceOrder->parts_amount,
                'total'     => (float) $serviceOrder->total_amount,
            ],
            'notes'            => $serviceOrder->services_performed,
            'generated_at'     => now()->format('d/m/Y H:i'),
        ];
    }

    public function generatePdf(ServiceOrder $serviceOrder, string $mode = 'technical'): Response
    {
        $data = $this->generate($serviceOrder);

        $serviceOrder->load([
            'client', 'clientAddress', 'items', 'signature',
            'technician', 'equipment',
            'checklists' => fn($q) => $q->where('is_inactive', false)->with('instancedQuestions.answer'),
            'checkins'   => fn($q) => $q->where('type', 'checkin')->with('user'),
            'attachments' => fn($q) => $q->where(fn($q2) => $q2->where('mime_type', 'like', 'image/%')),
        ]);

        if ($mode === 'receipt') {
            $pdf = Pdf::loadView('pdf.service-order_receipt', [
                'serviceOrder' => $serviceOrder,
                'fiscal'       => $data,
            ])->setPaper('a4');
            $docType = 'RECIBO';
        } else {
            $pdf = Pdf::loadView('pdf.service-order', [
                'serviceOrder' => $serviceOrder,
                'fiscal'       => $data,
            ])->setPaper('a4');
            $docType = 'OS';
        }

        $clientName = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::slug($serviceOrder->client->name));
        $number     = preg_replace('/^OS-/', '', $serviceOrder->code);
        $filename   = "{$clientName}-{$docType}-{$number}.pdf";

        return $pdf->stream($filename);
    }
}
