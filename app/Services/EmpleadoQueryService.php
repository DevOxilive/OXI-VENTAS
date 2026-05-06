<?php

namespace App\Services;

use App\Models\Empleado;

class EmpleadoQueryService
{
    public function build(array $filters)
    {
        $query = Empleado::query();

        if (!empty($filters['departamento'])) {
            $query->where('departamento', $filters['departamento']);
        }

        if (!empty($filters['estado'])) {
            $query->where('estado', $filters['estado']);
        }

        if (!empty($filters['puesto'])) {
            $query->where('puesto', 'like', '%' . $filters['puesto'] . '%');
        }

        if (!empty($filters['tipo_contrato'])) {
            $query->where('tipo_contrato', 'like', '%' . $filters['tipo_contrato'] . '%');
        }

        return $query;
    }
}
