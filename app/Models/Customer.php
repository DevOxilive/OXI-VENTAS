<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
    ];

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function creditAccount()
    {
        return $this->hasOne(EmployeeCreditAccount::class);
    }
}
