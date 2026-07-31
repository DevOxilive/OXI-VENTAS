<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

trait ValidatesRecordVersion
{
    protected function lockCurrentVersion(Request $request, Model $model): Model
    {
        $request->validate([
            'record_version' => ['required', 'date'],
        ], [
            'record_version.required' => 'No se pudo comprobar la version del registro. Vuelve a abrir el formulario.',
        ]);

        $current = $model->newQuery()
            ->whereKey($model->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        $expectedVersion = $request->date('record_version');

        if (!$current->updated_at || !$expectedVersion || !$current->updated_at->equalTo($expectedVersion)) {
            throw ValidationException::withMessages([
                'record_version' => 'Otra persona modifico este registro. Tus datos siguen en el formulario; revisa la informacion actual antes de volver a guardar.',
            ]);
        }

        return $current;
    }
}
