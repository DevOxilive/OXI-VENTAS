<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmpleadoController extends Controller
{
    public function index()
    {
        $empleados = Empleado::latest()->get()->map(function ($empleado) {
            return [
                'id' => $empleado->id,
                'nombre' => $empleado->nombre,
                'apellido' => $empleado->apellido,
                'correo' => $empleado->correo,
                'telefono' => $empleado->telefono,
                'domicilio' => $empleado->domicilio,
                'fechaInicio' => $empleado->fecha_inicio,
                'estado' => $empleado->estado,
                'foto' => $empleado->foto,
                'puesto' => $empleado->puesto,
                'departamento' => $empleado->departamento,
                'banco' => $empleado->banco,
                'cuenta' => $empleado->numero_cuenta,
                'grado' => $empleado->grado_estudios,
                'especialidad' => $empleado->especialidad,
                'tipoContrato' => $empleado->tipo_contrato,
                'antiguedad' => $empleado->antiguedad,
                'nss' => $empleado->nss,
                'rfc' => $empleado->rfc,
            ];
        });

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

    public function update(Request $request, $id)
    {
        $empleado = Empleado::findOrFail($id);

        $request->validate([
            'nombre' => 'required',
            'apellido' => 'required',
            'correo' => 'required|email|unique:empleados,correo,' . $id,
            'puesto' => 'required',
            'departamento' => 'required',
        ]);

        $empleado->update([
            'nombre' => $request->nombre,
            'apellido' => $request->apellido,
            'correo' => $request->correo,
            'telefono' => $request->telefono,
            'domicilio' => $request->domicilio,
            'fecha_inicio' => $request->fechaInicio,
            'estado' => $request->estado,
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

    public function destroy($id)
    {
        $empleado = Empleado::findOrFail($id);
        $empleado->delete();

        return redirect()->back();
    }
}
