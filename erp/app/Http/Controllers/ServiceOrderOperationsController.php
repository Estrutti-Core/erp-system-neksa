<?php

namespace App\Http\Controllers;

use App\Models\ServiceOrder;
use App\Models\ServiceOrderChecklist;
use App\Models\ChecklistAnswer;
use App\Models\ServiceOrderCheckin;
use App\Models\ServiceOrderSignature;
use App\Models\ServiceOrderAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ServiceOrderOperationsController extends Controller
{
    /**
     * Exibe o formulário de preenchimento de um checklist.
     */
    public function fillChecklist(ServiceOrder $serviceOrder, ServiceOrderChecklist $checklist)
    {
        $this->authorize('view', $serviceOrder);

        abort_if($checklist->service_order_id !== $serviceOrder->id, 404);
        abort_if($checklist->is_inactive, 403, 'Este checklist está inativo.');

        $checklist->load('instancedQuestions.answer');

        return view('service-orders.checklists.fill', compact('serviceOrder', 'checklist'));
    }

    /**
     * Salva as respostas do checklist.
     */
    public function saveChecklist(Request $request, ServiceOrder $serviceOrder, ServiceOrderChecklist $checklist)
    {
        $this->authorize('update', $serviceOrder);

        abort_if($checklist->service_order_id !== $serviceOrder->id, 404);
        abort_if($checklist->is_inactive, 403, 'Este checklist está inativo.');

        $request->validate([
            'answers'              => ['required', 'array'],
            'answers.*.value'      => ['nullable', 'string'],
            'answers.*.photo'      => ['nullable', 'image', 'max:10240'],
        ]);

        $checklist->load('instancedQuestions');

        foreach ($checklist->instancedQuestions as $question) {
            $key     = $question->id;
            $value   = $request->input("answers.{$key}.value");
            $photo   = $request->file("answers.{$key}.photo");
            $photoPath = null;

            if ($photo) {
                $photoPath = $photo->store(
                    "checklists/{$serviceOrder->id}",
                    'public'
                );
            }

            ChecklistAnswer::updateOrCreate(
                [
                    'service_order_checklist_id'          => $checklist->id,
                    'service_order_checklist_question_id' => $question->id,
                ],
                [
                    'checklist_question_id' => $question->checklist_question_id,
                    'answer_value'          => $value,
                    'photo_path'            => $photoPath ?? null,
                ]
            );
        }

        // Marcar checklist como preenchido
        $checklist->update([
            'filled_at' => now(),
            'filled_by' => auth()->id(),
        ]);

        return redirect()
            ->route('service-orders.show', $serviceOrder)
            ->with('success', 'Checklist preenchido com sucesso!');
    }

    /**
     * Registra check-in de chegada do técnico (via GPS).
     */
    public function checkIn(Request $request, ServiceOrder $serviceOrder)
    {
        $this->authorize('update', $serviceOrder);

        $data = $request->validate([
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'type'      => ['required', 'in:checkin,checkout'],
            'notes'     => ['nullable', 'string', 'max:500'],
        ]);

        $checkin = $serviceOrder->checkins()->create([
            'user_id'    => auth()->id(),
            'type'       => $data['type'],
            'latitude'   => $data['latitude'],
            'longitude'  => $data['longitude'],
            'notes'      => $data['notes'] ?? null,
            'checked_at' => now(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'checkin' => $checkin]);
        }

        $label = $data['type'] === 'checkin' ? 'Check-in' : 'Check-out';
        return back()->with('success', "{$label} registrado com sucesso!");
    }

    /**
     * Salva a assinatura digital do cliente.
     */
    public function saveSignature(Request $request, ServiceOrder $serviceOrder)
    {
        $this->authorize('update', $serviceOrder);

        $request->validate([
            'signer_name'     => ['required', 'string', 'max:255'],
            'signer_document' => ['nullable', 'string', 'max:50'],
            'signature_data'  => ['required', 'string'], // base64 PNG
            'latitude'        => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'       => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        // Decodificar base64 e salvar como PNG
        $base64 = $request->input('signature_data');
        $base64 = preg_replace('/^data:image\/\w+;base64,/', '', $base64);
        $imageData = base64_decode($base64);

        $filename = 'signatures/' . $serviceOrder->id . '/' . Str::uuid() . '.png';
        Storage::disk('public')->put($filename, $imageData);

        // Substituir assinatura anterior se existir
        $serviceOrder->signature()?->delete();

        $serviceOrder->signature()->create([
            'signer_name'      => $request->signer_name,
            'signer_document'  => $request->signer_document,
            'path'             => $filename,
            'disk'             => 'public',
            'signed_latitude'  => $request->latitude,
            'signed_longitude' => $request->longitude,
            'ip_address'       => $request->ip(),
            'user_agent'       => $request->userAgent(),
            'signed_at'        => now(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Assinatura coletada com sucesso!');
    }

    /**
     * Upload de múltiplos anexos.
     */
    public function uploadAttachments(Request $request, ServiceOrder $serviceOrder)
    {
        $this->authorize('update', $serviceOrder);

        $request->validate([
            'attachments'   => ['required', 'array', 'min:1'],
            'attachments.*' => ['required', 'file', 'max:10240',
                'mimes:jpg,jpeg,png,gif,webp,pdf,mp4,mov,avi'],
            'type'    => ['nullable', 'in:before,after,general'],
            'caption' => ['nullable', 'string', 'max:255'],
        ]);

        $type    = $request->input('type', 'general');
        $caption = $request->input('caption');

        foreach ($request->file('attachments') as $file) {
            $path = $file->store("attachments/{$serviceOrder->id}", 'public');

            $serviceOrder->attachments()->create([
                'uploaded_by'   => auth()->id(),
                'path'          => $path,
                'original_name' => $file->getClientOriginalName(),
                'disk'          => 'public',
                'type'          => $type,
                'caption'       => $caption,
                'size'          => $file->getSize(),
                'mime_type'     => $file->getMimeType(),
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Arquivo(s) enviado(s) com sucesso!');
    }

    /**
     * Remove um anexo da OS.
     */
    public function destroyAttachment(ServiceOrder $serviceOrder, ServiceOrderAttachment $attachment)
    {
        $this->authorize('update', $serviceOrder);

        abort_if($attachment->service_order_id !== $serviceOrder->id, 404);

        Storage::disk($attachment->disk ?? 'public')->delete($attachment->path);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($attachment)
            ->withProperties(['path' => $attachment->path, 'original_name' => $attachment->original_name])
            ->log('attachment_deleted');

        $attachment->delete();

        return back()->with('success', 'Anexo removido.');
    }
}
