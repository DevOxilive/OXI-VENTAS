<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceIncident extends Model
{
    protected $fillable = ['employee_id', 'attendance_record_id', 'type', 'incident_date', 'incident_time', 'estimated_arrival_at', 'reason', 'evidence_path', 'status', 'authorized_by', 'authorized_at', 'authorization_notes'];
    protected function casts(): array { return ['incident_date' => 'date', 'authorized_at' => 'datetime']; }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function attendanceRecord(): BelongsTo { return $this->belongsTo(AttendanceRecord::class); }
    public function authorizedBy(): BelongsTo { return $this->belongsTo(User::class, 'authorized_by'); }
}
