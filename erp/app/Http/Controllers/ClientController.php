<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\Cnae;
use App\Models\ClientContact;
use App\Services\ClientGeocodingService;
use App\Services\CnpjaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function __construct(
        private readonly ClientGeocodingService $geocodingService
    ) {
        $this->authorizeResource(Client::class, 'client');
    }

    public function index(Request $request): View
    {
        $clients = Client::query()
            ->when($request->search, fn ($q, $s) => $q->search($s))
            ->when($request->status, fn ($q, $s) => $s === 'inactive' ? $q->where('is_active', false) : $q->active())
            ->withCount('serviceOrders')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('clients.index', compact('clients'));
    }

    public function create(): View
    {
        return view('clients.create');
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $client = Client::create($request->only([
                'name', 'document', 'document_type', 'phone', 'phone_secondary', 'email', 'notes',
                'social_name', 'trade_name', 'sector', 'opening_date', 'capital_social',
                'company_size', 'legal_nature', 'registration_status'
            ]));

            // Salvar CNAEs
            if ($request->filled('main_cnae_code')) {
                $mainCnae = Cnae::firstOrCreate(
                    ['code' => preg_replace('/\D/', '', $request->main_cnae_code)],
                    ['description' => $request->main_cnae_description ?? 'CNAE Principal']
                );

                $cnaesToSync = [
                    $mainCnae->id => ['is_primary' => true]
                ];

                if ($request->has('secondary_cnaes') && is_array($request->secondary_cnaes)) {
                    foreach ($request->secondary_cnaes as $sec) {
                        if (!empty($sec['code'])) {
                            $secCnae = Cnae::firstOrCreate(
                                ['code' => preg_replace('/\D/', '', $sec['code'])],
                                ['description' => $sec['description'] ?? 'CNAE Secundário']
                            );
                            $cnaesToSync[$secCnae->id] = ['is_primary' => false];
                        }
                    }
                }
                $client->cnaes()->sync($cnaesToSync);
            }

            // Salvar Contatos
            if ($request->has('contacts') && is_array($request->contacts)) {
                $contactsData = $request->contacts;
                $hasPrimary = false;
                foreach ($contactsData as &$cData) {
                    $cData['is_primary'] = filter_var($cData['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    if ($cData['is_primary']) {
                        if ($hasPrimary) {
                            $cData['is_primary'] = false;
                        } else {
                            $hasPrimary = true;
                        }
                    }
                }
                unset($cData);
                if (!$hasPrimary && !empty($contactsData)) {
                    $contactsData[0]['is_primary'] = true;
                }

                foreach ($contactsData as $cData) {
                    $client->contacts()->create([
                        'name'                => $cData['name'],
                        'email'               => $cData['email'] ?? null,
                        'phone'               => $cData['phone'] ?? null,
                        'whatsapp'            => $cData['whatsapp'] ?? null,
                        'role'                => $cData['role'] ?? null,
                        'is_primary'          => $cData['is_primary'] ?? false,
                        'is_phone_blocked'    => filter_var($cData['is_phone_blocked'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'is_whatsapp_blocked' => filter_var($cData['is_whatsapp_blocked'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'is_email_blocked'    => filter_var($cData['is_email_blocked'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    ]);
                }
            }
            // Salvar Equipamentos
            if ($request->has('equipments') && is_array($request->equipments)) {
                foreach ($request->equipments as $eqData) {
                    if (!empty($eqData['name'])) {
                        $client->equipments()->create([
                            'name'          => $eqData['name'],
                            'brand'         => $eqData['brand'] ?? null,
                            'model'         => $eqData['model'] ?? null,
                            'serial_number' => $eqData['serial_number'] ?? null,
                            'notes'         => $eqData['notes'] ?? null,
                        ]);
                    }
                }
            }

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

            // Geocodificar o endereço em background (não bloqueia a resposta)
            if ($address->zip_code || ($address->street && $address->city)) {
                $this->geocodingService->geocodeAndSave($address);
            }
        });

        return redirect()->route('clients.index')
            ->with('success', 'Cliente cadastrado com sucesso!');
    }

    public function show(Client $client): View
    {
        $client->load(['addresses', 'contacts', 'cnaes', 'serviceOrders' => function ($q) {
            $q->with(['technician'])->latest()->limit(10);
        }]);

        return view('clients.show', compact('client'));
    }

    public function edit(Client $client): View
    {
        $client->load(['addresses', 'contacts', 'cnaes', 'equipments']);

        return view('clients.edit', compact('client'));
    }

    public function update(StoreClientRequest $request, Client $client): RedirectResponse
    {
        DB::transaction(function () use ($request, $client) {
            $client->update($request->only([
                'name', 'document', 'document_type', 'phone', 'phone_secondary', 'email', 'notes',
                'social_name', 'trade_name', 'sector', 'opening_date', 'capital_social',
                'company_size', 'legal_nature', 'registration_status'
            ]));

            // Sincronizar CNAEs
            if ($request->filled('main_cnae_code')) {
                $mainCnae = Cnae::firstOrCreate(
                    ['code' => preg_replace('/\D/', '', $request->main_cnae_code)],
                    ['description' => $request->main_cnae_description ?? 'CNAE Principal']
                );

                $cnaesToSync = [
                    $mainCnae->id => ['is_primary' => true]
                ];

                if ($request->has('secondary_cnaes') && is_array($request->secondary_cnaes)) {
                    foreach ($request->secondary_cnaes as $sec) {
                        if (!empty($sec['code'])) {
                            $secCnae = Cnae::firstOrCreate(
                                ['code' => preg_replace('/\D/', '', $sec['code'])],
                                ['description' => $sec['description'] ?? 'CNAE Secundário']
                            );
                            $cnaesToSync[$secCnae->id] = ['is_primary' => false];
                        }
                    }
                }
                $client->cnaes()->sync($cnaesToSync);
            } else {
                $client->cnaes()->detach();
            }

            // Sincronizar Contatos
            if ($request->has('contacts') && is_array($request->contacts)) {
                $incomingContactIds = [];
                $contactsData = $request->contacts;

                $hasPrimary = false;
                foreach ($contactsData as &$cData) {
                    $cData['is_primary'] = filter_var($cData['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    if ($cData['is_primary']) {
                        if ($hasPrimary) {
                            $cData['is_primary'] = false;
                        } else {
                            $hasPrimary = true;
                        }
                    }
                }
                unset($cData);

                if (!$hasPrimary && !empty($contactsData)) {
                    $contactsData[0]['is_primary'] = true;
                }

                foreach ($contactsData as $cData) {
                    $contact = null;
                    if (!empty($cData['id'])) {
                        $contact = $client->contacts()->find($cData['id']);
                    }

                    $fields = [
                        'name'                => $cData['name'],
                        'email'               => $cData['email'] ?? null,
                        'phone'               => $cData['phone'] ?? null,
                        'whatsapp'            => $cData['whatsapp'] ?? null,
                        'role'                => $cData['role'] ?? null,
                        'is_primary'          => $cData['is_primary'] ?? false,
                        'is_phone_blocked'    => filter_var($cData['is_phone_blocked'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'is_whatsapp_blocked' => filter_var($cData['is_whatsapp_blocked'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'is_email_blocked'    => filter_var($cData['is_email_blocked'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    ];

                    if ($contact) {
                        $contact->update($fields);
                        $incomingContactIds[] = $contact->id;
                    } else {
                        $newContact = $client->contacts()->create($fields);
                        $incomingContactIds[] = $newContact->id;
                    }
                }

                $client->contacts()->whereNotIn('id', $incomingContactIds)->delete();
            } else {
                if ($request->has('contacts')) {
                    $client->contacts()->delete();
                }
            }

            // Sincronizar Equipamentos
            if ($request->has('equipments') && is_array($request->equipments)) {
                $incomingEquipmentIds = [];
                foreach ($request->equipments as $eqData) {
                    if (empty($eqData['name'])) continue;

                    $equipment = null;
                    if (!empty($eqData['id'])) {
                        $equipment = $client->equipments()->find($eqData['id']);
                    }

                    $fields = [
                        'name'          => $eqData['name'],
                        'brand'         => $eqData['brand'] ?? null,
                        'model'         => $eqData['model'] ?? null,
                        'serial_number' => $eqData['serial_number'] ?? null,
                        'notes'         => $eqData['notes'] ?? null,
                    ];

                    if ($equipment) {
                        $equipment->update($fields);
                        $incomingEquipmentIds[] = $equipment->id;
                    } else {
                        $newEquipment = $client->equipments()->create($fields);
                        $incomingEquipmentIds[] = $newEquipment->id;
                    }
                }
                $client->equipments()->whereNotIn('id', $incomingEquipmentIds)->delete();
            } else {
                if ($request->has('equipments')) {
                    $client->equipments()->delete();
                }
            }

            $address = $client->primaryAddress ?? $client->addresses()->first();

            if ($address) {
                $address->update($request->only([
                    'zip_code', 'street', 'number', 'complement', 'neighborhood', 'city', 'state',
                ]));

                // Regeocodificar se endereço mudou
                if ($address->wasChanged(['street', 'city', 'zip_code'])) {
                    $this->geocodingService->geocodeAndSave($address);
                }
            }
        });

        return redirect()->route('clients.show', $client)
            ->with('success', 'Cliente atualizado com sucesso!');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $client->delete();

        return redirect()->route('clients.index')
            ->with('success', 'Cliente removido com sucesso!');
    }

    /**
     * Consulta CNPJ via AJAX.
     */
    public function lookupCnpj(string $cnpj, CnpjaService $cnpjService): JsonResponse
    {
        $this->authorize('create', Client::class);

        try {
            $data = $cnpjService->consult($cnpj);
            return response()->json($data);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
