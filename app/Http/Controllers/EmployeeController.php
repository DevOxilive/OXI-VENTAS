<?php

namespace App\Http\Controllers;

use App\Events\UserChanged;
use Inertia\Inertia;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use App\Events\EmployeeChanged;
use App\Events\RealtimeActivityLogged;
use App\Exports\EmployeeExport;
use App\Support\FlexibleSearch;
use App\Support\TablePagination;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeController extends Controller
{
    private function syncLinkedUserStatus(Employee $employee): void
    {
        $user = $employee->user;

        if (!$user) {
            return;
        }

        $isActive = $employee->employment_status !== 'Inactivo';

        $user->forceFill([
            'is_active' => $isActive,
        ])->save();

        if (!$isActive) {
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->delete();
        }

        broadcast(new UserChanged($user, 'updated'))->toOthers();
    }

    private function deleteLinkedUser(Employee $employee): void
    {
        $user = $employee->user;

        if (!$user) {
            return;
        }

        $user->load(['role', 'permissions', 'branches']);

        try {
            broadcast(new UserChanged($user, 'deleted'))->toOthers();
            event(RealtimeActivityLogged::message('elimino', 'el usuario', $user->email, 'Sistemas', 'deleted'));
        } catch (\Throwable $e) {
            report($e);
        }

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->delete();

        $user->permissions()->detach();
        $user->branches()->detach();
        $user->assignedPhysicalCounts()->detach();
        $user->delete();
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $perPage = TablePagination::resolvePerPage($request, 50);

        $employmentStatus = trim((string) $request->input('employmentStatus', ''));
        $department = trim((string) $request->input('department', ''));
        $position = trim((string) $request->input('position', ''));
        $startDateFrom = trim((string) $request->input('startDateFrom', ''));
        $startDateTo = trim((string) $request->input('startDateTo', ''));

        $employeesDB = Employee::query()
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
            ->when($employmentStatus, function ($query) use ($employmentStatus) {
                $query->where('employment_status', $employmentStatus);
            })
            ->when($department, function ($query) use ($department) {
                $query->where('department', $department);
            })
            ->when($position, function ($query) use ($position) {
                $query->where('position', 'like', "%{$position}%");
            })
            ->when($startDateFrom, function ($query) use ($startDateFrom) {
                $query->whereDate('start_date', '>=', $startDateFrom);
            })
            ->when($startDateTo, function ($query) use ($startDateTo) {
                $query->whereDate('start_date', '<=', $startDateTo);
            })
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString()
            ->through(function ($employee) {
                return [
                    'id' => $employee->id,
                    'firstName' => $employee->first_name,
                    'lastName' => $employee->last_name,
                    'email' => $employee->email,
                    'phone' => $employee->phone,
                    'street' => $employee->street,
                    'externalNumber' => $employee->external_number,
                    'internalNumber' => $employee->internal_number,
                    'postalCode' => $employee->postal_code,
                    'neighborhood' => $employee->neighborhood,
                    'municipality' => $employee->municipality,
                    'addressState' => $employee->address_state,
                    'mapsUrl' => $employee->maps_url,
                    'startDate' => $employee->start_date,
                    'employmentStatus' => $employee->employment_status,
                    'photo' => $employee->photo,
                    'position' => $employee->position,
                    'department' => $employee->department,
                    'bank' => $employee->bank,
                    'accountNumber' => $employee->account_number,
                    'educationLevel' => $employee->education_level,
                    'specialty' => $employee->specialty,
                    'contractType' => $employee->contract_type,
                    'seniority' => $employee->seniority,
                    'nss' => $employee->nss,
                    'rfc' => $employee->rfc,
                ];
            });

        return Inertia::render('HumanResources/Employees', [
            'employeesDB' => $employeesDB,
            'filterOptions' => [
                'positions' => Employee::query()
                    ->whereNotNull('position')
                    ->where('position', '!=', '')
                    ->distinct()
                    ->orderBy('position')
                    ->pluck('position')
                    ->map(fn ($position) => [
                        'value' => $position,
                        'label' => $position,
                    ])
                    ->values(),
                'departments' => Employee::query()
                    ->whereNotNull('department')
                    ->where('department', '!=', '')
                    ->distinct()
                    ->orderBy('department')
                    ->pluck('department')
                    ->map(fn ($department) => [
                        'value' => $department,
                        'label' => $department,
                    ])
                    ->values(),
                'statuses' => Employee::query()
                    ->whereNotNull('employment_status')
                    ->where('employment_status', '!=', '')
                    ->distinct()
                    ->orderBy('employment_status')
                    ->pluck('employment_status')
                    ->map(fn ($status) => [
                        'value' => $status,
                        'label' => $status,
                    ])
                    ->values(),
            ],
            'filters' => [
                'search' => $search,
                'perPage' => $perPage,
                'employmentStatus' => $employmentStatus,
                'department' => $department,
                'position' => $position,
                'startDateFrom' => $startDateFrom,
                'startDateTo' => $startDateTo,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'firstName' => 'required',
            'lastName' => 'required',
            'email' => 'required|email|unique:employees,email',
            'position' => 'required',
            'department' => 'required',
        ]);

        $employee = Employee::create([
            'first_name' => $request->firstName,
            'last_name' => $request->lastName,
            'email' => $request->email,
            'phone' => $request->phone,
            'street' => $request->street,
            'external_number' => $request->externalNumber,
            'internal_number' => $request->internalNumber,
            'postal_code' => $request->postalCode,
            'neighborhood' => $request->neighborhood,
            'municipality' => $request->municipality,
            'address_state' => $request->addressState,
            'maps_url' => $request->mapsUrl,
            'start_date' => $request->startDate,
            'employment_status' => $request->employmentStatus,
            'photo' => null,
            'position' => $request->position,
            'department' => $request->department,
            'bank' => $request->bank,
            'account_number' => $request->accountNumber,
            'education_level' => $request->educationLevel,
            'specialty' => $request->specialty,
            'contract_type' => $request->contractType,
            'seniority' => $request->seniority,
            'nss' => $request->nss,
            'rfc' => $request->rfc,
        ]);

        broadcast(new EmployeeChanged('created', $employee->id))->toOthers();
        event(RealtimeActivityLogged::message('creó', 'el empleado', trim("{$employee->first_name} {$employee->last_name}"), 'Recursos humanos', 'created'));

        return redirect()->back();
    }

    public function update(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $request->validate([
            'firstName' => 'required',
            'lastName' => 'required',
            'email' => 'required|email|unique:employees,email,' . $id,
            'position' => 'required',
            'department' => 'required',
        ]);

        $employee->update([
            'first_name' => $request->firstName,
            'last_name' => $request->lastName,
            'email' => $request->email,
            'phone' => $request->phone,
            'street' => $request->street,
            'external_number' => $request->externalNumber,
            'internal_number' => $request->internalNumber,
            'postal_code' => $request->postalCode,
            'neighborhood' => $request->neighborhood,
            'municipality' => $request->municipality,
            'address_state' => $request->addressState,
            'maps_url' => $request->mapsUrl,
            'start_date' => $request->startDate,
            'employment_status' => $request->employmentStatus,
            'position' => $request->position,
            'department' => $request->department,
            'bank' => $request->bank,
            'account_number' => $request->accountNumber,
            'education_level' => $request->educationLevel,
            'specialty' => $request->specialty,
            'contract_type' => $request->contractType,
            'seniority' => $request->seniority,
            'nss' => $request->nss,
            'rfc' => $request->rfc,
        ]);

        $employee->load('user');
        $this->syncLinkedUserStatus($employee);

        broadcast(new EmployeeChanged('updated', $employee->id))->toOthers();
        event(RealtimeActivityLogged::message('actualizó', 'el empleado', trim("{$employee->first_name} {$employee->last_name}"), 'Recursos humanos', 'updated'));

        return redirect()->back();
    }

    public function destroy($id)
    {
        $employee = Employee::with('user')->findOrFail($id);
        $employeeId = $employee->id;
        $employeeName = trim("{$employee->first_name} {$employee->last_name}");

        $this->deleteLinkedUser($employee);
        $employee->delete();

        broadcast(new EmployeeChanged('deleted', $employeeId))->toOthers();
        event(RealtimeActivityLogged::message('eliminó', 'el empleado', $employeeName, 'Recursos humanos', 'deleted'));

        return redirect()->back();
    }

    public function exportExcel(Request $request)
    {
        $employmentStatus = $request->employmentStatus;
        $department = $request->department;
        $position = $request->position;
        $search = $request->search;
        $startDateFrom = $request->startDateFrom;
        $startDateTo = $request->startDateTo;

        $fileName = 'employees_' . now()->format('d_m_Y_H_i_s') . '.xlsx';

        return Excel::download(
            new EmployeeExport($employmentStatus, $department, $position, $search, $startDateFrom, $startDateTo),
            $fileName
        );
    }
}
