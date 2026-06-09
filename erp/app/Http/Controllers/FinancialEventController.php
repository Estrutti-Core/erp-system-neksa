<?php

namespace App\Http\Controllers;

use App\Models\FinancialEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialEventController extends Controller
{
    public function index(Request $request): View
    {
        // Apenas admin tem acesso total aos logs imutáveis do financeiro
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Acesso restrito ao administrador do sistema.');
        }

        $query = FinancialEvent::query()->with('user');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->input('event_type'));
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [$request->input('start_date'), $request->input('end_date')]);
        }

        $events = $query->latest()->paginate(25)->withQueryString();
        $users = User::orderBy('name')->get();

        return view('financial.audit', compact('events', 'users'));
    }
}
