<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Employee extends Model
{
    protected $table = 'employees';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'street',
        'external_number',
        'internal_number',
        'postal_code',
        'neighborhood',
        'municipality',
        'address_state',
        'maps_url',
        'start_date',
        'employment_status',
        'photo',
        'position',
        'department',
        'bank',
        'account_number',
        'education_level',
        'specialty',
        'contract_type',
        'seniority',
        'nss',
        'rfc',
    ];

    public function user()
    {
        return $this->hasOne(User::class);
    }
}
