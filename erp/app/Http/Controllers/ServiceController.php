<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceRequest;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Service::class, 'service');
    }

    public function index(Request $request): View
    {
        $services = Service::query()
            ->when($request->search, fn ($q, $s) => $q->search($s))
            ->when($request->status, fn ($q, $s) => $s === 'inactive' ? $q->where('is_active', false) : $q->active())
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('services.index', compact('services'));
    }

    public function create(): View
    {
        $checklistTemplates = \App\Models\ChecklistTemplate::orderBy('name')->get();
        return view('services.create', compact('checklistTemplates'));
    }

    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $service = Service::create($request->validated());

        if ($request->has('checklist_templates')) {
            $service->checklistTemplates()->sync($request->input('checklist_templates'));
        }

        return redirect()->route('services.index')
            ->with('success', 'Serviço cadastrado com sucesso!');
    }

    public function show(Service $service): View
    {
        return view('services.show', compact('service'));
    }

    public function edit(Service $service): View
    {
        $service->load('checklistTemplates');
        $checklistTemplates = \App\Models\ChecklistTemplate::orderBy('name')->get();
        return view('services.edit', compact('service', 'checklistTemplates'));
    }

    public function update(StoreServiceRequest $request, Service $service): RedirectResponse
    {
        $service->update($request->validated());

        if ($request->has('checklist_templates')) {
            $service->checklistTemplates()->sync($request->input('checklist_templates'));
        } else {
            $service->checklistTemplates()->detach();
        }

        return redirect()->route('services.index')
            ->with('success', 'Serviço atualizado com sucesso!');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        return redirect()->route('services.index')
            ->with('success', 'Serviço removido com sucesso!');
    }

    /**
     * Endpoint JSON para autocomplete.
     */
    public function search(Request $request): JsonResponse
    {
        $term = $request->get('q', '');

        $services = Service::query()
            ->active()
            ->search($term)
            ->limit(10)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'sku' => $s->sku,
                'sale_price' => number_format($s->price, 2, ',', '.'),
                'sale_price_raw' => $s->price,
                'unit' => 'un',
                'type' => 'service',
                'type_label' => 'Serviço',
            ]);

        return response()->json($services);
    }
}
