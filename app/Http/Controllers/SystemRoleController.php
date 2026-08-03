<?php

namespace App\Http\Controllers;

use App\Events\RealtimeActivityLogged;
use App\Events\UserChanged;
use App\Http\Controllers\Concerns\ValidatesRecordVersion;
use App\Models\Permission;
use App\Models\Role;
use App\Support\SystemPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SystemRoleController extends SystemAdministrationController
{
    use ValidatesRecordVersion;

    public function index(Request $request)
    {
        abort_unless(
            $request->user()?->hasPermission(SystemPermission::ROLES_MANAGE)
                || $request->user()?->hasPermission(SystemPermission::PERMISSIONS_MANAGE),
            403
        );

        return Inertia::render('SystemAdministration/Roles', [
            'roles' => Role::with('permissions')->orderBy('name')->get(),
            'permissions' => Permission::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $this->authorizePermission($request, SystemPermission::PERMISSIONS_MANAGE);
        $data = $request->validate([
            'permission_ids' => ['required', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        if ($role->name === 'Super Administrador'
            && !$request->user()->hasPermission(SystemPermission::SUPER_ADMINISTRATORS_MANAGE)) {
            abort(403);
        }

        $role = DB::transaction(function () use ($request, $role, $data) {
            $role = $this->lockCurrentVersion($request, $role);
            $role->permissions()->sync($data['permission_ids']);
            $role->touch();

            return $role;
        });
        $role->unsetRelation('permissions');

        try {
            $role->users()
                ->with(['role.permissions', 'permissions'])
                ->eachById(function ($user): void {
                    event(new UserChanged($user, 'permissions_updated'));
                });

            event(RealtimeActivityLogged::message(
                'actualizó los permisos de',
                'el rol',
                $role->name,
                'Sistemas',
                'permissions_updated',
            ));
        } catch (\Throwable $exception) {
            report($exception);
        }

        return back()->with('success', 'Permisos del rol actualizados correctamente.');
    }
}
