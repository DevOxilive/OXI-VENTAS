<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Empleado;

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
    return inertia('Sistemas/Empleados', [

        // 👇 EMPLEADOS (tabla empleados REAL)
        'empleados' => Empleado::select(
            'id',
            'nombre',
            'apellido',
            'correo'
        )->get(),

        // 👇 USUARIOS (tabla users)
        'usuarios' => User::with(['role', 'permissions'])
            ->select('id','name','email','role_id')
            ->get(),

        'roles' => Role::all(),
        'permissions' => Permission::all(),
    ]);
}

    // ➕ CREAR
    public function store(Request $request)
    {
        $this->checkPermission('usuarios.crear');

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|confirmed|min:6',
            'role_id' => 'nullable|exists:roles,id',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id
        ]);

        $user->permissions()->sync($request->input('permissions', []));

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
                : $user->password
        ]);

        $user->permissions()->sync($request->input('permissions', []));

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