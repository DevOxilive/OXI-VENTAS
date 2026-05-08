<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Empleado extends Model
{
    protected $table = 'empleados';

    protected $fillable = [
        'nombre',
        'apellido',
        'correo',
        'telefono',
        'domicilio',
        'fecha_inicio',
        'estado',
        'foto',
        'puesto',
        'departamento',
        'banco',
        'numero_cuenta',
        'grado_estudios',
        'especialidad',
        'tipo_contrato',
        'antiguedad',
        'nss',
        'rfc'
    ];

    public function user()
    {
        return $this->hasOne(User::class);
    }
}