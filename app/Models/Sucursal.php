<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sucursal extends Model
{
    protected $table = 'sucursales';

    protected $fillable = [
        'nombre',
        'activa',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}