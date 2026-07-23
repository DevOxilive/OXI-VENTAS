<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AttendanceScheduleAssignment extends Model
{
    protected $fillable = ['attendance_schedule_id', 'assignable_type', 'assignable_id', 'effective_from', 'effective_to', 'priority', 'observations', 'working_days', 'assigned_by', 'active'];
    protected function casts(): array { return ['effective_from' => 'date', 'effective_to' => 'date', 'working_days' => 'array', 'active' => 'boolean']; }
    public function schedule(): BelongsTo { return $this->belongsTo(AttendanceSchedule::class, 'attendance_schedule_id'); }
    public function assignable(): MorphTo { return $this->morphTo(); }
    public function assignedBy(): BelongsTo { return $this->belongsTo(User::class, 'assigned_by'); }
}
