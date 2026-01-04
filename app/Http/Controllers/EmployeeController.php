<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRequest;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $employees = Employee::orderBy('name')->get();

        return Inertia::render('Cadastros/EmployeesPage', [
            'employees' => $employees,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(EmployeeRequest $request)
    {
        $employee = Employee::create($request->validated());

        Log::info('Employee created', ['id' => $employee->id, 'name' => $employee->name]);

        return redirect()->route('cadastros.funcionarios.index')
            ->with('success', 'Funcionário criado com sucesso!');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EmployeeRequest $request, Employee $employee)
    {
        $employee->update($request->validated());

        Log::info('Employee updated', ['id' => $employee->id, 'name' => $employee->name]);

        return redirect()->route('cadastros.funcionarios.index')
            ->with('success', 'Funcionário atualizado com sucesso!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee)
    {
        $employeeName = $employee->name;
        $employee->delete();

        Log::info('Employee deleted', ['id' => $employee->id, 'name' => $employeeName]);

        return redirect()->route('cadastros.funcionarios.index')
            ->with('success', 'Funcionário excluído com sucesso!');
    }
}
