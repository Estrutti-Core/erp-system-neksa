<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SalesListController extends Controller
{
    /**
     * Display sales list with filters.
     */
    public function index(Request $request)
    {
        $query = Sale::with(['items.product', 'customer', 'user', 'paymentMethod']);

        // Apply payment method filter
        if ($request->has('payment_method_id') && $request->payment_method_id !== '') {
            $query->where('payment_method_id', $request->payment_method_id);
        }

        // Apply date range filter if needed
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $sales = $query->orderBy('created_at', 'desc')->get();

        $paymentMethods = PaymentMethod::where('active', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Vendas/SalesListPage', [
            'sales' => $sales,
            'paymentMethods' => $paymentMethods,
        ]);
    }
}
