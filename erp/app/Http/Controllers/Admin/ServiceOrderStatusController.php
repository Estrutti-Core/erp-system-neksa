<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceOrderStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServiceOrderStatusController extends Controller
{
    /**
     * Exibe a listagem de status.
     */
    public function index()
    {
        $statuses = ServiceOrderStatus::with('allowedTransitions')->get();
        return view('settings.statuses.index', compact('statuses'));
    }

    /**
     * Exibe o formulário de criação de status.
     */
    public function create()
    {
        $statuses = ServiceOrderStatus::all();
        return view('settings.statuses.create', compact('statuses'));
    }

    /**
     * Salva um novo status.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:service_order_statuses,name',
            'color' => 'required|string|max:50',
            'max_stay_minutes' => 'nullable|integer|min:1',
            'expected_time_minutes' => 'nullable|integer|min:1',
        ]);

        $isOpen = $request->boolean('is_open_state');
        $isCompleted = $request->boolean('is_completed_state');
        $isCancelled = $request->boolean('is_cancelled_state');

        // Validações de exclusividade lógica
        if ($isCompleted && $isCancelled) {
            throw ValidationException::withMessages(['is_completed_state' => 'Um status não pode ser de conclusão e de cancelamento ao mesmo tempo.']);
        }
        if (($isCompleted || $isCancelled) && $isOpen) {
            throw ValidationException::withMessages(['is_open_state' => 'Um status de conclusão ou cancelamento não pode ser um estado aberto.']);
        }

        // Se for marcado como concluído, garante que será o único no sistema
        if ($isCompleted) {
            ServiceOrderStatus::query()->update(['is_completed_state' => false]);
        }

        // Se for marcado como cancelado, garante que será o único no sistema
        if ($isCancelled) {
            ServiceOrderStatus::query()->update(['is_cancelled_state' => false]);
        }

        $status = ServiceOrderStatus::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'color' => $data['color'],
            'max_stay_minutes' => $data['max_stay_minutes'] ?? null,
            'expected_time_minutes' => $data['expected_time_minutes'] ?? null,
            'is_open_state' => $isOpen,
            'is_completed_state' => $isCompleted,
            'is_cancelled_state' => $isCancelled,
            'is_system' => false,
        ]);

        // Salva transições permitidas
        if ($request->has('allowed_transitions')) {
            $status->allowedTransitions()->sync($request->input('allowed_transitions'));
        }

        return redirect()->route('settings.statuses.index')
            ->with('success', 'Status cadastrado com sucesso.');
    }

    /**
     * Exibe o formulário de edição de status.
     */
    public function edit(ServiceOrderStatus $status)
    {
        $statuses = ServiceOrderStatus::where('id', '!=', $status->id)->get();
        $allowedTransitionIds = $status->allowedTransitions()->pluck('to_status_id')->toArray();
        return view('settings.statuses.edit', compact('status', 'statuses', 'allowedTransitionIds'));
    }

    /**
     * Atualiza um status existente.
     */
    public function update(Request $request, ServiceOrderStatus $status)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:service_order_statuses,name,' . $status->id,
            'color' => 'required|string|max:50',
            'max_stay_minutes' => 'nullable|integer|min:1',
            'expected_time_minutes' => 'nullable|integer|min:1',
        ]);

        $isOpen = $request->boolean('is_open_state');
        $isCompleted = $request->boolean('is_completed_state');
        $isCancelled = $request->boolean('is_cancelled_state');

        // Validações de exclusividade lógica
        if ($isCompleted && $isCancelled) {
            throw ValidationException::withMessages(['is_completed_state' => 'Um status não pode ser de conclusão e de cancelamento ao mesmo tempo.']);
        }
        if (($isCompleted || $isCancelled) && $isOpen) {
            throw ValidationException::withMessages(['is_open_state' => 'Um status de conclusão ou cancelamento não pode ser um estado aberto.']);
        }

        // Se is_completed_state mudou de true para false, verifica se resta algum
        if (!$isCompleted && $status->is_completed_state) {
            if (ServiceOrderStatus::where('id', '!=', $status->id)->where('is_completed_state', true)->count() === 0) {
                throw ValidationException::withMessages(['is_completed_state' => 'Deve haver exatamente um status com estado de conclusão. Você não pode desmarcar este sem marcar outro antes.']);
            }
        }

        // Se mudou de false para true, desmarca o outro
        if ($isCompleted && !$status->is_completed_state) {
            ServiceOrderStatus::where('id', '!=', $status->id)->update(['is_completed_state' => false]);
        }

        // Se is_cancelled_state mudou de true para false, verifica se resta algum
        if (!$isCancelled && $status->is_cancelled_state) {
            if (ServiceOrderStatus::where('id', '!=', $status->id)->where('is_cancelled_state', true)->count() === 0) {
                throw ValidationException::withMessages(['is_cancelled_state' => 'Deve haver exatamente um status com estado de cancelamento. Você não pode desmarcar este sem marcar outro antes.']);
            }
        }

        // Se mudou de false para true, desmarca o outro
        if ($isCancelled && !$status->is_cancelled_state) {
            ServiceOrderStatus::where('id', '!=', $status->id)->update(['is_cancelled_state' => false]);
        }

        $status->update([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'color' => $data['color'],
            'max_stay_minutes' => $data['max_stay_minutes'] ?? null,
            'expected_time_minutes' => $data['expected_time_minutes'] ?? null,
            'is_open_state' => $isOpen,
            'is_completed_state' => $isCompleted,
            'is_cancelled_state' => $isCancelled,
        ]);

        // Salva transições permitidas
        if ($request->has('allowed_transitions')) {
            $status->allowedTransitions()->sync($request->input('allowed_transitions'));
        } else {
            $status->allowedTransitions()->detach();
        }

        return redirect()->route('settings.statuses.index')
            ->with('success', 'Status atualizado com sucesso.');
    }

    /**
     * Exclui um status (SoftDelete).
     */
    public function destroy(ServiceOrderStatus $status)
    {
        if ($status->is_system) {
            return redirect()->back()->with('error', 'Status do sistema não podem ser excluídos.');
        }

        if ($status->is_completed_state || $status->is_cancelled_state) {
            return redirect()->back()->with('error', 'Não é possível excluir o status principal de conclusão ou cancelamento.');
        }

        $status->delete();

        return redirect()->route('settings.statuses.index')
            ->with('success', 'Status excluído com sucesso.');
    }
}
