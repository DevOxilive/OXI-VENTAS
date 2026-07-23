<?php

namespace App\Http\Controllers;

use App\Events\AttendanceChanged;
use App\Models\AttendanceSchedule;
use App\Models\AttendanceScheduleAssignment;
use App\Models\Employee;
use App\Services\SystemAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AttendanceScheduleAssignmentController extends Controller
{
    public function __construct(private readonly SystemAuditService $audit) {}

    public function index()
    {
        return Inertia::render('HumanResources/AttendanceScheduleAssignments', [
            'assignments' => AttendanceScheduleAssignment::query()->with(['schedule','assignable','assignedBy'])->where('assignable_type', Employee::class)->latest()->paginate(30)->through(fn ($assignment) => $this->payload($assignment)),
            'employees' => Employee::query()->where('employment_status', '!=', 'Inactivo')->orderBy('first_name')->get(['id','first_name','last_name','department','position'])->map(fn ($employee) => ['value' => $employee->id, 'label' => trim($employee->first_name.' '.$employee->last_name)]),
            'schedules' => AttendanceSchedule::query()->where('active', true)->orderBy('name')->get(['id','name'])->map(fn ($schedule) => ['value' => $schedule->id, 'label' => $schedule->name]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $assignment = null;
        DB::transaction(function () use ($data, $request, &$assignment) {
            AttendanceScheduleAssignment::query()->where('assignable_type', Employee::class)->where('assignable_id', $data['employee_id'])->where('active', true)->whereNull('effective_to')->whereDate('effective_from', '<=', $data['effective_from'])->update(['effective_to' => Carbon::parse($data['effective_from'])->subDay()->toDateString(), 'updated_at' => now()]);
            $assignment = AttendanceScheduleAssignment::create(['attendance_schedule_id' => $data['attendance_schedule_id'], 'assignable_type' => Employee::class, 'assignable_id' => $data['employee_id'], 'effective_from' => $data['effective_from'], 'effective_to' => $data['effective_to'] ?? null, 'observations' => $data['observations'] ?? null, 'working_days' => $data['working_days'], 'active' => $data['active'], 'assigned_by' => $request->user()->id]);
        });
        $this->audit->record('attendance_schedule_assignment', 'create', 'success', $request, ['record_type' => AttendanceScheduleAssignment::class, 'record_id' => $assignment->id, 'record_label' => 'Asignación de horario']);
        broadcast(new AttendanceChanged(0, 'schedule_assignment_created', $request->user()->id));
        return back()->with('success', 'Horario asignado y vigencia anterior actualizada.');
    }

    public function update(Request $request, AttendanceScheduleAssignment $attendanceScheduleAssignment)
    {
        abort_unless($attendanceScheduleAssignment->assignable_type === Employee::class, 404);
        $data = $this->validated($request);
        $before = $attendanceScheduleAssignment->getOriginal();
        $attendanceScheduleAssignment->update(['attendance_schedule_id' => $data['attendance_schedule_id'], 'assignable_id' => $data['employee_id'], 'effective_from' => $data['effective_from'], 'effective_to' => $data['effective_to'] ?? null, 'observations' => $data['observations'] ?? null, 'working_days' => $data['working_days'], 'active' => $data['active']]);
        $this->audit->record('attendance_schedule_assignment', 'update', 'success', $request, ['record_type' => AttendanceScheduleAssignment::class, 'record_id' => $attendanceScheduleAssignment->id, 'old_values' => $before, 'new_values' => $attendanceScheduleAssignment->getChanges()]);
        broadcast(new AttendanceChanged(0, 'schedule_assignment_updated', $request->user()->id));
        return back()->with('success', 'Asignación actualizada correctamente.');
    }

    public function destroy(Request $request, AttendanceScheduleAssignment $attendanceScheduleAssignment)
    {
        $attendanceScheduleAssignment->update(['active' => false, 'effective_to' => $attendanceScheduleAssignment->effective_to ?? now()->toDateString()]);
        $this->audit->record('attendance_schedule_assignment', 'deactivate', 'success', $request, ['record_type' => AttendanceScheduleAssignment::class, 'record_id' => $attendanceScheduleAssignment->id]);
        broadcast(new AttendanceChanged(0, 'schedule_assignment_deactivated', $request->user()->id));
        return back()->with('success', 'Asignación desactivada; el historial se conserva.');
    }

    private function validated(Request $request): array
    {
        return $request->validate(['employee_id' => ['required','exists:employees,id'], 'attendance_schedule_id' => ['required','exists:attendance_schedules,id'], 'effective_from' => ['required','date'], 'effective_to' => ['nullable','date','after_or_equal:effective_from'], 'active' => ['required','boolean'], 'observations' => ['nullable','string','max:2000'], 'working_days' => ['required','array','min:1'], 'working_days.*' => ['in:monday,tuesday,wednesday,thursday,friday,saturday,sunday']]);
    }

    private function payload(AttendanceScheduleAssignment $assignment): array
    {
        $employee = $assignment->assignable;
        return ['id' => $assignment->id, 'employee_id' => $assignment->assignable_id, 'attendance_schedule_id' => $assignment->attendance_schedule_id, 'employee' => trim(($employee?->first_name ?? '').' '.($employee?->last_name ?? '')), 'department' => $employee?->department, 'position' => $employee?->position, 'schedule' => $assignment->schedule?->name, 'effective_from' => $assignment->effective_from?->toDateString(), 'effective_to' => $assignment->effective_to?->toDateString(), 'working_days' => $assignment->working_days ?: ['monday','tuesday','wednesday','thursday','friday'], 'observations' => $assignment->observations, 'active' => $assignment->active, 'assigned_by' => $assignment->assignedBy?->name ?? 'Sistema', 'created_at' => $assignment->created_at?->format('d/m/Y H:i')];
    }
}
