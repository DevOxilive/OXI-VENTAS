<?php

namespace App\Services;

use App\Models\AttendanceIncident;
use App\Models\AttendanceSchedule;
use App\Models\AttendanceScheduleAssignment;
use App\Models\Employee;
use Illuminate\Support\Carbon;

class AttendanceRuleEngine
{
    public function evaluate(?Employee $employee, string $type, Carbon $recordedAt): string
    {
        if ($type !== 'check_in') return 'on_time';

        $schedule = $employee ? $this->scheduleFor($employee, $recordedAt) : null;
        if (! $schedule?->check_in_at) return 'on_time';

        $dayKey = strtolower($recordedAt->englishDayOfWeek);
        $dailySchedule = $schedule->daily_schedule[$dayKey] ?? [];
        $checkInAt = $dailySchedule['check_in_at'] ?? $schedule->check_in_at;

        $scheduledAt = Carbon::parse($recordedAt->toDateString().' '.$checkInAt);
        $minutesLate = max(0, $scheduledAt->diffInMinutes($recordedAt, false));
        if ($minutesLate === 0) return 'on_time';

        if ($this->hasApprovedIncident($employee, $recordedAt)) return 'late_justified';

        $tolerances = $schedule->tolerances ?? [];
        $onTimeLimit = (int) ($tolerances['on_time_minutes'] ?? 10);
        return $minutesLate <= $onTimeLimit ? 'on_time' : 'late';
    }

    public function scheduleFor(Employee $employee, Carbon $date): ?AttendanceSchedule
    {
        return AttendanceScheduleAssignment::query()
            ->with('schedule')
            ->where('assignable_type', Employee::class)
            ->where('assignable_id', $employee->id)
            ->where('active', true)
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', $date))
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->orderBy('priority')
            ->get()
            ->pluck('schedule')
            ->first(fn ($schedule) => $schedule?->active);
    }

    private function hasApprovedIncident(Employee $employee, Carbon $date): bool
    {
        return AttendanceIncident::query()
            ->where('employee_id', $employee->id)
            ->whereDate('incident_date', $date)
            ->where('status', 'approved')
            ->exists();
    }
}
