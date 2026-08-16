<?php

namespace App\Http\Controllers;

use App\Events\AttendanceChanged;
use App\Models\AttendanceSchedule;
use App\Models\AttendanceScheduleAssignment;
use App\Models\Employee;
use App\Services\SystemAuditService;
use App\Support\TablePagination;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AttendanceScheduleAssignmentController extends Controller
{
    public function __construct(private readonly SystemAuditService $audit) {}

    public function index(Request $request)
    {
        $perPage = TablePagination::resolvePerPage($request);

        return Inertia::render('HumanResources/AttendanceScheduleAssignments', [
            'assignments' => AttendanceScheduleAssignment::query()
                ->with([
                    'schedule',
                    'assignable' => function (MorphTo $morphTo) {
                        $morphTo->morphWith([
                            Employee::class => ['position.department'],
                        ]);
                    },
                    'assignedBy',
                ])
                ->where('assignable_type', Employee::class)
                ->latest()
                ->paginate($perPage)
                ->through(fn ($assignment) => $this->payload($assignment)),
            'employees' => Employee::query()
                ->where('employment_status', '!=', 'Inactivo')
                ->whereHas('user.role', fn ($role) => $role->whereIn('name', ['Ventas', 'Vendedor']))
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name'])
                ->map(fn ($employee) => ['value' => $employee->id, 'label' => trim($employee->first_name.' '.$employee->last_name)]),
            'schedules' => AttendanceSchedule::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'check_in_at', 'check_out_at'])
                ->map(fn ($schedule) => [
                    'value' => $schedule->id,
                    'label' => $schedule->name,
                    'description' => $this->scheduleHours($schedule),
                ]),
            'filters' => ['per_page' => $perPage],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->assertEligibleSalesEmployee((int) $data['employee_id']);

        $assignments = DB::transaction(function () use ($data, $request) {
            Employee::query()->lockForUpdate()->findOrFail($data['employee_id']);

            $assignments = collect($data['attendance_schedule_ids'])->map(function ($scheduleId) use ($data, $request) {
                $assignmentData = array_merge($data, ['attendance_schedule_id' => (int) $scheduleId]);
                $this->assertNoOverlappingAssignment($assignmentData);

                return $this->createAssignment($assignmentData, $request->user()->id);
            });
            $this->resequenceEmployeeShifts((int) $data['employee_id']);

            return $assignments;
        }, 3);

        $this->audit->record('attendance_schedule_assignment', 'create', 'success', $request, [
            'record_type' => AttendanceScheduleAssignment::class,
            'record_id' => $assignments->first()->id,
            'record_label' => 'Asignaciones de horario',
        ]);
        broadcast(new AttendanceChanged($assignments->first()->id, 'schedule_assignment_created', $request->user()->id));

        return back()->with('success', $assignments->count() === 1 ? 'Horario asignado correctamente.' : 'Horarios asignados correctamente.');
    }

    public function update(Request $request, AttendanceScheduleAssignment $attendanceScheduleAssignment)
    {
        abort_unless($attendanceScheduleAssignment->assignable_type === Employee::class, 404);
        $data = $this->validated($request);
        $this->assertEligibleSalesEmployee((int) $data['employee_id']);
        $before = $attendanceScheduleAssignment->getOriginal();

        DB::transaction(function () use ($attendanceScheduleAssignment, $data, $request) {
            Employee::query()
                ->whereIn('id', collect([$attendanceScheduleAssignment->assignable_id, $data['employee_id']])->sort()->values())
                ->lockForUpdate()
                ->get();

            $assignment = AttendanceScheduleAssignment::query()->lockForUpdate()->findOrFail($attendanceScheduleAssignment->id);
            $primaryScheduleId = (int) collect($data['attendance_schedule_ids'])->first();
            $primaryData = array_merge($data, ['attendance_schedule_id' => $primaryScheduleId]);
            $this->assertNoOverlappingAssignment($primaryData, $assignment->id);
            $assignment->update([
                'attendance_schedule_id' => $primaryScheduleId,
                'assignable_id' => $data['employee_id'],
                'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'] ?? null,
                'observations' => $data['observations'] ?? null,
                'working_days' => $data['working_days'],
                'active' => $data['active'],
            ]);

            collect($data['attendance_schedule_ids'])->skip(1)->each(function ($scheduleId) use ($data, $request) {
                $assignmentData = array_merge($data, ['attendance_schedule_id' => (int) $scheduleId]);
                $this->assertNoOverlappingAssignment($assignmentData);
                $this->createAssignment($assignmentData, $request->user()->id);
            });

            $this->resequenceEmployeeShifts((int) $data['employee_id']);
        }, 3);

        $attendanceScheduleAssignment->refresh();
        $this->audit->record('attendance_schedule_assignment', 'update', 'success', $request, [
            'record_type' => AttendanceScheduleAssignment::class,
            'record_id' => $attendanceScheduleAssignment->id,
            'old_values' => $before,
            'new_values' => $attendanceScheduleAssignment->getChanges(),
        ]);
        broadcast(new AttendanceChanged($attendanceScheduleAssignment->id, 'schedule_assignment_updated', $request->user()->id));

        return back()->with('success', 'Asignacion actualizada correctamente.');
    }

    public function destroy(Request $request, AttendanceScheduleAssignment $attendanceScheduleAssignment)
    {
        abort_unless($attendanceScheduleAssignment->assignable_type === Employee::class, 404);
        $assignmentId = $attendanceScheduleAssignment->id;
        $attendanceScheduleAssignment->delete();
        $this->audit->record('attendance_schedule_assignment', 'delete', 'success', $request, [
            'record_type' => AttendanceScheduleAssignment::class,
            'record_id' => $assignmentId,
            'record_label' => 'Asignacion de horario',
        ]);
        broadcast(new AttendanceChanged($assignmentId, 'schedule_assignment_deleted', $request->user()->id));

        return back()->with('success', 'Asignacion eliminada correctamente. El empleado y el horario se conservan.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'attendance_schedule_ids' => ['required', 'array', 'min:1'],
            'attendance_schedule_ids.*' => ['integer', 'distinct', 'exists:attendance_schedules,id'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'active' => ['required', 'boolean'],
            'observations' => ['nullable', 'string', 'max:2000'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
        ]);
    }

    private function createAssignment(array $data, int $assignedBy): AttendanceScheduleAssignment
    {
        return AttendanceScheduleAssignment::create([
            'attendance_schedule_id' => $data['attendance_schedule_id'],
            'assignable_type' => Employee::class,
            'assignable_id' => $data['employee_id'],
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'shift_order' => 1,
            'observations' => $data['observations'] ?? null,
            'working_days' => $data['working_days'],
            'active' => $data['active'],
            'assigned_by' => $assignedBy,
        ]);
    }

    private function assertNoOverlappingAssignment(array $data, ?int $exceptId = null): void
    {
        if (! $data['active']) {
            return;
        }

        $start = Carbon::parse($data['effective_from'])->toDateString();
        $end = filled($data['effective_to'] ?? null) ? Carbon::parse($data['effective_to'])->toDateString() : null;
        $overlaps = AttendanceScheduleAssignment::query()
            ->where('assignable_type', Employee::class)
            ->where('assignable_id', $data['employee_id'])
            ->where('active', true)
            ->where('attendance_schedule_id', $data['attendance_schedule_id'])
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $start))
            ->when($end, fn ($query) => $query->whereDate('effective_from', '<=', $end))
            ->when(! $end, fn ($query) => $query->whereNotNull('effective_from'))
            ->lockForUpdate()
            ->exists();

        if ($overlaps) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'attendance_schedule_ids' => 'El empleado ya tiene uno de estos horarios durante el periodo indicado.',
            ]);
        }
    }

    private function payload(AttendanceScheduleAssignment $assignment): array
    {
        $employee = $assignment->assignable;
        $position = $employee?->position;

        return [
            'id' => $assignment->id,
            'employee_id' => $assignment->assignable_id,
            'attendance_schedule_id' => $assignment->attendance_schedule_id,
            'employee' => trim(($employee?->first_name ?? '').' '.($employee?->last_name ?? '')),
            'department' => $position?->department?->name ?? 'Sin departamento',
            'position' => $position?->name ?? 'Sin puesto',
            'schedule' => $assignment->schedule?->name,
            'effective_from' => $assignment->effective_from?->toDateString(),
            'effective_to' => $assignment->effective_to?->toDateString(),
            'working_days' => $assignment->working_days ?: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'observations' => $assignment->observations,
            'active' => $assignment->active,
            'assigned_by' => $assignment->assignedBy?->name ?? 'Sistema',
            'created_at' => $assignment->created_at?->format('d/m/Y H:i'),
        ];
    }

    private function assertEligibleSalesEmployee(int $employeeId): void
    {
        $eligible = Employee::query()
            ->whereKey($employeeId)
            ->whereHas('user.role', fn ($role) => $role->whereIn('name', ['Ventas', 'Vendedor']))
            ->exists();

        if (! $eligible) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'employee_id' => 'Solo puedes asignar horarios de asistencia a personal de Ventas o Vendedor.',
            ]);
        }
    }

    private function resequenceEmployeeShifts(int $employeeId): void
    {
        AttendanceScheduleAssignment::query()
            ->with('schedule:id,check_in_at,name')
            ->where('assignable_type', Employee::class)
            ->where('assignable_id', $employeeId)
            ->where('active', true)
            ->get()
            ->sortBy(fn (AttendanceScheduleAssignment $assignment) => sprintf('%s:%s', $assignment->schedule?->check_in_at ?? '99:99', $assignment->schedule?->name ?? ''))
            ->values()
            ->each(fn (AttendanceScheduleAssignment $assignment, int $index) => $assignment->update(['shift_order' => $index + 1]));
    }

    private function scheduleHours(AttendanceSchedule $schedule): string
    {
        $hours = collect([$schedule->check_in_at, $schedule->check_out_at])
            ->filter()
            ->map(fn ($time) => substr((string) $time, 0, 5))
            ->join(' - ');

        return $hours ?: 'Sin horario definido';
    }
}
