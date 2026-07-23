<?php

namespace App\Http\Controllers;

use App\Events\OrganizationStructureChanged;
use App\Events\RealtimeActivityLogged;
use App\Models\Department;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    public function index()
    {
        return Inertia::render('HumanResources/Departments', [
            'departments' => Department::query()
                ->with(['positions' => fn ($query) => $query
                    ->orderBy('name')
                    ->select(['id', 'department_id', 'name'])])
                ->withCount(['positions', 'employees'])
                ->orderBy('name')
                ->get()
                ->map(fn (Department $department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'positionsCount' => $department->positions_count,
                    'employeesCount' => $department->employees_count,
                    'positions' => $department->positions
                        ->map(fn (Position $position) => [
                            'id' => $position->id,
                            'name' => $position->name,
                        ])
                        ->values(),
                    'active' => $department->active,
                ]),
            'positions' => Position::query()
                ->with('department:id,name')
                ->withCount('employees')
                ->orderBy('name')
                ->get()
                ->map(fn (Position $position) => [
                    'id' => $position->id,
                    'name' => $position->name,
                    'description' => $position->description,
                    'departmentId' => $position->department_id,
                    'departmentName' => $position->department?->name ?? 'Sin departamento',
                    'employeesCount' => $position->employees_count,
                    'active' => $position->active,
                    'status' => $position->active ? 'Activo' : 'Inactivo',
                ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        $department = Department::create([
            'name' => $data['name'],
            'active' => true,
        ]);

        broadcast(new OrganizationStructureChanged('department', 'created', $department->id))->toOthers();
        event(RealtimeActivityLogged::message('creó', 'el departamento', $department->name, 'Capital Humano', 'created'));

        return back()->with('success', 'Departamento creado correctamente.');
    }

    public function update(Request $request, Department $department)
    {
        $data = $this->validatedData($request, $department);

        $department->update([
            'name' => $data['name'],
        ]);

        broadcast(new OrganizationStructureChanged('department', 'updated', $department->id))->toOthers();
        event(RealtimeActivityLogged::message('actualizó', 'el departamento', $department->name, 'Capital Humano', 'updated'));

        return back()->with('success', 'Departamento actualizado correctamente.');
    }

    public function destroy(Department $department)
    {
        if ($department->positions()->exists()) {
            throw ValidationException::withMessages([
                'department' => 'No puedes eliminar el departamento mientras tenga puestos registrados.',
            ]);
        }

        $departmentId = $department->id;
        $departmentName = $department->name;
        $department->delete();

        broadcast(new OrganizationStructureChanged('department', 'deleted', $departmentId))->toOthers();
        event(RealtimeActivityLogged::message('eliminó', 'el departamento', $departmentName, 'Capital Humano', 'deleted'));

        return back()->with('success', 'Departamento eliminado correctamente.');
    }

    private function validatedData(Request $request, ?Department $department = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('departments', 'name')->ignore($department?->id),
            ],
        ]);
    }
}
