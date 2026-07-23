<?php

namespace App\Http\Controllers;

use App\Events\OrganizationStructureChanged;
use App\Events\RealtimeActivityLogged;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PositionController extends Controller
{
    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        $position = Position::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'department_id' => $data['departmentId'],
            'active' => $data['active'] ?? true,
        ]);

        broadcast(new OrganizationStructureChanged('position', 'created', $position->id))->toOthers();
        event(RealtimeActivityLogged::message('creó', 'el puesto', $position->name, 'Capital Humano', 'created'));

        return back()->with('success', 'Puesto creado correctamente.');
    }

    public function update(Request $request, Position $position)
    {
        $data = $this->validatedData($request, $position);

        $position->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'department_id' => $data['departmentId'],
            'active' => $data['active'] ?? $position->active,
        ]);

        broadcast(new OrganizationStructureChanged('position', 'updated', $position->id))->toOthers();
        event(RealtimeActivityLogged::message('actualizó', 'el puesto', $position->name, 'Capital Humano', 'updated'));

        return back()->with('success', 'Puesto actualizado correctamente.');
    }

    public function destroy(Position $position)
    {
        if ($position->employees()->exists()) {
            throw ValidationException::withMessages([
                'position' => 'No puedes eliminar el puesto mientras tenga empleados asignados.',
            ]);
        }

        $positionId = $position->id;
        $positionName = $position->name;
        $position->delete();

        broadcast(new OrganizationStructureChanged('position', 'deleted', $positionId))->toOthers();
        event(RealtimeActivityLogged::message('eliminó', 'el puesto', $positionName, 'Capital Humano', 'deleted'));

        return back()->with('success', 'Puesto eliminado correctamente.');
    }

    private function validatedData(Request $request, ?Position $position = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('positions', 'name')
                    ->where(fn ($query) => $query->where('department_id', $request->integer('departmentId')))
                    ->ignore($position?->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'departmentId' => ['required', 'integer', 'exists:departments,id'],
            'active' => ['sometimes', 'boolean'],
        ]);
    }
}
