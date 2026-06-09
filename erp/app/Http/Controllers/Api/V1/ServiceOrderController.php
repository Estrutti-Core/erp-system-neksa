<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ServiceOrderResource;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderStatus;
use App\Models\ServiceOrderChecklist;
use App\Models\ChecklistAnswer;
use App\Services\ServiceOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ServiceOrderController extends Controller
{
    public function __construct(
        private readonly ServiceOrderService $serviceOrderService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $orders = ServiceOrder::query()
            ->with(['client', 'clientAddress', 'technician', 'status'])
            ->when($user->isTechnician(), fn ($q) => $q->forTechnician($user->id))
            ->when($request->status, fn ($q, $s) => $q->status($s))
            ->latest()
            ->paginate(20);

        return ServiceOrderResource::collection($orders);
    }

    public function show(ServiceOrder $serviceOrder): ServiceOrderResource
    {
        $this->authorize('view', $serviceOrder);

        $serviceOrder->load([
            'client',
            'clientAddress',
            'equipment',
            'technician',
            'creator',
            'items.service',
            'attachments.uploader',
            'checklists.template',
            'checklists.instancedQuestions.answer',
            'checkins.user',
            'history.user',
            'signature',
            'status',
        ]);

        return new ServiceOrderResource($serviceOrder);
    }

    public function updateStatus(Request $request, ServiceOrder $serviceOrder): JsonResponse
    {
        $this->authorize('changeStatus', $serviceOrder);

        $request->validate([
            'status' => ['required', 'string', 'exists:service_order_statuses,slug'],
            'note'   => ['nullable', 'string'],
        ]);

        $newStatus = ServiceOrderStatus::where('slug', $request->status)->firstOrFail();

        $updated = $this->serviceOrderService->changeStatus(
            $serviceOrder,
            $newStatus,
            $request->user(),
            $request->note,
        );

        return response()->json([
            'message'       => 'Status atualizado com sucesso.',
            'service_order' => new ServiceOrderResource($updated->load(['status', 'client', 'clientAddress', 'technician'])),
        ]);
    }

    /**
     * Registra check-in/out via API na tabela dedicada.
     */
    public function checkIn(Request $request, ServiceOrder $serviceOrder): JsonResponse
    {
        $this->authorize('update', $serviceOrder);

        $request->validate([
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'type'      => ['required', 'in:checkin,checkout'],
            'notes'     => ['nullable', 'string', 'max:500'],
        ]);

        $checkin = $serviceOrder->checkins()->create([
            'user_id'    => $request->user()->id,
            'type'       => $request->type,
            'latitude'   => $request->latitude,
            'longitude'  => $request->longitude,
            'notes'      => $request->notes,
            'checked_at' => now(),
        ]);

        return response()->json([
            'message' => 'Check-in/out registrado com sucesso.',
            'checkin' => $checkin,
        ]);
    }

    /**
     * Salva assinatura digital via API.
     */
    public function signature(Request $request, ServiceOrder $serviceOrder): JsonResponse
    {
        $this->authorize('update', $serviceOrder);

        $request->validate([
            'signer_name'     => ['required', 'string', 'max:255'],
            'signer_document' => ['nullable', 'string', 'max:50'],
            'signature_data'  => ['required', 'string'], // base64 PNG
            'latitude'        => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'       => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        // Decodificar base64 e salvar
        $base64 = $request->input('signature_data');
        $base64 = preg_replace('/^data:image\/\w+;base64,/', '', $base64);
        $imageData = base64_decode($base64);

        $filename = 'signatures/' . $serviceOrder->id . '/' . Str::uuid() . '.png';
        Storage::disk('public')->put($filename, $imageData);

        // Substituir assinatura anterior se houver
        $serviceOrder->signature()?->delete();

        $signature = $serviceOrder->signature()->create([
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

        return response()->json([
            'message'   => 'Assinatura salva com sucesso.',
            'signature' => [
                'signer_name' => $signature->signer_name,
                'url'         => $signature->url,
            ],
        ]);
    }

    /**
     * Salva múltiplas fotos/anexos na OS via API.
     */
    public function uploadAttachments(Request $request, ServiceOrder $serviceOrder): JsonResponse
    {
        $this->authorize('update', $serviceOrder);

        $request->validate([
            'attachments'   => ['required', 'array', 'min:1'],
            'attachments.*' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,mp4,mov,avi'],
            'type'          => ['nullable', 'in:before,after,general'],
            'caption'       => ['nullable', 'string', 'max:255'],
        ]);

        $type    = $request->input('type', 'general');
        $caption = $request->input('caption');
        $saved   = [];

        foreach ($request->file('attachments') as $file) {
            $path = $file->store("attachments/{$serviceOrder->id}", 'public');

            $att = $serviceOrder->attachments()->create([
                'uploaded_by'   => $request->user()->id,
                'path'          => $path,
                'original_name' => $file->getClientOriginalName(),
                'disk'          => 'public',
                'type'          => $type,
                'caption'       => $caption,
                'size'          => $file->getSize(),
                'mime_type'     => $file->getMimeType(),
            ]);

            $saved[] = [
                'id'            => $att->id,
                'original_name' => $att->original_name,
                'url'           => $att->url,
            ];
        }

        return response()->json([
            'message'     => 'Anexos enviados com sucesso.',
            'attachments' => $saved,
        ]);
    }

    /**
     * Salva respostas de checklist via API.
     */
    public function fillChecklist(Request $request, ServiceOrder $serviceOrder, ServiceOrderChecklist $checklist): JsonResponse
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
                $photoPath = $photo->store("checklists/{$serviceOrder->id}", 'public');
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

        $checklist->update([
            'filled_at' => now(),
            'filled_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Checklist salvo com sucesso.',
        ]);
    }
}
