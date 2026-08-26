<?php

namespace App\Http\Controllers;

use App\Events\EmployeeChanged;
use App\Events\RealtimeActivityLogged;
use App\Events\UserChanged;
use App\Exports\EmployeeExport;
use App\Http\Controllers\Concerns\ValidatesRecordVersion;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Support\FlexibleSearch;
use App\Support\SystemPermission;
use App\Support\TablePagination;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeController extends Controller
{
    use ValidatesRecordVersion;

    private function syncLinkedUserStatus(Employee $employee): void
    {
        $user = $employee->user;

        if (! $user) {
            return;
        }

        $isActive = $employee->employment_status !== 'Inactivo';

        $user->forceFill([
            'is_active' => $isActive,
        ])->save();

        if (! $isActive) {
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->delete();
        }

        broadcast(new UserChanged($user, 'updated'))->toOthers();
    }

    private function deleteLinkedUser(Employee $employee): void
    {
        $user = $employee->user;

        if (! $user) {
            return;
        }

        $user->load(['role', 'permissions', 'branches']);

        if ($user->role?->name === 'Super Administrador'
            && ! request()->user()?->hasPermission(SystemPermission::SUPER_ADMINISTRATORS_MANAGE)) {
            abort(403, 'Solo un Super Administrador puede administrar a otro Super Administrador.');
        }

        try {
            broadcast(new UserChanged($user, 'deleted'))->toOthers();
            event(RealtimeActivityLogged::message('elimino', 'el usuario', $user->email, 'Sistemas', 'deleted'));
        } catch (\Throwable $e) {
            report($e);
        }

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->delete();

        $user->delete();
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $perPage = TablePagination::resolvePerPage($request);

        $employmentStatus = trim((string) $request->input('employmentStatus', ''));
        $department = $request->integer('department') ?: null;
        $position = $request->integer('position') ?: null;
        $startDateFrom = trim((string) $request->input('startDateFrom', ''));
        $startDateTo = trim((string) $request->input('startDateTo', ''));

        $employeesDB = Employee::query()
            ->with('position.department')
            ->when($search, function ($query) use ($search) {
                FlexibleSearch::apply($query, $search, function ($subQuery, $phrase, $terms) {
                    FlexibleSearch::orWhereColumns($subQuery, [
                        'first_name',
                        'last_name',
                        'email',
                        'phone',
                        'emergency_contact_name',
                        'emergency_contact_relationship',
                        'emergency_contact_phone',
                        'secondary_emergency_contact_name',
                        'secondary_emergency_contact_relationship',
                        'secondary_emergency_contact_phone',
                        'nss',
                        'rfc',
                    ], $phrase, $terms);
                    FlexibleSearch::orWhereHasColumns($subQuery, 'position', ['name'], $phrase, $terms);
                    FlexibleSearch::orWhereHasColumns($subQuery, 'position.department', ['name'], $phrase, $terms);
                });
            })
            ->when($employmentStatus, function ($query) use ($employmentStatus) {
                $query->where('employment_status', $employmentStatus);
            })
            ->when($department, function ($query) use ($department) {
                $query->whereHas('position', fn ($positionQuery) => $positionQuery->where('department_id', $department));
            })
            ->when($position, function ($query) use ($position) {
                $query->where('position_id', $position);
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
                    'birthDate' => $employee->birth_date?->toDateString(),
                    'age' => $this->ageLabel($employee->birth_date),
                    'email' => $employee->email,
                    'phone' => $employee->phone,
                    'emergencyContactName' => $employee->emergency_contact_name,
                    'emergencyContactRelationship' => $employee->emergency_contact_relationship,
                    'emergencyContactPhone' => $employee->emergency_contact_phone,
                    'secondaryEmergencyContactName' => $employee->secondary_emergency_contact_name,
                    'secondaryEmergencyContactRelationship' => $employee->secondary_emergency_contact_relationship,
                    'secondaryEmergencyContactPhone' => $employee->secondary_emergency_contact_phone,
                    'street' => $employee->street,
                    'externalNumber' => $employee->external_number,
                    'internalNumber' => $employee->internal_number,
                    'postalCode' => $employee->postal_code,
                    'neighborhood' => $employee->neighborhood,
                    'municipality' => $employee->municipality,
                    'addressState' => $employee->address_state,
                    'mapsUrl' => $employee->maps_url,
                    'startDate' => $employee->start_date?->toDateString(),
                    'employmentStatus' => $employee->employment_status,
                    'photo' => $employee->photo,
                    'positionId' => $employee->position_id,
                    'position' => $employee->position?->name ?? 'Sin puesto',
                    'departmentId' => $employee->position?->department_id,
                    'department' => $employee->position?->department?->name ?? 'Sin departamento',
                    'bank' => $employee->bank,
                    'accountNumber' => $employee->account_number,
                    'bankClabe' => $employee->bank_clabe,
                    'bankCardNumber' => $employee->bank_card_number,
                    'educationLevel' => $employee->education_level,
                    'specialty' => $employee->specialty,
                    'contractType' => $employee->contract_type,
                    'seniority' => $this->seniorityLabel($employee->start_date),
                    'nss' => $employee->nss,
                    'rfc' => $employee->rfc,
                    'recordVersion' => $employee->updated_at?->toJSON(),
                ];
            });

        return Inertia::render('HumanResources/Employees', [
            'employeesDB' => $employeesDB,
            'filterOptions' => [
                'positions' => Position::query()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Position $position) => [
                        'value' => $position->id,
                        'label' => $position->name,
                    ])
                    ->values(),
                'departments' => Department::query()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Department $department) => [
                        'value' => $department->id,
                        'label' => $department->name,
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
            'organizationOptions' => $this->organizationOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $this->validateEmployee($request);

        $employee = Employee::create([
            'first_name' => $request->firstName,
            'last_name' => $request->lastName,
            'birth_date' => $request->birthDate,
            'email' => $request->email,
            'phone' => $request->phone,
            'emergency_contact_name' => $request->emergencyContactName,
            'emergency_contact_relationship' => $request->emergencyContactRelationship,
            'emergency_contact_phone' => $request->emergencyContactPhone,
            'secondary_emergency_contact_name' => $request->secondaryEmergencyContactName,
            'secondary_emergency_contact_relationship' => $request->secondaryEmergencyContactRelationship,
            'secondary_emergency_contact_phone' => $request->secondaryEmergencyContactPhone,
            'street' => $request->street,
            'external_number' => $request->externalNumber,
            'internal_number' => $request->internalNumber,
            'postal_code' => $request->postalCode,
            'neighborhood' => $request->neighborhood,
            'municipality' => $request->municipality,
            'address_state' => $request->addressState,
            'maps_url' => $request->mapsUrl,
            'start_date' => Carbon::now('America/Mexico_City')->toDateString(),
            'employment_status' => 'Activo',
            'photo' => null,
            'position_id' => $request->positionId,
            'bank' => $request->bank ?: 'HSBC',
            'account_number' => $request->accountNumber,
            'bank_clabe' => $request->bankClabe,
            'bank_card_number' => $request->bankCardNumber,
            'education_level' => $request->educationLevel,
            'specialty' => $request->specialty,
            'contract_type' => $request->contractType,
            'nss' => $request->nss,
            'rfc' => $this->buildRfc($request),
        ]);

        broadcast(new EmployeeChanged('created', $employee->id))->toOthers();
        event(RealtimeActivityLogged::message('creó', 'el empleado', trim("{$employee->first_name} {$employee->last_name}"), 'Recursos humanos', 'created'));

        return redirect()->back();
    }

    public function update(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $this->validateEmployee($request, $employee);

        $employee = DB::transaction(function () use ($request, $employee) {
            $employee = $this->lockCurrentVersion($request, $employee);
            $employee->update([
                'first_name' => $request->firstName,
                'last_name' => $request->lastName,
                'birth_date' => $request->birthDate,
                'email' => $request->email,
                'phone' => $request->phone,
                'emergency_contact_name' => $request->emergencyContactName,
                'emergency_contact_relationship' => $request->emergencyContactRelationship,
                'emergency_contact_phone' => $request->emergencyContactPhone,
                'secondary_emergency_contact_name' => $request->secondaryEmergencyContactName,
                'secondary_emergency_contact_relationship' => $request->secondaryEmergencyContactRelationship,
                'secondary_emergency_contact_phone' => $request->secondaryEmergencyContactPhone,
                'street' => $request->street,
                'external_number' => $request->externalNumber,
                'internal_number' => $request->internalNumber,
                'postal_code' => $request->postalCode,
                'neighborhood' => $request->neighborhood,
                'municipality' => $request->municipality,
                'address_state' => $request->addressState,
                'maps_url' => $request->mapsUrl,
                'position_id' => $request->positionId,
                'bank' => $request->bank ?: 'HSBC',
                'account_number' => $request->accountNumber,
                'bank_clabe' => $request->bankClabe,
                'bank_card_number' => $request->bankCardNumber,
                'education_level' => $request->educationLevel,
                'specialty' => $request->specialty,
                'contract_type' => $request->contractType,
                'nss' => $request->nss,
                'rfc' => $this->buildRfc($request),
            ]);

            return $employee;
        });

        $employee->load('user');
        $this->syncLinkedUserStatus($employee);

        broadcast(new EmployeeChanged('updated', $employee->id))->toOthers();
        event(RealtimeActivityLogged::message('actualizó', 'el empleado', trim("{$employee->first_name} {$employee->last_name}"), 'Recursos humanos', 'updated'));

        return redirect()->back();
    }

    public function destroy(Request $request, $id)
    {
        $employee = Employee::with('user')->findOrFail($id);
        $employeeId = $employee->id;
        $employeeName = trim("{$employee->first_name} {$employee->last_name}");

        DB::transaction(function () use ($request, $employee) {
            $employee = $this->lockCurrentVersion($request, $employee);
            $this->deleteLinkedUser($employee);
            $employee->delete();
        });

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

        $fileName = 'employees_'.now()->format('d_m_Y_H_i_s').'.xlsx';

        return Excel::download(
            new EmployeeExport($employmentStatus, $department, $position, $search, $startDateFrom, $startDateTo),
            $fileName
        );
    }

    private function organizationOptions(): array
    {
        return [
            'departments' => Department::query()
                ->orderBy('name')
                ->get(['id', 'name', 'active'])
                ->map(fn (Department $department) => [
                    'value' => $department->id,
                    'label' => $department->active ? $department->name : "{$department->name} (Inactivo)",
                    'active' => $department->active,
                ])
                ->values(),
            'positions' => Position::query()
                ->orderBy('name')
                ->get(['id', 'name', 'department_id', 'active'])
                ->map(fn (Position $position) => [
                    'value' => $position->id,
                    'label' => $position->active ? $position->name : "{$position->name} (Inactivo)",
                    'departmentId' => $position->department_id,
                    'active' => $position->active,
                ])
                ->values(),
        ];
    }

    private function ageLabel($birthDate): string
    {
        if (! $birthDate) {
            return '';
        }

        return Carbon::parse($birthDate)->age.' años';
    }

    private function seniorityLabel($startDate): string
    {
        if (! $startDate) {
            return '';
        }

        $diff = Carbon::parse($startDate)->diff(Carbon::now('America/Mexico_City'));

        return "{$diff->y} años {$diff->m} meses";
    }

    private function buildRfc(Request $request): string
    {
        $prefix = $this->rfcNamePrefix($request->firstName, $request->lastName);
        $birthDate = Carbon::parse($request->birthDate)->format('ymd');
        $suffix = substr(preg_replace('/[^A-Z0-9Ñ&]/u', '', strtoupper((string) $request->rfc)), 10, 3);

        return mb_substr($prefix.$birthDate.$suffix, 0, 13, 'UTF-8');
    }

    private function rfcNamePrefix(?string $firstName, ?string $lastName): string
    {
        $firstNameParts = preg_split('/\s+/', $this->normalizeRfcText($firstName), -1, PREG_SPLIT_NO_EMPTY);
        $lastNameParts = preg_split('/\s+/', $this->normalizeRfcText($lastName), -1, PREG_SPLIT_NO_EMPTY);
        $ignoredNames = ['JOSE', 'J', 'MARIA', 'MA', 'MA.'];
        $relevantFirstName = collect($firstNameParts)->first(fn ($name) => ! in_array($name, $ignoredNames, true)) ?: ($firstNameParts[0] ?? '');
        $paternalSurname = $lastNameParts[0] ?? '';
        $maternalSurname = $lastNameParts[1] ?? '';

        return mb_substr(
            $this->firstCharacter($paternalSurname)
            .$this->firstInternalVowel($paternalSurname)
            .$this->firstCharacter($maternalSurname)
            .$this->firstCharacter($relevantFirstName),
            0,
            4,
            'UTF-8'
        );
    }

    private function normalizeRfcText(?string $value): string
    {
        $value = mb_strtoupper(trim((string) $value), 'UTF-8');
        $value = strtr($value, [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
        ]);

        return preg_replace('/[^A-ZÑ&\s]/u', ' ', $value) ?? '';
    }

    private function firstInternalVowel(string $word): string
    {
        return preg_match('/[AEIOU]/', mb_substr($word, 1, null, 'UTF-8'), $matches) ? $matches[0] : 'X';
    }

    private function firstCharacter(string $word): string
    {
        return $word !== '' ? mb_substr($word, 0, 1, 'UTF-8') : 'X';
    }

    private function relationshipOptions(): array
    {
        return [
            'Madre',
            'Padre',
            'Abuela',
            'Abuelo',
            'Tia',
            'Tio',
            'Hija',
            'Hijo',
            'Hermana',
            'Hermano',
            'Conyuge',
            'Pareja',
            'Otro',
        ];
    }

    private function validateEmployee(Request $request, ?Employee $employee = null): array
    {
        $positionRule = Rule::exists('positions', 'id')
            ->where(fn ($query) => $query->where('department_id', $request->integer('departmentId')));

        if (! $employee) {
            $positionRule->where(fn ($query) => $query->where('active', true));
        }

        return $request->validate([
            'firstName' => ['required', 'string', 'max:80'],
            'lastName' => ['required', 'string', 'max:80'],
            'birthDate' => ['required', 'date', 'before_or_equal:today'],
            'email' => [
                'required',
                'email',
                Rule::unique('employees', 'email')->ignore($employee?->id),
            ],
            'phone' => ['required', 'digits:10'],
            'emergencyContactName' => ['required', 'string', 'max:80'],
            'emergencyContactRelationship' => ['required', Rule::in($this->relationshipOptions())],
            'emergencyContactPhone' => ['required', 'digits:10'],
            'secondaryEmergencyContactName' => ['nullable', 'string', 'max:80'],
            'secondaryEmergencyContactRelationship' => ['nullable', Rule::in($this->relationshipOptions())],
            'secondaryEmergencyContactPhone' => ['nullable', 'digits:10'],
            'street' => ['required', 'string', 'max:80'],
            'externalNumber' => ['required', 'string', 'max:20'],
            'internalNumber' => ['nullable', 'string', 'max:20'],
            'postalCode' => ['required', 'digits:5'],
            'neighborhood' => ['required', 'string', 'max:80'],
            'municipality' => ['required', 'string', 'max:80'],
            'addressState' => ['required', 'string', 'max:80'],
            'mapsUrl' => ['nullable', 'string', 'max:1000'],
            'departmentId' => ['required', 'integer', 'exists:departments,id'],
            'positionId' => ['required', 'integer', $positionRule],
            'bank' => ['nullable', 'string', 'max:50'],
            'accountNumber' => ['nullable', 'digits_between:10,18'],
            'bankClabe' => ['nullable', 'digits:18'],
            'bankCardNumber' => ['nullable', 'digits:16'],
            'educationLevel' => [
                'required',
                Rule::in([
                    'Primaria',
                    'Secundaria',
                    'Bachillerato',
                    'Carrera tecnica',
                    'Tecnico superior universitario',
                    'Licenciatura',
                    'Ingenieria',
                    'Especialidad',
                    'Maestria',
                    'Doctorado',
                    'Posdoctorado',
                ]),
            ],
            'specialty' => ['required', 'string', 'max:50'],
            'contractType' => ['required', 'string', 'max:50'],
            'nss' => ['nullable', 'digits:11'],
            'rfc' => ['required', 'string', 'size:13', 'regex:/^[A-ZÑ&]{4}\d{6}[A-Z0-9]{3}$/'],
        ]);
    }
}
