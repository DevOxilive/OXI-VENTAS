<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceIncident extends Model
{
    protected $fillable = ['employee_id', 'attendance_record_id', 'submitted_by', 'type', 'incident_date', 'incident_time', 'estimated_arrival_at', 'rest_day_requested', 'rest_day_date', 'make_up_date', 'reason', 'evidence_path', 'status', 'authorized_by', 'authorized_at', 'authorization_notes'];
    protected function casts(): array { return ['incident_date' => 'date', 'rest_day_requested' => 'boolean', 'rest_day_date' => 'date', 'make_up_date' => 'date', 'authorized_at' => 'datetime']; }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function attendanceRecord(): BelongsTo { return $this->belongsTo(AttendanceRecord::class); }
    public function submittedBy(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by'); }
    public function authorizedBy(): BelongsTo { return $this->belongsTo(User::class, 'authorized_by'); }
}
