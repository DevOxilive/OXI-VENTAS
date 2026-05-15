<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Exports\EmployeeExport;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeController extends Controller
{
    public function index()
    {
        $employees = Employee::latest()->get()->map(function ($employee) {
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
            'employeesDB' => $employees
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

        Employee::create([
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

        return redirect()->back();
    }

    public function destroy($id)
    {
        $employee = Employee::findOrFail($id);

        $employee->delete();

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
