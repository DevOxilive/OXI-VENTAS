<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Empleado;
use Inertia\Inertia;

class UserController extends Controller
{
    // 🔐 VALIDAR PERMISO
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

    // 📋 LISTAR
    public function index()
    {
        return Inertia::render('Sistemas/Empleados', [
            'empleados' => Empleado::with('user.role')
                ->orderBy('id', 'desc')
                ->get(),

            'usuarios' => User::with([
                'role.permissions',
                'permissions'
            ])
                ->orderBy('id', 'desc')
                ->get(),

            'roles' => Role::with('permissions')->get(),

            'permissions' => Permission::all(),
        ]);
    }

    // ➕ CREAR
    public function store(Request $request)
    {
        $this->checkPermission('usuarios.crear');

        $request->validate([
            'empleado_id' => 'nullable|exists:empleados,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|confirmed|min:6',
            'role_id' => 'nullable|exists:roles,id',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $user = User::create([
            'empleado_id' => $request->empleado_id,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id,
            'sucursal_id' => $request->sucursal_id,
        ]);

        // Permisos directos del usuario.
        // Si el frontend precargó permisos por rol, llegarán aquí en request.
        // Si no manda permisos, se guarda sin permisos directos.
        $user->permissions()->sync($request->permissions ?? []);

        return redirect()->route('sistemas.empleados')
            ->with('success', 'Usuario creado correctamente');
    }

    // ✏️ ACTUALIZAR
    public function update(Request $request, $id)
    {
        $this->checkPermission('usuarios.editar');

        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'role_id' => 'nullable|exists:roles,id',
             'sucursal_id' => 'required|exists:sucursales,id',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        if ($request->password) {
            $request->validate([
                'password' => 'confirmed|min:6'
            ]);
        }

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'role_id' => $request->role_id,
            'password' => $request->password
                ? Hash::make($request->password)
                : $user->password,
        ]);

        // Actualiza SOLO permisos directos de este usuario.
        // No toca permissions del rol.
        $user->permissions()->sync($request->permissions ?? []);

        return redirect()->route('sistemas.empleados')
            ->with('success', 'Usuario actualizado');
    }

    // 🗑️ ELIMINAR
    public function destroy($id)
    {
        $this->checkPermission('usuarios.eliminar');

        User::findOrFail($id)->delete();

        return redirect()->route('sistemas.empleados')
            ->with('success', 'Usuario eliminado');
    }
}
