<?php

namespace App\Models;

use App\Models\Sepomex\Colony;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [

        //Datos Generales
        'names',
        'last_name',
        'gender',
        //Datos Laborales
        'contract_type',
        'contract_duration',
        'start_date',
        'expired_date',
        'termination_date',
        'termination_description',

        'birth_date',
        //Datos de Contacto
        'telephone_one',
        'telephone_two',
        'email',
        //Datos de emergencia
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_number',
        'ailments',
        'current_treatments',
        'allergies',
        //Datos unicos de población
        'curp',
        'rfc',
        'nss',
        //Datos Bancarios
        'bank_id',
        'bank_account',
        //Datos Profesionales
        'last_level_educational',
        'specialty_area',
        'professional_id',
        //Domicilio
        'street',
        'out_number',
        'int_number',
        'colony_id',
        'first_reference_street',
        'second_reference_street',
        'address_reference',
        'address_url',
    ];

    public function user()
    {
        return $this->hasOne(User::class, 'id_employee');
    }
}