<?php

namespace App\Http\Controllers;

use App\Events\AttendanceChanged;
use App\Models\AttendanceSchedule;
use App\Services\SystemAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AttendanceScheduleController extends Controller
{
    public function __construct(private readonly SystemAuditService $audit) {}

    public function index()
    {
        return Inertia::render('HumanResources/AttendanceSchedules', ['schedules' => AttendanceSchedule::query()->latest()->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['code'] = $this->makeCode($data['name']);
        $schedule = AttendanceSchedule::create($data);
        $this->audit->record('attendance_schedule', 'create', 'success', $request, ['record_type' => AttendanceSchedule::class, 'record_id' => $schedule->id, 'record_label' => $schedule->name]);
        broadcast(new AttendanceChanged(0, 'schedule_created', $request->user()->id));
        return back()->with('success', 'Horario creado correctamente.');
    }

    public function update(Request $request, AttendanceSchedule $attendanceSchedule)
    {
        $before = $attendanceSchedule->getOriginal();
        $attendanceSchedule->update($this->validated($request));
        $this->audit->record('attendance_schedule', 'update', 'success', $request, ['record_type' => AttendanceSchedule::class, 'record_id' => $attendanceSchedule->id, 'record_label' => $attendanceSchedule->name, 'old_values' => $before, 'new_values' => $attendanceSchedule->getChanges()]);
        broadcast(new AttendanceChanged(0, 'schedule_updated', $request->user()->id));
        return back()->with('success', 'Horario actualizado correctamente.');
    }

    public function destroy(Request $request, AttendanceSchedule $attendanceSchedule)
    {
        if ($attendanceSchedule->assignments()->exists()) {
            return back()->withErrors(['schedule' => 'No se puede eliminar un horario que ya tiene asignaciones. Desactívalo o reasigna primero al personal.']);
        }

        $label = $attendanceSchedule->name;
        $id = $attendanceSchedule->id;
        $attendanceSchedule->delete();

        $this->audit->record('attendance_schedule', 'delete', 'success', $request, [
            'record_type' => AttendanceSchedule::class,
            'record_id' => $id,
            'record_label' => $label,
        ]);
        broadcast(new AttendanceChanged(0, 'schedule_deleted', $request->user()->id));

        return back()->with('success', 'Horario eliminado correctamente.');
    }

    private function validated(Request $request): array
    {
        foreach (['check_in_at', 'check_out_at', 'meal_start_at', 'meal_end_at'] as $field) {
            $request->merge([$field => $this->normalizeTime($request->input($field))]);
        }

        $data = $request->validate(['name' => ['required','string','max:120'], 'description' => ['nullable','string','max:1000'], 'active' => ['required','boolean'], 'check_in_at' => ['nullable','date_format:H:i'], 'check_out_at' => ['nullable','date_format:H:i'], 'meal_start_at' => ['nullable','date_format:H:i'], 'meal_end_at' => ['nullable','date_format:H:i'], 'maximum_meal_minutes' => ['nullable','integer','min:0','max:1440'], 'expected_work_minutes' => ['nullable','integer','min:0','max:1440'], 'tolerances' => ['nullable','array'], 'tolerances.on_time_minutes' => ['nullable','integer','min:0','max:240'], 'daily_schedule' => ['nullable','array'], 'daily_schedule.*.check_in_at' => ['required','date_format:H:i'], 'daily_schedule.*.check_out_at' => ['required','date_format:H:i']]);
        $data['type'] = 'fixed';
        $data['working_days'] = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        return $data;
    }

    private function makeCode(string $name): string
    {
        return 'HR-'.Str::upper(Str::substr(Str::slug($name), 0, 16)).'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }

    private function normalizeTime(mixed $value): mixed
    {
        if (! is_string($value) || ! preg_match('/^(\d{1,2}):(\d{2})$/', $value, $matches)) {
            return $value;
        }

        return str_pad($matches[1], 2, '0', STR_PAD_LEFT).':'.$matches[2];
    }
}
