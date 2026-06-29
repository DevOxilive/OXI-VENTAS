<?php

namespace App\Http\Controllers;

use App\Events\UserChanged;
use App\Events\RealtimeActivityLogged;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use App\Support\FlexibleSearch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UserController extends Controller
{
    private function checkPermission(string $permission): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (!$user) {
            abort(401);
        }

        $user->load(['permissions']);

        if (!$user->hasPermission($permission)) {
            abort(403, 'No tienes permiso');
        }
    }

    private function checkAnyPermission(array $permissions): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (!$user) {
            abort(401);
        }

        $user->load(['permissions']);

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return;
            }
        }

        abort(403, 'No tienes permiso');
    }

    public function index(Request $request)
    {
        $this->checkAnyPermission([
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
        ]);

        $search = $request->input('search');
        $perPage = (int) $request->input('per_page', 50);

        if (!in_array($perPage, [10, 25, 50, 100])) {
            $perPage = 50;
        }
        $view = $request->input('view', 'users');

        $usersDB = User::with([
            'role.permissions',
            'permissions',
            'branches',
        ])
            ->select([
                'id',
                'employee_id',
                'name',
                'email',
                'role_id',
            ])
            ->when($search, function ($query) use ($search) {
                FlexibleSearch::apply($query, $search, function ($subQuery, $phrase, $terms) {
                    FlexibleSearch::orWhereColumns($subQuery, [
                        'name',
                        'email',
                    ], $phrase, $terms);
                });
            })
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $employeesDB = Employee::doesntHave('user')
            ->when($search, function ($query) use ($search) {
                FlexibleSearch::apply($query, $search, function ($subQuery, $phrase, $terms) {
                    FlexibleSearch::orWhereColumns($subQuery, [
                        'first_name',
                        'last_name',
                        'email',
                        'phone',
                        'position',
                        'department',
                        'nss',
                        'rfc',
                    ], $phrase, $terms);
                });
            })
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Systems/Users', [
            'employeesDB' => $employeesDB,
            'usersDB' => $usersDB,

            'roles' => Role::with('permissions')
                ->orderBy('name')
                ->get(),

            'permissions' => Permission::orderBy('name')->get(),

            'branches' => Branch::where('active', true)
                ->orderBy('name')
                ->get(),

            'filters' => [
                'search' => $search,
                'perPage' => $perPage,
                'view' => $view,
            ],
        ]);
    }
    public function store(Request $request)
    {
        $this->checkPermission('users.create');

        $validated = $request->validate([
            'employee_id' => ['nullable', 'exists:employees,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:6'],
            'role_id' => ['required', 'exists:roles,id'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['exists:branches,id'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role = Role::findOrFail($validated['role_id']);

        if ($role->name === 'Ventas' && empty($validated['branch_ids'])) {
            return back()->withErrors([
                'branch_ids' => 'Debes seleccionar al menos una sucursal para el vendedor.',
            ])->withInput();
        }

        $user = User::create([
            'employee_id' => $validated['employee_id'] ?? null,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $validated['role_id'],
        ]);

        $user->permissions()->sync($validated['permissions'] ?? []);

        $user->branches()->sync(
            $role->name === 'Ventas'
            ? ($validated['branch_ids'] ?? [])
            : []
        );

        $user->load(['role', 'permissions', 'branches']);

        broadcast(new UserChanged($user, 'created'))->toOthers();
        event(RealtimeActivityLogged::message('creó', 'el usuario', $user->email, 'Sistemas', 'created'));

        return redirect()
            ->route('systems.users.index')
            ->with('success', 'Usuario creado correctamente');
    }

    public function update(Request $request, User $user)
    {
        $this->checkPermission('users.update');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,' . $user->id],
            'role_id' => ['required', 'exists:roles,id'],
            'password' => ['nullable', 'confirmed', 'min:6'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['exists:branches,id'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role = Role::findOrFail($validated['role_id']);

        if ($role->name === 'Ventas' && empty($validated['branch_ids'])) {
            return back()->withErrors([
                'branch_ids' => 'Debes seleccionar al menos una sucursal para el vendedor.',
            ])->withInput();
        }

        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role_id' => $validated['role_id'],
        ];

        if (!empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        $user->update($userData);

        $user->permissions()->sync($validated['permissions'] ?? []);

        $user->branches()->sync(
            $role->name === 'Ventas'
            ? ($validated['branch_ids'] ?? [])
            : []
        );

        $user->load(['role', 'permissions', 'branches']);

        try {
            broadcast(new UserChanged($user, 'updated'))->toOthers();
            event(RealtimeActivityLogged::message('actualizó', 'el usuario', $user->email, 'Sistemas', 'updated'));
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()
            ->route('systems.users.index')
            ->with('success', 'Usuario actualizado correctamente');
    }

    public function destroy(User $user)
    {
        $this->checkPermission('users.delete');

        $user->load(['role', 'permissions', 'branches']);
        $userEmail = $user->email;

        try {
            broadcast(new UserChanged($user, 'deleted'))->toOthers();
            event(RealtimeActivityLogged::message('eliminó', 'el usuario', $userEmail, 'Sistemas', 'deleted'));
        } catch (\Throwable $e) {
            report($e);
        }

        $user->permissions()->detach();
        $user->branches()->detach();
        $user->delete();

        return redirect()
            ->route('systems.users.index')
            ->with('success', 'Usuario eliminado correctamente');
    }
}
