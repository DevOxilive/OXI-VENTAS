<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Employee;
use Illuminate\Http\Request;
use App\Events\EmployeeChanged;
use App\Events\RealtimeActivityLogged;
use App\Exports\EmployeeExport;
use App\Support\FlexibleSearch;
use App\Support\TablePagination;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $perPage = TablePagination::resolvePerPage($request, 50);

        $employmentStatus = $request->input('employmentStatus');
        $department = $request->input('department');
        $position = $request->input('position');

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
            'filters' => [
                'search' => $search,
                'perPage' => $perPage,
                'employmentStatus' => $employmentStatus,
                'department' => $department,
                'position' => $position,
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

        broadcast(new EmployeeChanged('updated', $employee->id))->toOthers();
        event(RealtimeActivityLogged::message('actualizó', 'el empleado', trim("{$employee->first_name} {$employee->last_name}"), 'Recursos humanos', 'updated'));

        return redirect()->back();
    }

    public function destroy($id)
    {
        $employee = Employee::findOrFail($id);
        $employeeId = $employee->id;
        $employeeName = trim("{$employee->first_name} {$employee->last_name}");

        $employee->delete();

        broadcast(new EmployeeChanged('deleted', $employeeId))->toOthers();
        event(RealtimeActivityLogged::message('eliminó', 'el empleado', $employeeName, 'Recursos humanos', 'deleted'));

        return redirect()->back();
    }

    public function exportExcel(Request $request)
    {
        $employmentStatus = $request->employmentStatus;
        $department = $request->department;
        $search = $request->search;

        $fileName = 'employees_' . now()->format('d_m_Y_H_i_s') . '.xlsx';

        return Excel::download(
            new EmployeeExport($employmentStatus, $department, $search),
            $fileName
        );
    }
}
