<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Empleado;
use App\Models\Sucursal;
use Inertia\Inertia;

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
            'empleados' => Empleado::with('user.role')
                ->orderBy('id', 'desc')
                ->get(),

            'usuarios' => User::with([
                'role.permissions',
                'permissions',
                'sucursales',
            ])
                ->orderBy('id', 'desc')
                ->get(),

            'roles' => Role::with('permissions')->get(),

            'permissions' => Permission::all(),

            'sucursales' => Sucursal::where('activa', true)
                ->orderBy('nombre')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->checkPermission('usuarios.crear');

        $request->validate([
            'empleado_id' => 'nullable|exists:empleados,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|confirmed|min:6',
            'role_id' => 'required|exists:roles,id',

            // Varias sucursales para rol Ventas
            'sucursal_ids' => 'nullable|array',
            'sucursal_ids.*' => 'exists:sucursales,id',

            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::findOrFail($request->role_id);

        if ($role->name === 'Ventas' && empty($request->sucursal_ids)) {
            return back()->withErrors([
                'sucursal_ids' => 'Debes seleccionar al menos una sucursal para el vendedor.',
            ])->withInput();
        }

        $user = User::create([
            'empleado_id' => $request->empleado_id,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id,
        ]);

        // Permisos directos
        $user->permissions()->sync($request->permissions ?? []);

        // Sucursales permitidas solo para Ventas
        if ($role->name === 'Ventas') {
            $user->sucursales()->sync($request->sucursal_ids ?? []);
        } else {
            $user->sucursales()->sync([]);
        }

        return redirect()->route('sistemas.empleados')
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

            // Varias sucursales para rol Ventas
            'sucursal_ids' => 'nullable|array',
            'sucursal_ids.*' => 'exists:sucursales,id',

            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        if ($request->password) {
            $request->validate([
                'password' => 'confirmed|min:6',
            ]);
        }

        $role = Role::findOrFail($request->role_id);

        if ($role->name === 'Ventas' && empty($request->sucursal_ids)) {
            return back()->withErrors([
                'sucursal_ids' => 'Debes seleccionar al menos una sucursal para el vendedor.',
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

        // Permisos directos
        $user->permissions()->sync($request->permissions ?? []);

        // Sucursales permitidas solo para Ventas
        if ($role->name === 'Ventas') {
            $user->sucursales()->sync($request->sucursal_ids ?? []);
        } else {
            $user->sucursales()->sync([]);
        }

        return redirect()->route('sistemas.empleados')
            ->with('success', 'Usuario actualizado');
    }

    public function destroy($id)
    {
        $this->checkPermission('usuarios.eliminar');

        $user = User::findOrFail($id);

        $user->permissions()->detach();
        $user->sucursales()->detach();

        $user->delete();

        return redirect()->route('sistemas.empleados')
            ->with('success', 'Usuario eliminado');
    }
}