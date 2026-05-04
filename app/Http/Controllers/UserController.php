<?php

namespace App\Http\Controllers;

use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        return inertia('Sistemas/Empleados', [
            'empleados' => User::all() 
        ]);
    }
}