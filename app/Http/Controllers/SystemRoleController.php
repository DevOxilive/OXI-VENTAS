<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Support\SystemPermission;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SystemRoleController extends SystemAdministrationController
{
    public function index(Request $request)
    {
        $this->authorizePermission($request, SystemPermission::ROLES_MANAGE);

        return Inertia::render('SystemAdministration/Roles', [
            'roles' => Role::with('permissions')->orderBy('name')->get(),
            'permissions' => Permission::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $this->authorizePermission($request, SystemPermission::ROLES_MANAGE);
        $data = $request->validate([
            'permission_ids' => ['required', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        if ($role->name === 'Super Administrador'
            && !$request->user()->hasPermission(SystemPermission::SUPER_ADMINISTRATORS_MANAGE)) {
            abort(403);
        }

        $role->permissions()->sync($data['permission_ids']);

        return back()->with('success', 'Permisos del rol actualizados correctamente.');
    }
}
