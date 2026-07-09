<?php

namespace App\Http\Controllers;

use App\Events\UserChanged;
use App\Events\RealtimeActivityLogged;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\TablePagination;
use Illuminate\Http\Request;
use App\Support\FlexibleSearch;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UserController extends Controller
{
    private function resolveUserActiveState(?int $employeeId, bool $default = true): bool
    {
        if (!$employeeId) {
            return $default;
        }

        $employee = Employee::find($employeeId);

        if (!$employee) {
            return $default;
        }

        return $employee->employment_status !== 'Inactivo';
    }

    private function refreshAuthenticatedSession(Request $request, User $user): void
    {
        if ((int) Auth::id() !== (int) $user->id) {
            return;
        }

        $guard = Auth::guard('web');
        $freshUser = $user->fresh(['role.permissions', 'permissions', 'branches']);

        if (!$freshUser) {
            return;
        }

        $guard->setUser($freshUser);

        $request->session()->put([
            'password_hash_web' => method_exists($guard, 'hashPasswordForCookie')
                ? $guard->hashPasswordForCookie($freshUser->getAuthPassword())
                : $freshUser->getAuthPassword(),
        ]);
    }

    private function requiresSalesBranchesByPermissionIds(array $permissionIds = []): bool
    {
        if (empty($permissionIds)) {
            return false;
        }

        return Permission::query()
            ->whereIn('id', $permissionIds)
            ->where('name', 'like', 'sales.%')
            ->exists();
    }

    private function requiresBranchAssignments(Role $role, array $permissionIds = []): bool
    {
        if ($role->name === 'Administrador') {
            return false;
        }

        if ($role->name === 'Ventas') {
            return true;
        }

        if ($this->requiresSalesBranchesByPermissionIds($permissionIds)) {
            return true;
        }

        if (empty($permissionIds)) {
            return false;
        }

        return Permission::query()
            ->whereIn('id', $permissionIds)
            ->where(function ($query) {
                $query
                    ->where('name', 'like', 'inventory.products.%')
                    ->orWhere('name', 'like', 'inventory.branches.%')
                    ->orWhere('name', 'like', 'inventory.purchase-reports.%')
                    ->orWhere('name', 'like', 'audits.physical-counts.%')
                    ->orWhere('name', 'like', 'sales.%')
                    ->orWhereIn('name', [
                        'inventory.view',
                        'inventory.create',
                        'inventory.update',
                        'inventory.delete',
                    ]);
            })
            ->exists();
    }

    private function shouldPersistBranchAssignments(Role $role): bool
    {
        return $role->name !== 'Administrador';
    }

    private function visiblePermissionsQuery()
    {
        return Permission::query()
            ->where('name', 'not like', 'roles.%');
    }

    private function syncUserPermissionOverrides(User $user, Role $role, array $finalPermissionIds = []): void
    {
        $rolePermissionIds = $role->permissions()
            ->pluck('permissions.id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $finalPermissionIds = collect($finalPermissionIds)
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $allowedPermissionIds = $finalPermissionIds
            ->diff($rolePermissionIds)
            ->values();

        $deniedPermissionIds = $rolePermissionIds
            ->diff($finalPermissionIds)
            ->values();

        $syncPayload = [];

        foreach ($allowedPermissionIds as $permissionId) {
            $syncPayload[$permissionId] = ['mode' => 'allow'];
        }

        foreach ($deniedPermissionIds as $permissionId) {
            $syncPayload[$permissionId] = ['mode' => 'deny'];
        }

        $user->permissions()->sync($syncPayload);
    }

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

        $search = trim((string) $request->input('search', ''));
        $perPage = TablePagination::resolvePerPage($request, 50);
        $userStatus = trim((string) $request->input('user_status', ''));
        $statusFilter = trim((string) $request->input('status', ''));
        $roleFilter = trim((string) $request->input('role', ''));

        $users = User::with([
            'employee:id,first_name,last_name,email,employment_status',
            'role.permissions' => fn ($query) => $query->where('name', 'not like', 'roles.%'),
            'permissions' => fn ($query) => $query->where('name', 'not like', 'roles.%'),
            'branches',
        ])
            ->select([
                'id',
                'employee_id',
                'name',
                'email',
                'role_id',
                'is_active',
            ])
            ->when($search, function ($query) use ($search) {
                FlexibleSearch::apply($query, $search, function ($subQuery, $phrase, $terms) {
                    FlexibleSearch::orWhereColumns($subQuery, [
                        'name',
                        'email',
                    ], $phrase, $terms);
                    FlexibleSearch::orWhereHasColumns($subQuery, 'role', [
                        'name',
                    ], $phrase, $terms);
                });
            })
            ->when($userStatus === 'without_user', function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->when($statusFilter === 'active', function ($query) {
                $query->where('is_active', true)
                    ->where(function ($statusQuery) {
                        $statusQuery->whereNull('employee_id')
                            ->orWhereHas('employee', function ($employeeQuery) {
                                $employeeQuery->where('employment_status', '!=', 'Inactivo');
                            });
                    });
            })
            ->when($statusFilter === 'inactive', function ($query) {
                $query->where(function ($statusQuery) {
                    $statusQuery->where('is_active', false)
                        ->orWhereHas('employee', function ($employeeQuery) {
                            $employeeQuery->where('employment_status', 'Inactivo');
                        });
                });
            })
            ->when($roleFilter !== '', function ($query) use ($roleFilter) {
                if ($roleFilter === 'without_role') {
                    $query->whereNull('role_id');

                    return;
                }

                $query->where('role_id', $roleFilter);
            })
            ->orderBy('id', 'desc')
            ->get();

        $employees = Employee::doesntHave('user')
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
            ->when($userStatus === 'with_user', function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->when($statusFilter === 'active', function ($query) {
                $query->where('employment_status', '!=', 'Inactivo');
            })
            ->when($statusFilter === 'inactive', function ($query) {
                $query->where('employment_status', 'Inactivo');
            })
            ->when($roleFilter !== '', function ($query) use ($roleFilter) {
                if ($roleFilter !== 'without_role') {
                    $query->whereRaw('1 = 0');
                }
            })
            ->orderBy('id', 'desc')
            ->get();

        $combinedRows = $users
            ->map(function (User $user) {
                $user->setAttribute('row_id', 'user-' . $user->id);
                $user->setAttribute('entity_type', 'user');
                $user->setAttribute('has_user', true);
                $user->setAttribute('sort_group', 0);
                $user->setAttribute('sort_name', mb_strtolower(trim(implode(' ', array_filter([
                    data_get($user, 'employee.first_name'),
                    data_get($user, 'employee.last_name'),
                    $user->name,
                ])))));

                return $user;
            })
            ->concat(
                $employees->map(function (Employee $employee) {
                    $employee->setAttribute('row_id', 'employee-' . $employee->id);
                    $employee->setAttribute('entity_type', 'employee');
                    $employee->setAttribute('has_user', false);
                    $employee->setAttribute('sort_group', 1);
                    $employee->setAttribute('sort_name', mb_strtolower(trim(implode(' ', array_filter([
                        $employee->first_name,
                        $employee->last_name,
                    ])))));

                    return $employee;
                })
            )
            ->sortBy([
                ['sort_group', 'asc'],
                ['sort_name', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $items = $combinedRows->forPage($currentPage, $perPage)->values();

        $recordsPaginator = new LengthAwarePaginator(
            $items,
            $combinedRows->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $recordsDB = $recordsPaginator->toArray();

        return Inertia::render('Systems/Users', [
            'recordsDB' => $recordsDB,

            'roles' => Role::with('permissions')
                ->with(['permissions' => fn ($query) => $query->where('name', 'not like', 'roles.%')])
                ->orderBy('name')
                ->get(),

            'permissions' => $this->visiblePermissionsQuery()
                ->orderBy('name')
                ->get(),

            'branches' => Branch::where('active', true)
                ->orderBy('name')
                ->get(),

            'filters' => [
                'search' => $search,
                'perPage' => $perPage,
                'userStatus' => $userStatus,
                'status' => $statusFilter,
                'role' => $roleFilter,
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
        $finalPermissionIds = $validated['permissions'] ?? [];
        $requiresBranchAssignments = $this->requiresBranchAssignments($role, $finalPermissionIds);

        if ($requiresBranchAssignments && empty($validated['branch_ids'])) {
            return back()->withErrors([
                'branch_ids' => 'Debes seleccionar al menos una sucursal para este usuario.',
            ])->withInput();
        }

        $user = User::create([
            'employee_id' => $validated['employee_id'] ?? null,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $validated['role_id'],
            'is_active' => $this->resolveUserActiveState($validated['employee_id'] ?? null),
        ]);

        $this->syncUserPermissionOverrides(
            $user,
            $role,
            $finalPermissionIds,
        );

        $user->branches()->sync(
            $this->shouldPersistBranchAssignments($role)
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
        $finalPermissionIds = $validated['permissions'] ?? [];
        $requiresBranchAssignments = $this->requiresBranchAssignments($role, $finalPermissionIds);

        if ($requiresBranchAssignments && empty($validated['branch_ids'])) {
            return back()->withErrors([
                'branch_ids' => 'Debes seleccionar al menos una sucursal para este usuario.',
            ])->withInput();
        }

        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role_id' => $validated['role_id'],
            'is_active' => $this->resolveUserActiveState($user->employee_id, $user->is_active ?? true),
        ];

        if (!empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        $user->update($userData);

        $this->syncUserPermissionOverrides(
            $user,
            $role,
            $finalPermissionIds,
        );

        $user->branches()->sync(
            $this->shouldPersistBranchAssignments($role)
                ? ($validated['branch_ids'] ?? [])
                : []
        );

        $this->refreshAuthenticatedSession($request, $user);

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
