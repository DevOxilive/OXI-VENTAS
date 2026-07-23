<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSchedule extends Model
{
    protected $fillable = ['name', 'code', 'description', 'type', 'color', 'active', 'check_in_at', 'check_out_at', 'meal_start_at', 'meal_end_at', 'maximum_meal_minutes', 'expected_work_minutes', 'minimum_work_minutes', 'working_days', 'daily_schedule', 'tolerances', 'settings'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'working_days' => 'array', 'daily_schedule' => 'array', 'tolerances' => 'array', 'settings' => 'array'];
    }

    public function assignments(): HasMany { return $this->hasMany(AttendanceScheduleAssignment::class); }
}
