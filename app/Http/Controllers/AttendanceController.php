<?php

namespace App\Http\Controllers;

use App\Events\AttendanceChanged;
use App\Exports\AttendanceExport;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Services\SystemAuditService;
use App\Services\AttendanceRuleEngine;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceController extends Controller
{
    public function __construct(private readonly SystemAuditService $audit, private readonly AttendanceRuleEngine $rules) {}

    public function index(Request $request)
    {
        $canViewAttendance = $request->user()->hasPermission('attendance.view')
            || $request->user()->hasPermission('attendance.manage')
            || $request->user()->hasPermission('attendance.export.excel')
            || $request->user()->hasPermission('attendance.export.pdf');
        $filters = $this->validatedFilters($request);
        $canManage = $request->user()->hasPermission('attendance.manage')
            || $request->user()->hasPermission('attendance.export.excel')
            || $request->user()->hasPermission('attendance.export.pdf');
        $canViewEvidence = $request->user()->hasPermission('attendance.manage');
        $records = $canViewAttendance
            ? $this->recordsQuery($request, $canManage, $filters)->paginate(30)->withQueryString()
            : null;
        $todayRecords = $canViewAttendance
            ? AttendanceRecord::query()->whereDate('attendance_date', Carbon::today())->get()
            : collect();

        return Inertia::render('HumanResources/Attendance', [
            'records' => $records?->through(fn (AttendanceRecord $record) => array_merge(
                $this->recordPayload($record),
                $canViewEvidence ? ['evidence' => $this->evidencePayload($record)] : [],
            )) ?? ['data' => []],
            'dashboard' => $canViewAttendance ? [
                'present' => $todayRecords->where('type', 'check_in')->pluck('user_id')->unique()->count(),
                'late' => $todayRecords->where('status', 'late')->pluck('user_id')->unique()->count(),
                'meal' => $this->activeCount($todayRecords, 'meal_start', 'meal_end'),
                'break' => $this->activeCount($todayRecords, 'break_start', 'break_end'),
                'remote' => $todayRecords->where('type', 'remote_work')->pluck('user_id')->unique()->count(),
                'activeEmployees' => Employee::query()->where('employment_status', '!=', 'Inactivo')->count(),
            ] : [],
            'filters' => array_merge(['from' => $request->input('from'), 'to' => $request->input('to'), 'branch' => $request->input('branch'), 'department' => $request->input('department'), 'employee' => $request->input('employee'), 'type' => $request->input('type')], $filters),
            'options' => [
                'types' => collect(AttendanceRecord::TYPES)->map(fn ($type) => ['value' => $type, 'label' => $this->typeLabel($type)])->values(),
                'branches' => $canViewAttendance ? Branch::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($branch) => ['value' => $branch->id, 'label' => $branch->name]) : [],
                'departments' => $canViewAttendance
                    ? Department::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($department) => ['value' => $department->id, 'label' => $department->name])
                    : [],
                'employees' => $canViewAttendance ? Employee::query()->where('employment_status', '!=', 'Inactivo')->orderBy('first_name')->get()->map(fn ($employee) => ['value' => $employee->id, 'label' => trim($employee->first_name.' '.$employee->last_name)]) : [],
            ],
            'canViewAttendance' => $canViewAttendance,
            'canManage' => $canManage,
            'canViewEvidence' => $canViewEvidence,
            'canRegister' => $request->user()->hasPermission('attendance.register'),
            'canRequestCorrection' => $request->user()->hasPermission('attendance.corrections.request'),
            'canReviewCorrections' => $request->user()->hasPermission('attendance.corrections.review'),
            'passkeyEnabled' => $request->user()->hasPasskeysEnabled(),
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
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'integer', 'min:0'],
            'approximateAddress' => ['nullable', 'string', 'max:255'],
            'authenticationMethod' => ['required', 'in:platform_biometric'],
            'device' => ['nullable', 'array'],
            'selfie' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = $request->user();
        $branch = Branch::query()->find($user->branch_id);
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
        $selfiePath = $request->file('selfie')->store("attendance/selfies/{$user->id}", 'local');
        $record = AttendanceRecord::create([
            'user_id' => $user->id, 'employee_id' => $user->employee_id, 'branch_id' => $branch->exists ? $branch->id : null,
            'attendance_date' => $now->toDateString(), 'recorded_at' => $now, 'type' => $data['type'],
            'status' => $this->rules->evaluate($user->employee, $data['type'], $now),
            'latitude' => $data['latitude'], 'longitude' => $data['longitude'], 'location_accuracy' => $data['accuracy'] ?? null,
            'approximate_address' => $data['approximateAddress'] ?? null, 'within_geofence' => true,
            'geofence_snapshot' => ['branch_id' => $branch->exists ? $branch->id : null, 'label' => $branch->name, 'radius_meters' => (int) $branch->attendance_geofence_radius_meters, 'distance_meters' => round($distance, 1)],
            'authentication_method' => $data['authenticationMethod'], 'authentication_result' => 'verified', 'selfie_path' => $selfiePath,
            'operating_system' => data_get($data, 'device.operatingSystem'), 'browser' => data_get($data, 'device.browser'),
            'device_type' => data_get($data, 'device.type'), 'user_agent' => $request->userAgent(), 'ip_address' => $request->ip(),
            'metadata' => ['photo_captured' => true],
        ]);

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
        $correction = AttendanceCorrectionRequest::create(['attendance_record_id' => $attendanceRecord->id, 'requested_by' => $request->user()->id, 'reason' => $data['reason'], 'requested_changes' => $data['requestedChanges'] ?? []]);
        $this->audit->record('attendance', 'request_correction', 'success', $request, ['record_type' => AttendanceCorrectionRequest::class, 'record_id' => $correction->id, 'record_label' => 'Solicitud de corrección']);
        broadcast(new AttendanceChanged($attendanceRecord->id, 'correction_requested', $request->user()->id));
        return back()->with('success', 'Solicitud de corrección enviada.');
    }

    public function reviewCorrection(Request $request, AttendanceCorrectionRequest $attendanceCorrectionRequest)
    {
        $data = $request->validate(['status' => ['required', 'in:approved,rejected'], 'reviewNotes' => ['nullable', 'string', 'max:1000']]);
        $attendanceCorrectionRequest->update(['status' => $data['status'], 'review_notes' => $data['reviewNotes'] ?? null, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        if ($data['status'] === 'approved') {
            $changes = collect($attendanceCorrectionRequest->requested_changes ?? [])->only(['recorded_at', 'status', 'approximate_address'])->all();
            if ($changes) $attendanceCorrectionRequest->attendanceRecord->update(array_merge($changes, ['status' => 'corrected']));
        }
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
            'department' => ['nullable', 'integer', 'exists:departments,id'],
            'employee' => ['nullable', 'integer', 'exists:employees,id'],
            'type' => ['nullable', 'in:'.implode(',', AttendanceRecord::TYPES)],
        ]);
    }
    private function recordsQuery(Request $request, bool $canManage, array $filters) { return AttendanceRecord::query()->with(['user.role', 'employee.position.department', 'branch'])->when(!$canManage, fn ($query) => $query->where('user_id', $request->user()->id))->when($filters['from'] ?? null, fn ($query, $value) => $query->whereDate('attendance_date', '>=', $value))->when($filters['to'] ?? null, fn ($query, $value) => $query->whereDate('attendance_date', '<=', $value))->when($filters['branch'] ?? null, fn ($query, $value) => $query->where('branch_id', $value))->when($filters['department'] ?? null, fn ($query, $value) => $query->whereHas('employee.position', fn ($position) => $position->where('department_id', $value)))->when($filters['employee'] ?? null, fn ($query, $value) => $query->where('employee_id', $value))->when($filters['type'] ?? null, fn ($query, $value) => $query->where('type', $value))->latest('recorded_at'); }
    private function recordPayload(AttendanceRecord $record): array { return ['id' => $record->id, 'employee' => $record->employee ? trim($record->employee->first_name.' '.$record->employee->last_name) : $record->user?->name, 'role' => $record->user?->role?->name, 'branch' => $record->branch?->name ?? 'Sin sucursal', 'date' => $record->attendance_date?->format('d/m/Y'), 'time' => $record->recorded_at?->format('H:i'), 'type' => $this->typeLabel($record->type), 'status' => $this->statusLabel($record->status), 'authentication' => 'Biometría del dispositivo']; }
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

    private function activeCount($records, string $start, string $end): int { return $records->groupBy('user_id')->filter(fn ($userRecords) => optional($userRecords->sortByDesc('recorded_at')->first())->type === $start)->count(); }
    private function statusFor(string $type, Carbon $now): string { return $type === 'check_in' ? ($now->format('H:i') > '09:10' ? 'late' : 'on_time') : 'on_time'; }
    private function distanceInMeters(float $latitude, float $longitude, float $branchLatitude, float $branchLongitude): float { $earthRadius = 6371000; $latDelta = deg2rad($branchLatitude - $latitude); $lonDelta = deg2rad($branchLongitude - $longitude); $a = sin($latDelta / 2) ** 2 + cos(deg2rad($latitude)) * cos(deg2rad($branchLatitude)) * sin($lonDelta / 2) ** 2; return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)); }
    private function typeLabel(string $type): string { return ['check_in'=>'Entrada','check_out'=>'Salida','meal_start'=>'Inicio de comida','meal_end'=>'Fin de comida','break_start'=>'Inicio de descanso','break_end'=>'Fin de descanso','remote_work'=>'Trabajo remoto','commission'=>'Comisión','training'=>'Capacitación'][$type] ?? $type; }
    private function statusLabel(string $status): string { return ['on_time'=>'Puntual','late'=>'Retardo','absent'=>'Falta','justified'=>'Justificada','outside_zone'=>'Fuera de zona','pending'=>'Pendiente','corrected'=>'Corregida'][$status] ?? $status; }
}
