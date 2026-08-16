<?php

namespace App\Http\Controllers;

use App\Events\AttendanceChanged;
use App\Exports\AttendanceExport;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\AttendanceScheduleAssignment;
use App\Models\Branch;
use App\Models\Employee;
use App\Services\SystemAuditService;
use App\Services\AttendanceRuleEngine;
use App\Support\TablePagination;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class AttendanceController extends Controller
{
    public function __construct(private readonly SystemAuditService $audit, private readonly AttendanceRuleEngine $rules) {}

    public function index(Request $request)
    {
        $canRegisterAttendance = $this->canRegisterAttendance($request->user());
        $canViewAttendance = $request->user()->hasPermission('attendance.view')
            || $request->user()->hasPermission('attendance.manage')
            || $request->user()->hasPermission('attendance.export.excel')
            || $request->user()->hasPermission('attendance.export.pdf');
        $filters = $this->listingFilters($this->validatedFilters($request));
        $canManage = $request->user()->hasPermission('attendance.manage')
            || $request->user()->hasPermission('attendance.export.excel')
            || $request->user()->hasPermission('attendance.export.pdf');
        $canViewEvidence = $request->user()->hasPermission('attendance.manage');
        $attendanceShifts = $this->attendanceShiftsForUser($request->user());
        $attendanceBranches = $this->attendanceBranchesForUser($request->user());
        $records = $canViewAttendance
            ? $this->recordsQuery($request, $canViewAttendance, $filters)
                ->paginate(TablePagination::resolvePerPage($request))
                ->withQueryString()
            : null;
        $dashboard = $canViewAttendance
            ? $this->attendanceDashboard()
            : [];

        return Inertia::render('HumanResources/Attendance', [
            'records' => $records?->through(fn (AttendanceRecord $record) => array_merge(
                $this->recordPayload($record),
                $canViewEvidence ? ['evidence' => $this->evidencePayload($record)] : [],
            )) ?? ['data' => []],
            'dashboard' => $dashboard,
            'filters' => $filters,
            'options' => [
                'types' => collect(AttendanceRecord::TYPES)->map(fn ($type) => ['value' => $type, 'label' => $this->typeLabel($type)])->values(),
                'branches' => $canViewAttendance ? Branch::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($branch) => ['value' => $branch->id, 'label' => $branch->name]) : [],
            ],
            'canViewAttendance' => $canViewAttendance,
            'canManage' => $canManage,
            'canViewEvidence' => $canViewEvidence,
            'canRegister' => $canRegisterAttendance,
            'canRequestCorrection' => $request->user()->hasPermission('attendance.corrections.request'),
            'canReviewCorrections' => $request->user()->hasPermission('attendance.corrections.review'),
            'passkeyEnabled' => $request->user()->hasPasskeysEnabled(),
            'attendanceShifts' => $attendanceShifts,
            'attendanceBranches' => $attendanceBranches,
        ]);
    }

    public function table(Request $request)
    {
        return $this->index($request);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', AttendanceRecord::TYPES)],
            'attendance_schedule_assignment_id' => ['nullable', 'integer', 'exists:attendance_schedule_assignments,id'],
            'attendance_branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'integer', 'min:0'],
            'approximateAddress' => ['nullable', 'string', 'max:255'],
            'authenticationMethod' => ['required', 'in:platform_biometric'],
            'device' => ['nullable', 'array'],
            'selfie' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = $request->user()->loadMissing('branches');
        if (! $this->canRegisterAttendance($user)) {
            return back()->withErrors([
                'type' => 'Solo los usuarios con rol Ventas o Vendedor pueden registrar asistencia.',
            ]);
        }
        $branch = $this->resolveAttendanceBranch($user, $data['attendance_branch_id'] ?? null);
        if (filled($data['attendance_branch_id'] ?? null) && ! $branch) {
            return back()->withErrors([
                'attendance_branch_id' => 'La sucursal seleccionada no está asignada a tu usuario.',
            ]);
        }
        if (! $branch) {
            $temporaryGeofence = config('attendance.temporary_geofence');
            $branch = new Branch([
                'name' => data_get($temporaryGeofence, 'label'),
                'attendance_latitude' => data_get($temporaryGeofence, 'latitude'),
                'attendance_longitude' => data_get($temporaryGeofence, 'longitude'),
                'attendance_geofence_radius_meters' => data_get($temporaryGeofence, 'radius_meters'),
            ]);
        }
        if (! $branch) {
            return back()->withErrors(['latitude' => 'Tu usuario no tiene una sucursal asignada para registrar asistencia.']);
        }
        if ($branch->attendance_latitude === null || $branch->attendance_longitude === null || ! $branch->attendance_geofence_radius_meters) {
            return back()->withErrors(['latitude' => 'La sucursal no tiene configurado su perímetro de asistencia.']);
        }

        $distance = $this->distanceInMeters((float) $data['latitude'], (float) $data['longitude'], (float) $branch->attendance_latitude, (float) $branch->attendance_longitude);
        if ($distance > (int) $branch->attendance_geofence_radius_meters) {
            return back()->withErrors(['latitude' => sprintf(
                'Estás a %.0f metros del punto autorizado y el radio permitido es de %d metros. Precisión reportada por el GPS: %s metros.',
                $distance,
                (int) $branch->attendance_geofence_radius_meters,
                $data['accuracy'] ?? 'no disponible',
            )]);
        }
        if ($distance > (int) $branch->attendance_geofence_radius_meters) {
            return back()->withErrors(['latitude' => 'No puedes registrar asistencia fuera del perímetro autorizado de tu sucursal.']);
        }

        $now = now();
        $shiftContext = $this->shiftContextForRegistration($user, $data, $now);
        $this->assertNextShiftRecord($user->id, $now, $shiftContext['assignment_id'], $data['type']);
        $selfiePath = $request->file('selfie')->store("attendance/selfies/{$user->id}", 'local');
        try {
            $record = DB::transaction(fn () => AttendanceRecord::create([
                'user_id' => $user->id, 'employee_id' => $user->employee_id, 'branch_id' => $branch->exists ? $branch->id : null,
                'attendance_date' => $now->toDateString(), 'recorded_at' => $now, 'type' => $data['type'],
                'attendance_schedule_assignment_id' => $shiftContext['assignment_id'],
                'shift_label' => $shiftContext['label'], 'shift_order' => $shiftContext['order'],
                'operation_key' => "attendance:{$user->id}:{$now->toDateString()}:{$shiftContext['key']}:{$data['type']}",
                'status' => $this->rules->evaluate($user->employee, $data['type'], $now, $shiftContext['schedule']),
                'latitude' => $data['latitude'], 'longitude' => $data['longitude'], 'location_accuracy' => $data['accuracy'] ?? null,
                'approximate_address' => $data['approximateAddress'] ?? null, 'within_geofence' => true,
                'geofence_snapshot' => ['branch_id' => $branch->exists ? $branch->id : null, 'label' => $branch->name, 'radius_meters' => (int) $branch->attendance_geofence_radius_meters, 'distance_meters' => round($distance, 1)],
                'authentication_method' => $data['authenticationMethod'], 'authentication_result' => 'verified', 'selfie_path' => $selfiePath,
                'operating_system' => data_get($data, 'device.operatingSystem'), 'browser' => data_get($data, 'device.browser'),
                'device_type' => data_get($data, 'device.type'), 'user_agent' => $request->userAgent(), 'ip_address' => $request->ip(),
                'metadata' => ['photo_captured' => true],
            ]));
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($selfiePath);

            if (str_contains($exception->getMessage(), 'operation_key')) {
                throw ValidationException::withMessages([
                    'type' => sprintf('Ya registraste %s para este turno.', mb_strtolower($this->typeLabel($data['type']))),
                ]);
            }

            throw $exception;
        }

        $this->audit->record('attendance', 'create', 'success', $request, ['record_type' => AttendanceRecord::class, 'record_id' => $record->id, 'record_label' => $this->typeLabel($record->type)]);
        broadcast(new AttendanceChanged($record->id, 'created', $user->id));

        return back()->with('success', 'Asistencia registrada correctamente.');
    }

    public function evidencePhoto(Request $request, AttendanceRecord $attendanceRecord)
    {
        abort_unless($request->user()->hasPermission('attendance.manage'), 403);
        abort_unless($attendanceRecord->selfie_path && Storage::disk('local')->exists($attendanceRecord->selfie_path), 404);

        $this->audit->record('attendance', 'view_evidence', 'success', $request, [
            'record_type' => AttendanceRecord::class,
            'record_id' => $attendanceRecord->id,
            'record_label' => $this->typeLabel($attendanceRecord->type),
        ]);

        return response()->file(Storage::disk('local')->path($attendanceRecord->selfie_path), [
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function requestCorrection(Request $request, AttendanceRecord $attendanceRecord)
    {
        abort_unless($attendanceRecord->user_id === $request->user()->id || $request->user()->hasPermission('attendance.corrections.review'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000'], 'requestedChanges' => ['nullable', 'array']]);
        $correction = DB::transaction(function () use ($attendanceRecord, $request, $data) {
            $attendanceRecord = AttendanceRecord::query()->lockForUpdate()->findOrFail($attendanceRecord->id);

            if ($attendanceRecord->correctionRequests()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages([
                    'reason' => 'Este registro ya tiene una solicitud de correccion pendiente.',
                ]);
            }

            return AttendanceCorrectionRequest::create(['attendance_record_id' => $attendanceRecord->id, 'requested_by' => $request->user()->id, 'reason' => $data['reason'], 'requested_changes' => $data['requestedChanges'] ?? [], 'pending_key' => "attendance-correction:{$attendanceRecord->id}:pending"]);
        });
        $this->audit->record('attendance', 'request_correction', 'success', $request, ['record_type' => AttendanceCorrectionRequest::class, 'record_id' => $correction->id, 'record_label' => 'Solicitud de corrección']);
        broadcast(new AttendanceChanged($attendanceRecord->id, 'correction_requested', $request->user()->id));
        return back()->with('success', 'Solicitud de corrección enviada.');
    }

    public function reviewCorrection(Request $request, AttendanceCorrectionRequest $attendanceCorrectionRequest)
    {
        $data = $request->validate(['status' => ['required', 'in:approved,rejected'], 'reviewNotes' => ['nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($attendanceCorrectionRequest, $data, $request) {
            $correction = AttendanceCorrectionRequest::query()
                ->lockForUpdate()
                ->findOrFail($attendanceCorrectionRequest->id);

            if ($correction->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Esta solicitud ya fue revisada por otra persona.',
                ]);
            }

            $correction->update(['status' => $data['status'], 'review_notes' => $data['reviewNotes'] ?? null, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'pending_key' => null]);
            if ($data['status'] === 'approved') {
                $changes = collect($correction->requested_changes ?? [])->only(['recorded_at', 'status', 'approximate_address'])->all();
                if ($changes) {
                    AttendanceRecord::query()->lockForUpdate()->findOrFail($correction->attendance_record_id)
                        ->update(array_merge($changes, ['status' => 'corrected']));
                }
            }
        });
        $this->audit->record('attendance', 'review_correction', 'success', $request, ['record_type' => AttendanceCorrectionRequest::class, 'record_id' => $attendanceCorrectionRequest->id, 'record_label' => 'Solicitud de corrección']);
        broadcast(new AttendanceChanged($attendanceCorrectionRequest->attendance_record_id, 'correction_reviewed', $request->user()->id));
        return back()->with('success', 'Solicitud de corrección actualizada.');
    }

    public function exportExcel(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $this->audit->record('attendance', 'export', 'success', $request, ['record_label' => 'Reporte de asistencias Excel']);

        return Excel::download(
            new AttendanceExport($this->recordsQuery($request, true, $filters)->get()),
            'asistencias-'.now()->format('Ymd_His').'.xlsx',
        );
    }

    public function exportPdf(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $this->audit->record('attendance', 'export', 'success', $request, ['record_label' => 'Reporte de asistencias PDF']);

        return Pdf::loadView('pdf.attendance-report', [
            'records' => $this->recordsQuery($request, true, $filters)->get(),
            'generatedAt' => now(),
        ])->download('asistencias-'.now()->format('Ymd_His').'.pdf');
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'branch' => ['nullable', 'integer', 'exists:branches,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:'.implode(',', AttendanceRecord::TYPES)],
            'per_page' => ['nullable', 'integer'],
        ]);
    }
    private function listingFilters(array $filters): array
    {
        return array_merge($filters, [
            'from' => $filters['from'] ?? Carbon::today()->toDateString(),
            'to' => $filters['to'] ?? Carbon::today()->toDateString(),
        ]);
    }

    private function recordsQuery(Request $request, bool $canViewAllAttendance, array $filters)
    {
        return AttendanceRecord::query()
            ->with(['user.role', 'user.branches', 'employee.position.department', 'branch', 'scheduleAssignment.schedule'])
            ->when(! $canViewAllAttendance, fn ($query) => $query->where('user_id', $request->user()->id))
            ->when($filters['from'] ?? null, fn ($query, $value) => $query->where('attendance_date', '>=', $value))
            ->when($filters['to'] ?? null, fn ($query, $value) => $query->where('attendance_date', '<=', $value))
            ->when($filters['branch'] ?? null, function ($query, $value) {
                $branchId = (int) $value;

                $query->where(function ($records) use ($branchId) {
                    $records
                        ->where('branch_id', $branchId)
                        ->orWhere(function ($fallback) use ($branchId) {
                            $fallback
                                ->whereNull('branch_id')
                                ->whereHas('user', fn ($user) => $user
                                    ->where('branch_id', $branchId)
                                    ->orWhereHas('branches', fn ($branch) => $branch->where('branches.id', $branchId)));
                        });
                });
            })
            ->when($filters['search'] ?? null, function ($query, $value) {
                $term = '%'.trim($value).'%';

                $query->where(function ($records) use ($term) {
                    $records->whereHas('employee', fn ($employee) => $employee
                        ->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", [$term])
                        ->orWhere('email', 'like', $term))
                        ->orWhereHas('user', fn ($user) => $user
                            ->where('name', 'like', $term)
                            ->orWhere('email', 'like', $term));
                });
            })
            ->when($filters['type'] ?? null, fn ($query, $value) => $query->where('type', $value))
            ->latest('recorded_at');
    }

    private function canRegisterAttendance($user): bool
    {
        if (! $user?->hasPermission('attendance.register')) {
            return false;
        }

        return in_array($user->role?->name, ['Ventas', 'Vendedor'], true);
    }

    private function attendanceBranchesForUser($user): array
    {
        return $user->accessibleBranchesQuery()
            ->orderBy('branches.name')
            ->get(['branches.id', 'branches.name'])
            ->map(fn (Branch $branch) => [
                'value' => $branch->id,
                'label' => $branch->name,
            ])
            ->values()
            ->all();
    }

    private function resolveAttendanceBranch($user, ?int $requestedBranchId = null): ?Branch
    {
        if ($requestedBranchId) {
            return $user->accessibleBranchesQuery()
                ->whereKey($requestedBranchId)
                ->first();
        }

        if ($user->branch_id && $user->hasBranchAccess((int) $user->branch_id)) {
            $branch = Branch::query()
                ->where('active', true)
                ->find($user->branch_id);

            if ($branch) {
                return $branch;
            }
        }

        return $user->branches
            ->first(fn (Branch $branch) => $branch->active);
    }

    private function attendanceShiftsForUser($user): array
    {
        $employee = $user->employee;
        $assignments = $employee
            ? $this->rules->shiftsFor($employee, Carbon::today())
            : collect();

        if ($assignments->isEmpty()) {
            return [[
                'id' => null,
                'label' => 'Turno general',
                'order' => 1,
                'registered_types' => $this->registeredTypesForShift($user->id, Carbon::today(), null),
            ]];
        }

        $hasPendingEarlierShift = false;

        return $assignments->map(function (AttendanceScheduleAssignment $assignment) use ($user, &$hasPendingEarlierShift) {
            $registeredTypes = $this->registeredTypesForShift($user->id, Carbon::today(), $assignment->id);
            $isCompleted = count($registeredTypes) === 4;
            $isLocked = $hasPendingEarlierShift;

            if (! $isCompleted) {
                $hasPendingEarlierShift = true;
            }

            return [
                'id' => $assignment->id,
                'label' => $this->shiftLabel($assignment),
                'order' => $assignment->shift_order,
                'registered_types' => $registeredTypes,
                'completed' => $isCompleted,
                'locked' => $isLocked,
            ];
        })->values()->all();
    }

    private function shiftContextForRegistration($user, array $data, Carbon $recordedAt): array
    {
        $assignments = $user->employee
            ? $this->rules->shiftsFor($user->employee, $recordedAt)
            : collect();
        $assignmentId = filled($data['attendance_schedule_assignment_id'] ?? null)
            ? (int) $data['attendance_schedule_assignment_id']
            : null;

        if ($assignments->isEmpty()) {
            if ($assignmentId !== null) {
                throw ValidationException::withMessages([
                    'attendance_schedule_assignment_id' => 'El turno seleccionado ya no esta disponible para hoy.',
                ]);
            }

            return ['assignment_id' => null, 'key' => 'general', 'label' => 'Turno general', 'order' => 1, 'schedule' => null];
        }

        $assignment = $assignments->firstWhere('id', $assignmentId);
        if (! $assignment) {
            throw ValidationException::withMessages([
                'attendance_schedule_assignment_id' => 'Selecciona uno de tus turnos programados para hoy.',
            ]);
        }

        $nextAssignment = $assignments->first(function (AttendanceScheduleAssignment $candidate) use ($user, $recordedAt) {
            return count($this->registeredTypesForShift($user->id, $recordedAt, $candidate->id)) < 4;
        });

        if ($nextAssignment && (int) $assignment->id !== (int) $nextAssignment->id) {
            throw ValidationException::withMessages([
                'attendance_schedule_assignment_id' => 'Completa las cuatro asistencias de '.$this->shiftLabel($nextAssignment).' antes de iniciar el siguiente horario.',
            ]);
        }

        return [
            'assignment_id' => $assignment->id,
            'key' => 'shift-'.$assignment->id,
            'label' => $this->shiftLabel($assignment),
            'order' => $assignment->shift_order,
            'schedule' => $assignment->schedule,
        ];
    }

    private function assertNextShiftRecord(int $userId, Carbon $recordedAt, ?int $assignmentId, string $type): void
    {
        $sequence = ['check_in', 'meal_start', 'meal_end', 'check_out'];
        $registeredTypes = $this->registeredTypesForShift($userId, $recordedAt, $assignmentId);
        $nextType = collect($sequence)->first(fn (string $expectedType) => ! in_array($expectedType, $registeredTypes, true));

        if ($nextType === null) {
            throw ValidationException::withMessages([
                'type' => 'Este turno ya tiene todos sus registros completados.',
            ]);
        }

        if ($type !== $nextType) {
            throw ValidationException::withMessages([
                'type' => sprintf('Para este turno primero registra %s.', mb_strtolower($this->typeLabel($nextType))),
            ]);
        }
    }

    private function registeredTypesForShift(int $userId, Carbon $date, ?int $assignmentId): array
    {
        return AttendanceRecord::query()
            ->where('user_id', $userId)
            ->whereDate('attendance_date', $date)
            ->when(
                $assignmentId,
                fn ($query, $id) => $query->where('attendance_schedule_assignment_id', $id),
                fn ($query) => $query->whereNull('attendance_schedule_assignment_id'),
            )
            ->pluck('type')
            ->unique()
            ->values()
            ->all();
    }

    private function shiftLabel(AttendanceScheduleAssignment $assignment): string
    {
        $schedule = $assignment->schedule;
        $hours = collect([$schedule?->check_in_at, $schedule?->check_out_at])
            ->filter()
            ->map(fn ($time) => substr((string) $time, 0, 5))
            ->join(' - ');

        return trim(($schedule?->name ?? 'Horario').' '.($hours ? "({$hours})" : ''));
    }

    private function recordPayload(AttendanceRecord $record): array
    {
        $fallbackBranch = $record->user?->branches?->first();

        return [
            'id' => $record->id,
            'employee' => $record->employee ? trim($record->employee->first_name.' '.$record->employee->last_name) : $record->user?->name,
            'role' => $record->user?->role?->name,
            'branch' => $record->branch?->name ?? $fallbackBranch?->name ?? 'Sin sucursal',
            'date' => $record->attendance_date?->format('d/m/Y'),
            'time' => $record->recorded_at?->format('H:i'),
            'shift' => $record->shift_label ?? $record->scheduleAssignment?->schedule?->name ?? 'Turno general',
            'type' => $this->typeLabel($record->type),
            'status' => $this->statusLabel($record->status),
            'authentication' => 'Biometría del dispositivo',
        ];
    }
    private function evidencePayload(AttendanceRecord $record): ?array
    {
        if (! $record->selfie_path) return null;

        $snapshot = $record->geofence_snapshot ?? [];
        $latitude = (float) $record->latitude;
        $longitude = (float) $record->longitude;

        return [
            'photo_url' => route('human-resources.attendance.evidence-photo', $record, false),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy_meters' => $record->location_accuracy,
            'distance_meters' => data_get($snapshot, 'distance_meters'),
            'radius_meters' => data_get($snapshot, 'radius_meters'),
            'location_label' => data_get($snapshot, 'label') ?? $record->approximate_address ?? 'Ubicación registrada',
            'map_url' => 'https://www.google.com/maps/search/?api=1&query='.$latitude.','.$longitude,
        ];
    }

    private function attendanceDashboard(): array
    {
        $today = Carbon::today();
        $aggregate = AttendanceRecord::query()
            ->where('attendance_date', $today->toDateString())
            ->selectRaw("COUNT(DISTINCT CASE WHEN type = 'check_in' THEN user_id END) as present")
            ->selectRaw("COUNT(DISTINCT CASE WHEN status = 'late' THEN user_id END) as late")
            ->selectRaw("COUNT(DISTINCT CASE WHEN type = 'remote_work' THEN user_id END) as remote")
            ->first();

        return [
            'present' => (int) ($aggregate->present ?? 0),
            'late' => (int) ($aggregate->late ?? 0),
            'meal' => $this->activeAttendanceTypeCount($today, 'meal_start', 'meal_end'),
            'break' => $this->activeAttendanceTypeCount($today, 'break_start', 'break_end'),
            'remote' => (int) ($aggregate->remote ?? 0),
            'activeEmployees' => Employee::query()
                ->where('employment_status', '!=', 'Inactivo')
                ->count(),
        ];
    }

    private function activeAttendanceTypeCount(Carbon $date, string $startType, string $endType): int
    {
        return AttendanceRecord::query()
            ->where('attendance_date', $date->toDateString())
            ->where('type', $startType)
            ->whereNotExists(function ($query) use ($date, $endType) {
                $query->selectRaw('1')
                    ->from('attendance_records as completed_attendance')
                    ->whereColumn('completed_attendance.user_id', 'attendance_records.user_id')
                    ->where('completed_attendance.attendance_date', $date->toDateString())
                    ->where('completed_attendance.type', $endType);
            })
            ->distinct()
            ->count('user_id');
    }
    private function statusFor(string $type, Carbon $now): string { return $type === 'check_in' ? ($now->format('H:i') > '09:10' ? 'late' : 'on_time') : 'on_time'; }
    private function distanceInMeters(float $latitude, float $longitude, float $branchLatitude, float $branchLongitude): float { $earthRadius = 6371000; $latDelta = deg2rad($branchLatitude - $latitude); $lonDelta = deg2rad($branchLongitude - $longitude); $a = sin($latDelta / 2) ** 2 + cos(deg2rad($latitude)) * cos(deg2rad($branchLatitude)) * sin($lonDelta / 2) ** 2; return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)); }
    private function typeLabel(string $type): string { return ['check_in'=>'Entrada','meal_start'=>'Salida a comida','meal_end'=>'Entrada de comida','check_out'=>'Salida general','break_start'=>'Inicio de descanso','break_end'=>'Fin de descanso','remote_work'=>'Trabajo remoto','commission'=>'Comisión','training'=>'Capacitación'][$type] ?? $type; }
    private function statusLabel(string $status): string { return ['on_time'=>'Puntual','late'=>'Retardo','absent'=>'Falta','justified'=>'Justificada','outside_zone'=>'Fuera de zona','pending'=>'Pendiente','corrected'=>'Corregida'][$status] ?? $status; }
}
