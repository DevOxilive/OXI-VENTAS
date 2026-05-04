<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmpleadoController extends Controller
{
    public function index()
    {
        $empleados = Empleado::latest()->get();

        return Inertia::render('Recursos-humanos/Empleados', [
            'empleadosDB' => $empleados
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required',
            'apellido' => 'required',
            'correo' => 'required|email|unique:empleados,correo',
            'puesto' => 'required',
            'departamento' => 'required',
        ]);

        Empleado::create([
            'nombre' => $request->nombre,
            'apellido' => $request->apellido,
            'correo' => $request->correo,
            'telefono' => $request->telefono,
            'domicilio' => $request->domicilio,
            'fecha_inicio' => $request->fechaInicio,
            'estado' => $request->estado,
            'foto' => null,
            'puesto' => $request->puesto,
            'departamento' => $request->departamento,
            'banco' => $request->banco,
            'numero_cuenta' => $request->cuenta,
            'grado_estudios' => $request->grado,
            'especialidad' => $request->especialidad,
            'tipo_contrato' => $request->tipoContrato,
            'antiguedad' => $request->antiguedad,
            'nss' => $request->nss,
            'rfc' => $request->rfc,
        ]);

        return redirect()->back();
    }
}
