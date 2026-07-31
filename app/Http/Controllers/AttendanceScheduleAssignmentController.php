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
        $perPage = TablePagination::resolvePerPage($request, 30, [10, 30, 50, 100]);

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
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name'])
                ->map(fn ($employee) => ['value' => $employee->id, 'label' => trim($employee->first_name . ' ' . $employee->last_name)]),
            'schedules' => AttendanceSchedule::query()->where('active', true)->orderBy('name')->get(['id','name'])->map(fn ($schedule) => ['value' => $schedule->id, 'label' => $schedule->name]),
            'filters' => [
                'per_page' => $perPage,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $assignment = null;
        DB::transaction(function () use ($data, $request, &$assignment) {
            Employee::query()->lockForUpdate()->findOrFail($data['employee_id']);
            AttendanceScheduleAssignment::query()->where('assignable_type', Employee::class)->where('assignable_id', $data['employee_id'])->where('active', true)->whereNull('effective_to')->whereDate('effective_from', '<=', $data['effective_from'])->update(['effective_to' => Carbon::parse($data['effective_from'])->subDay()->toDateString(), 'updated_at' => now()]);
            $this->assertNoOverlappingAssignment($data);
            $assignment = AttendanceScheduleAssignment::create(['attendance_schedule_id' => $data['attendance_schedule_id'], 'assignable_type' => Employee::class, 'assignable_id' => $data['employee_id'], 'effective_from' => $data['effective_from'], 'effective_to' => $data['effective_to'] ?? null, 'observations' => $data['observations'] ?? null, 'working_days' => $data['working_days'], 'active' => $data['active'], 'assigned_by' => $request->user()->id]);
        }, 3);
        $this->audit->record('attendance_schedule_assignment', 'create', 'success', $request, ['record_type' => AttendanceScheduleAssignment::class, 'record_id' => $assignment->id, 'record_label' => 'Asignación de horario']);
        broadcast(new AttendanceChanged($assignment->id, 'schedule_assignment_created', $request->user()->id));
        return back()->with('success', 'Horario asignado y vigencia anterior actualizada.');
    }

    public function update(Request $request, AttendanceScheduleAssignment $attendanceScheduleAssignment)
    {
        abort_unless($attendanceScheduleAssignment->assignable_type === Employee::class, 404);
        $data = $this->validated($request);
        $before = $attendanceScheduleAssignment->getOriginal();
        DB::transaction(function () use ($attendanceScheduleAssignment, $data) {
            Employee::query()
                ->whereIn('id', collect([$attendanceScheduleAssignment->assignable_id, $data['employee_id']])->sort()->values())
                ->lockForUpdate()
                ->get();
            $assignment = AttendanceScheduleAssignment::query()->lockForUpdate()->findOrFail($attendanceScheduleAssignment->id);
            $this->assertNoOverlappingAssignment($data, $assignment->id);
            $assignment->update(['attendance_schedule_id' => $data['attendance_schedule_id'], 'assignable_id' => $data['employee_id'], 'effective_from' => $data['effective_from'], 'effective_to' => $data['effective_to'] ?? null, 'observations' => $data['observations'] ?? null, 'working_days' => $data['working_days'], 'active' => $data['active']]);
        }, 3);
        $attendanceScheduleAssignment->refresh();
        $this->audit->record('attendance_schedule_assignment', 'update', 'success', $request, ['record_type' => AttendanceScheduleAssignment::class, 'record_id' => $attendanceScheduleAssignment->id, 'old_values' => $before, 'new_values' => $attendanceScheduleAssignment->getChanges()]);
        broadcast(new AttendanceChanged($attendanceScheduleAssignment->id, 'schedule_assignment_updated', $request->user()->id));
        return back()->with('success', 'Asignación actualizada correctamente.');
    }

    public function destroy(Request $request, AttendanceScheduleAssignment $attendanceScheduleAssignment)
    {
        abort_unless($attendanceScheduleAssignment->assignable_type === Employee::class, 404);
        $assignmentId = $attendanceScheduleAssignment->id;
        $attendanceScheduleAssignment->delete();
        $this->audit->record('attendance_schedule_assignment', 'delete', 'success', $request, ['record_type' => AttendanceScheduleAssignment::class, 'record_id' => $assignmentId, 'record_label' => 'Asignación de horario']);
        broadcast(new AttendanceChanged($assignmentId, 'schedule_assignment_deleted', $request->user()->id));
        return back()->with('success', 'Asignación eliminada correctamente. El empleado y el horario se conservan.');
    }

    private function validated(Request $request): array
    {
        return $request->validate(['employee_id' => ['required','exists:employees,id'], 'attendance_schedule_id' => ['required','exists:attendance_schedules,id'], 'effective_from' => ['required','date'], 'effective_to' => ['nullable','date','after_or_equal:effective_from'], 'active' => ['required','boolean'], 'observations' => ['nullable','string','max:2000'], 'working_days' => ['required','array','min:1'], 'working_days.*' => ['in:monday,tuesday,wednesday,thursday,friday,saturday,sunday']]);
    }

    private function assertNoOverlappingAssignment(array $data, ?int $exceptId = null): void
    {
        if (! $data['active']) {
            return;
        }

        $start = Carbon::parse($data['effective_from'])->toDateString();
        $end = filled($data['effective_to'] ?? null)
            ? Carbon::parse($data['effective_to'])->toDateString()
            : null;
        $overlaps = AttendanceScheduleAssignment::query()
            ->where('assignable_type', Employee::class)
            ->where('assignable_id', $data['employee_id'])
            ->where('active', true)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $start))
            ->when($end, fn ($query) => $query->whereDate('effective_from', '<=', $end))
            ->when(! $end, fn ($query) => $query->whereNotNull('effective_from'))
            ->lockForUpdate()
            ->exists();

        if ($overlaps) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'effective_from' => 'El empleado ya tiene un horario activo que se cruza con este periodo.',
            ]);
        }
    }

    private function payload(AttendanceScheduleAssignment $assignment): array
    {
        $employee = $assignment->assignable;
        $position = $employee?->position;

        return ['id' => $assignment->id, 'employee_id' => $assignment->assignable_id, 'attendance_schedule_id' => $assignment->attendance_schedule_id, 'employee' => trim(($employee?->first_name ?? '').' '.($employee?->last_name ?? '')), 'department' => $position?->department?->name ?? 'Sin departamento', 'position' => $position?->name ?? 'Sin puesto', 'schedule' => $assignment->schedule?->name, 'effective_from' => $assignment->effective_from?->toDateString(), 'effective_to' => $assignment->effective_to?->toDateString(), 'working_days' => $assignment->working_days ?: ['monday','tuesday','wednesday','thursday','friday'], 'observations' => $assignment->observations, 'active' => $assignment->active, 'assigned_by' => $assignment->assignedBy?->name ?? 'Sistema', 'created_at' => $assignment->created_at?->format('d/m/Y H:i')];
    }
}
