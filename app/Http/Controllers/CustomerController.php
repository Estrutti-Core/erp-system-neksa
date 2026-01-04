<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $customers = Customer::orderBy('name')->get();

        return Inertia::render('Cadastros/CustomersPage', [
            'customers' => $customers,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CustomerRequest $request)
    {
        $customer = Customer::create($request->validated());

        Log::info('Customer created', ['id' => $customer->id, 'name' => $customer->name]);

        return redirect()->route('cadastros.clientes.index')
            ->with('success', 'Cliente criado com sucesso!');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CustomerRequest $request, Customer $customer)
    {
        $customer->update($request->validated());

        Log::info('Customer updated', ['id' => $customer->id, 'name' => $customer->name]);

        return redirect()->route('cadastros.clientes.index')
            ->with('success', 'Cliente atualizado com sucesso!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {
        $customerName = $customer->name;
        $customer->delete();

        Log::info('Customer deleted', ['id' => $customer->id, 'name' => $customerName]);

        return redirect()->route('cadastros.clientes.index')
            ->with('success', 'Cliente excluído com sucesso!');
    }
}
