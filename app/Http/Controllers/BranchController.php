<?php
namespace App\Http\Controllers;

use App\Events\BranchChanged;
use App\Events\RealtimeActivityLogged;
use App\Models\Branch;
use App\Models\Product;
use App\Models\BranchProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;

class BranchController extends Controller
{
    private function checkPermission(string $permission): void
    {
        $user = request()->user();

        if (!$user) {
            abort(401);
        }

        $user->load(['permissions']);

        if (!$user->hasPermission($permission)) {
            abort(403, 'No tienes permiso');
        }
    }

  private function checkAnyPermission(array $permissions): void
{
    $user = request()->user();

    if (!$user) {
        abort(401);
    }

    $user->load(['permissions']);

    foreach ($permissions as $permission) {
        if ($user->hasPermission($permission)) {
            return;
        }
    }

    abort(403, 'No tienes permiso');
}
    public function index()
    {
        $this->checkAnyPermission([
            'branches.view',
            'branches.create',
            'branches.update',
            'branches.delete',
        ]);

        return Inertia::render('Systems/Branches', [
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->checkPermission('branches.create');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $branch = DB::transaction(function () use ($data) {
            $branch = Branch::create([
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'color' => $data['color'] ?? null,
                'active' => true,
            ]);

            Product::with(['barcodes'])
                ->where('active', true)
                ->chunk(500, function ($products) use ($branch) {
                    foreach ($products as $product) {
                        BranchProduct::updateOrCreate(
                            [
                                'branch_id' => $branch->id,
                                'product_id' => $product->id,
                            ],
                            [
                                'stock' => 0,
                                'min_stock' => 0,
                                'tracks_batches' => false,
                                'tracks_expiration' => false,
                                'status' => BranchProduct::STATUS_ACTIVE,
                            ]
                        );
                    }
                });

            return $branch;
        });

        Cache::forget('active_branches');
        broadcast(BranchChanged::fromBranch($branch, 'created'))->toOthers();
        event(RealtimeActivityLogged::message('creó', 'la sucursal', $branch->name, 'Sistemas', 'created'));

        return redirect()->back();
    }

    public function update(Request $request, Branch $branch)
    {
        $this->checkPermission('branches.update');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:20'],
            'active' => ['boolean'],
        ]);

        $branch->update([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'color' => $data['color'] ?? null,
            'active' => $data['active'] ?? true,
        ]);

        Cache::forget('active_branches');
        broadcast(BranchChanged::fromBranch($branch, 'updated'))->toOthers();
        event(RealtimeActivityLogged::message('actualizó', 'la sucursal', $branch->name, 'Sistemas', 'updated'));

        return redirect()->back();
    }

    public function destroy(Branch $branch)
    {
        $this->checkPermission('branches.delete');

        $branchId = $branch->id;
        $branchSlug = $branch->slug;
        $branchName = $branch->name;

        $branch->delete();

        Cache::forget('active_branches');
        broadcast(new BranchChanged('deleted', $branchId, $branchSlug))->toOthers();
        event(RealtimeActivityLogged::message('eliminó', 'la sucursal', $branchName, 'Sistemas', 'deleted'));

        return back()->with('success', 'Sucursal eliminada correctamente');
    }
}
