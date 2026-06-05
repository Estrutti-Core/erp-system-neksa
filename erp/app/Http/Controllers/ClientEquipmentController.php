<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientEquipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ClientEquipmentController extends Controller
{
    /**
     * Store a newly created equipment in storage.
     */
    public function store(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('update', $client);

        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'brand'         => 'nullable|string|max:255',
            'model'         => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'notes'         => 'nullable|string',
        ]);

        $client->equipments()->create($validated);

        return redirect()->route('clients.show', $client)
            ->with('success', 'Equipamento adicionado com sucesso!');
    }

    /**
     * Update the specified equipment in storage.
     */
    public function update(Request $request, ClientEquipment $equipment): RedirectResponse
    {
        Gate::authorize('update', $equipment->client);

        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'brand'         => 'nullable|string|max:255',
            'model'         => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'notes'         => 'nullable|string',
        ]);

        $equipment->update($validated);

        return redirect()->route('clients.show', $equipment->client)
            ->with('success', 'Equipamento atualizado com sucesso!');
    }

    /**
     * Remove the specified equipment from storage.
     */
    public function destroy(ClientEquipment $equipment): RedirectResponse
    {
        Gate::authorize('update', $equipment->client);

        $client = $equipment->client;
        $equipment->delete();

        return redirect()->route('clients.show', $client)
            ->with('success', 'Equipamento removido com sucesso!');
    }

    /**
     * List client equipments in JSON format for autocomplete/dropdown selection.
     */
    public function listJson(Client $client): JsonResponse
    {
        Gate::authorize('view', $client);

        $equipments = $client->equipments()
            ->latest()
            ->get(['id', 'name', 'brand', 'model', 'serial_number']);

        return response()->json($equipments);
    }
}
