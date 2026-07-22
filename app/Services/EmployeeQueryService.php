<?php

namespace App\Services;

use App\Models\Employee;

class EmployeeQueryService
{
    public function build(array $filters)
    {
        $query = Employee::query();

        if (!empty($filters['department'])) {
            $query->whereHas('position', function ($positionQuery) use ($filters) {
                $positionQuery->where('department_id', $filters['department']);
            });
        }

        if (!empty($filters['employment_status'])) {
            $query->where('employment_status', $filters['employment_status']);
        }

        if (!empty($filters['position'])) {
            $query->where('position_id', $filters['position']);
        }

        if (!empty($filters['contract_type'])) {
            $query->where('contract_type', 'like', '%' . $filters['contract_type'] . '%');
        }

        return $query;
    }
}
