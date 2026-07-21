<?php

namespace App\Http\Controllers;

use App\Events\AttendanceChanged;
use App\Exports\AttendanceExport;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\User;
use App\Services\SystemAuditService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceController extends Controller
{
    public function __construct(private readonly SystemAuditService $audit) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
            'branch' => ['nullable', 'integer', 'exists:branches,id'], 'department' => ['nullable', 'string', 'max:120'],
            'employee' => ['nullable', 'integer', 'exists:employees,id'], 'type' => ['nullable', 'string'],
        ]);
        $canManage = $request->user()->hasPermission('attendance.manage') || $request->user()->hasPermission('attendance.reports');
        $records = $this->recordsQuery($request, $canManage, $filters)->paginate(30)->withQueryString();
        $today = Carbon::today();
        $todayRecords = AttendanceRecord::query()->whereDate('attendance_date', $today)->get();
        $activeEmployees = Employee::query()->where('employment_status', '!=', 'Inactivo')->count();

        return Inertia::render('Systems/Attendance', [
            'records' => $records->through(fn (AttendanceRecord $record) => $this->recordPayload($record)),
            'dashboard' => [
                'present' => $todayRecords->where('type', 'check_in')->pluck('user_id')->unique()->count(),
                'late' => $todayRecords->where('status', 'late')->pluck('user_id')->unique()->count(),
                'meal' => $this->activeCount($todayRecords, 'meal_start', 'meal_end'),
                'break' => $this->activeCount($todayRecords, 'break_start', 'break_end'),
                'remote' => $todayRecords->where('type', 'remote_work')->pluck('user_id')->unique()->count(),
                'activeEmployees' => $activeEmployees,
            ],
            'filters' => array_merge(['from' => $request->input('from'), 'to' => $request->input('to'), 'branch' => $request->input('branch'), 'department' => $request->input('department'), 'employee' => $request->input('employee'), 'type' => $request->input('type')], $filters),
            'options' => [
                'types' => collect(AttendanceRecord::TYPES)->map(fn ($type) => ['value' => $type, 'label' => $this->typeLabel($type)])->values(),
                'branches' => Branch::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($branch) => ['value' => $branch->id, 'label' => $branch->name]),
                'departments' => Employee::query()->whereNotNull('department')->where('department', '!=', '')->distinct()->orderBy('department')->pluck('department')->map(fn ($department) => ['value' => $department, 'label' => $department]),
                'employees' => Employee::query()->where('employment_status', '!=', 'Inactivo')->orderBy('first_name')->get()->map(fn ($employee) => ['value' => $employee->id, 'label' => trim($employee->first_name . ' ' . $employee->last_name)]),
            ],
            'canManage' => $canManage,
            'canRegister' => $request->user()->hasPermission('attendance.register'),
            'canRequestCorrection' => $request->user()->hasPermission('attendance.corrections.request'),
            'canReviewCorrections' => $request->user()->hasPermission('attendance.corrections.review'),
            'canExport' => $request->user()->hasPermission('attendance.reports') && $request->user()->hasPermission('files.export'),
            'passkeyEnabled' => $request->user()->hasPasskeysEnabled(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:' . implode(',', AttendanceRecord::TYPES)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'integer', 'min:0'], 'approximateAddress' => ['nullable', 'string', 'max:255'],
            'authenticationMethod' => ['required', 'in:platform_biometric'], 'device' => ['nullable', 'array'],
        ]);
        $user = $request->user();
        $now = now();
        $record = AttendanceRecord::create([
            'user_id' => $user->id, 'employee_id' => $user->employee_id, 'branch_id' => $user->branch_id,
            'attendance_date' => $now->toDateString(), 'recorded_at' => $now, 'type' => $data['type'],
            'status' => $this->statusFor($data['type'], $now, $data['latitude'] ?? null, $data['longitude'] ?? null),
            'latitude' => $data['latitude'] ?? null, 'longitude' => $data['longitude'] ?? null,
            'location_accuracy' => $data['accuracy'] ?? null, 'approximate_address' => $data['approximateAddress'] ?? null,
            'authentication_method' => $data['authenticationMethod'], 'authentication_result' => 'verified',
            'operating_system' => data_get($data, 'device.operatingSystem'), 'browser' => data_get($data, 'device.browser'),
            'device_type' => data_get($data, 'device.type'), 'user_agent' => $request->userAgent(), 'ip_address' => $request->ip(),
            'metadata' => ['geofence_ready' => true],
        ]);
        $this->audit->record('attendance', 'create', 'success', $request, [
            'record_type' => AttendanceRecord::class, 'record_id' => $record->id, 'record_label' => $this->typeLabel($record->type),
            'metadata' => ['type' => $record->type, 'status' => $record->status, 'location' => $record->only(['latitude', 'longitude', 'location_accuracy'])],
        ]);
        broadcast(new AttendanceChanged($record->id, 'created', $user->id));
        return back()->with('success', 'Asistencia registrada correctamente.');
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

    public function exportExcel(Request $request) {
        $this->audit->record('attendance', 'export', 'success', $request, ['record_label' => 'Reporte de asistencias Excel']);
        return Excel::download(new AttendanceExport($this->recordsQuery($request, true, $request->all())->get()), 'asistencias-' . now()->format('Ymd_His') . '.xlsx');
    }

    public function exportPdf(Request $request) {
        $this->audit->record('attendance', 'export', 'success', $request, ['record_label' => 'Reporte de asistencias PDF']);
        return Pdf::loadView('pdf.attendance-report', ['records' => $this->recordsQuery($request, true, $request->all())->get(), 'generatedAt' => now()])->download('asistencias-' . now()->format('Ymd_His') . '.pdf');
    }

    private function recordsQuery(Request $request, bool $canManage, array $filters)
    {
        return AttendanceRecord::query()->with(['user.role', 'employee', 'branch'])
            ->when(!$canManage, fn ($query) => $query->where('user_id', $request->user()->id))
            ->when($filters['from'] ?? null, fn ($query, $value) => $query->whereDate('attendance_date', '>=', $value))
            ->when($filters['to'] ?? null, fn ($query, $value) => $query->whereDate('attendance_date', '<=', $value))
            ->when($filters['branch'] ?? null, fn ($query, $value) => $query->where('branch_id', $value))
            ->when($filters['department'] ?? null, fn ($query, $value) => $query->whereHas('employee', fn ($employee) => $employee->where('department', $value)))
            ->when($filters['employee'] ?? null, fn ($query, $value) => $query->where('employee_id', $value))
            ->when($filters['type'] ?? null, fn ($query, $value) => $query->where('type', $value))
            ->latest('recorded_at');
    }

    private function recordPayload(AttendanceRecord $record): array { return ['id' => $record->id, 'employee' => $record->employee ? trim($record->employee->first_name . ' ' . $record->employee->last_name) : $record->user?->name, 'role' => $record->user?->role?->name, 'branch' => $record->branch?->name ?? 'Sin sucursal', 'date' => $record->attendance_date?->format('d/m/Y'), 'time' => $record->recorded_at?->format('H:i'), 'type' => $this->typeLabel($record->type), 'status' => $this->statusLabel($record->status), 'authentication' => $record->authentication_method === 'platform_biometric' ? 'Biometría del dispositivo' : 'Código del dispositivo']; }
    private function activeCount($records, string $start, string $end): int { return $records->groupBy('user_id')->filter(fn ($userRecords) => optional($userRecords->sortByDesc('recorded_at')->first())->type === $start)->count(); }
    private function statusFor(string $type, Carbon $now, mixed $latitude, mixed $longitude): string { if ($type === 'check_in') return $now->format('H:i') > '09:10' ? 'late' : 'on_time'; return ($latitude === null || $longitude === null) ? 'pending' : 'on_time'; }
    private function typeLabel(string $type): string { return ['check_in'=>'Entrada','check_out'=>'Salida','meal_start'=>'Inicio de comida','meal_end'=>'Fin de comida','break_start'=>'Inicio de descanso','break_end'=>'Fin de descanso','remote_work'=>'Trabajo remoto','commission'=>'Comisión','training'=>'Capacitación'][$type] ?? $type; }
    private function statusLabel(string $status): string { return ['on_time'=>'Puntual','late'=>'Retardo','absent'=>'Falta','justified'=>'Justificada','outside_zone'=>'Fuera de zona','pending'=>'Pendiente','corrected'=>'Corregida'][$status] ?? $status; }
}
