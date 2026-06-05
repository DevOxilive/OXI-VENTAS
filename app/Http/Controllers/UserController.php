<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Inertia\Inertia;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Permission;
use App\Events\UserChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    private function checkPermission($permiso)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user) {
            abort(401);
        }

        $user->load(['permissions', 'role.permissions']);

        if (!$user->hasPermission($permiso)) {
            abort(403, 'No tienes permiso');
        }
    }

    public function index()
    {
        return Inertia::render('Sistemas/Empleados', [
            'empleados' => Employee::with('user.role')
                ->orderBy('id', 'desc')
                ->get(),

            'usuarios' => User::with([
                'role.permissions',
                'permissions',
                'branches',
            ])
                ->orderBy('id', 'desc')
                ->get(),

            'roles' => Role::with('permissions')->get(),

            'permissions' => Permission::all(),

            'branches' => Branch::where('active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->checkPermission('usuarios.crear');

        $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|confirmed|min:6',
            'role_id' => 'required|exists:roles,id',

            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id',

            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::findOrFail($request->role_id);

        if ($role->name === 'Ventas' && empty($request->branch_ids)) {
            return back()->withErrors([
                'branch_ids' => 'Debes seleccionar al menos una sucursal para el vendedor.',
            ])->withInput();
        }

        $user = User::create([
            'employee_id' => $request->employee_id,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id,
        ]);

        $user->permissions()->sync($request->permissions ?? []);

        if ($role->name === 'Ventas') {
            $user->branches()->sync($request->branch_ids ?? []);
        } else {
            $user->branches()->sync([]);
        }
        $user->load(['role', 'permissions']);

        broadcast(new UserChanged($user, 'created'))->toOthers();
        return redirect()->route('systems.employees')
            ->with('success', 'Usuario creado correctamente');
    }

    public function update(Request $request, $id)
    {
        $this->checkPermission('usuarios.editar');

        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'role_id' => 'required|exists:roles,id',

            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id',

            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        if ($request->password) {
            $request->validate([
                'password' => 'confirmed|min:6',
            ]);
        }

        $role = Role::findOrFail($request->role_id);

        if ($role->name === 'Ventas' && empty($request->branch_ids)) {
            return back()->withErrors([
                'branch_ids' => 'Debes seleccionar al menos una sucursal para el vendedor.',
            ])->withInput();
        }

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'role_id' => $request->role_id,

            'password' => $request->password
                ? Hash::make($request->password)
                : $user->password,
        ]);

        $user->permissions()->sync($request->permissions ?? []);

        if ($role->name === 'Ventas') {
            $user->branches()->sync($request->branch_ids ?? []);
        } else {
            $user->branches()->sync([]);
        }
        $user->load(['role', 'permissions']);
        broadcast(new UserChanged($user, 'updated'))->toOthers();
        return redirect()->route('systems.employees')
            ->with('success', 'Usuario actualizado');
    }

    public function destroy($id)
    {
        $this->checkPermission('usuarios.eliminar');

        $user = User::findOrFail($id);

        $user->load(['role', 'permissions']);

        broadcast(new UserChanged($user, 'deleted'))->toOthers();

        $user->permissions()->detach();
        $user->branches()->detach();

        $user->delete();

        return redirect()->route('systems.employees')
            ->with('success', 'Usuario eliminado');
    }
}
