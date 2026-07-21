<?php

namespace App\Http\Controllers;

use App\Models\SystemAudit;
use App\Support\SystemPermission;
use App\Support\TrashRegistry;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SystemAdministrationController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizePermission($request, SystemPermission::ACCESS_CENTER);

        return Inertia::render('SystemAdministration/Index', [
            'auditCount' => SystemAudit::query()->count(),
            'trashCount' => collect(TrashRegistry::resources())
                ->sum(fn (array $resource) => TrashRegistry::query($resource['key'])->count()),
        ]);
    }

    protected function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($request->user()?->hasPermission($permission), 403);
    }
}
