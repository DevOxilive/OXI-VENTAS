<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemAudit extends Model
{
    protected $fillable = [
        'user_id', 'user_name', 'role_name', 'module', 'action',
        'auditable_type', 'auditable_id', 'record_label', 'result',
        'observations', 'ip_address', 'user_agent', 'browser',
        'operating_system', 'device', 'url', 'method', 'metadata', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
