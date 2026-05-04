<?php

namespace App\Http\Controllers;

use App\Models\Role;

class RegisterController extends Controller
{
    public function create()
    {
        return inertia('Auth/Register', [
            'roles' => Role::all()
        ]);
    }
}