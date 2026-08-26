<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $table = 'employees';

    protected $fillable = [
        'first_name',
        'last_name',
        'birth_date',
        'email',
        'phone',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
        'secondary_emergency_contact_name',
        'secondary_emergency_contact_relationship',
        'secondary_emergency_contact_phone',
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
        'position_id',
        'bank',
        'account_number',
        'bank_clabe',
        'bank_card_number',
        'education_level',
        'specialty',
        'contract_type',
        'nss',
        'rfc',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'start_date' => 'date',
    ];

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function creditAccount()
    {
        return $this->hasOne(EmployeeCreditAccount::class);
    }
}
