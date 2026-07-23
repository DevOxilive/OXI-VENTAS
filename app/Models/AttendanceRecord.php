<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceRecord extends Model
{
    use HasFactory;

    public const TYPES = [
        'check_in', 'meal_start', 'meal_end', 'check_out',
    ];

    public const STATUSES = [
        'on_time', 'late', 'absent', 'justified', 'outside_zone', 'pending', 'corrected',
    ];

    protected $fillable = [
        'user_id', 'employee_id', 'branch_id', 'attendance_date', 'recorded_at', 'type', 'status',
        'latitude', 'longitude', 'location_accuracy', 'approximate_address', 'within_geofence',
        'geofence_snapshot', 'authentication_method', 'authentication_result', 'operating_system',
        'browser', 'device_type', 'user_agent', 'ip_address', 'selfie_path', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'recorded_at' => 'datetime',
            'within_geofence' => 'boolean',
            'geofence_snapshot' => 'array',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function correctionRequests(): HasMany { return $this->hasMany(AttendanceCorrectionRequest::class); }
}
