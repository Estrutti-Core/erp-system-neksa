<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientRequest;
use App\Http\Resources\Api\V1\ClientResource;
use App\Models\Client;
use App\Services\ClientGeocodingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    public function __construct(
        private readonly ClientGeocodingService $geocodingService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Client::class);

        $clients = Client::query()
            ->when($request->search, fn ($q, $s) => $q->search($s))
            ->when($request->status, fn ($q, $s) => $s === 'inactive' ? $q->where('is_active', false) : $q->active())
            ->latest()
            ->paginate(20);

        return ClientResource::collection($clients);
    }

    public function show(Client $client): ClientResource
    {
        $this->authorize('view', $client);
        $client->load('addresses');

        return new ClientResource($client);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        // A autorização é feita diretamente no StoreClientRequest

        $client = DB::transaction(function () use ($request) {
            $client = Client::create($request->only([
                'name', 'document', 'document_type', 'phone', 'phone_secondary', 'email', 'notes',
            ]));

            $address = $client->addresses()->create([
                'label'        => 'Principal',
                'zip_code'     => $request->zip_code,
                'street'       => $request->street,
                'number'       => $request->number,
                'complement'   => $request->complement,
                'neighborhood' => $request->neighborhood,
                'city'         => $request->city,
                'state'        => $request->state,
                'is_primary'   => true,
            ]);

            // Geocodificar
            if ($address->zip_code || ($address->street && $address->city)) {
                $this->geocodingService->geocodeAndSave($address);
            }

            return $client;
        });

        $client->load('addresses');

        return response()->json([
            'message' => 'Cliente cadastrado com sucesso.',
            'client'  => new ClientResource($client),
        ], 210); // 210 ou 201
    }
}
