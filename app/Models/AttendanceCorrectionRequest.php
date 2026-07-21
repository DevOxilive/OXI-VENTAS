<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCorrectionRequest extends Model
{
    protected $fillable = [
        'attendance_record_id', 'requested_by', 'reviewed_by', 'reason', 'requested_changes',
        'status', 'review_notes', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['requested_changes' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function attendanceRecord(): BelongsTo { return $this->belongsTo(AttendanceRecord::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
