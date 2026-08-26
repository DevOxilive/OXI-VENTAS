<?php

namespace App\Http\Controllers;

use App\Events\BranchChanged;
use App\Events\RealtimeActivityLogged;
use App\Http\Controllers\Concerns\ValidatesRecordVersion;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class BranchController extends Controller
{
    use ValidatesRecordVersion;

    private function checkPermission(string $permission): void
    {
        $user = request()->user();

        if (! $user) {
            abort(401);
        }

        $user->load(['permissions']);

        if (! $user->hasPermission($permission)) {
            abort(403, 'No tienes permiso');
        }
    }

    private function checkAnyPermission(array $permissions): void
    {
        $user = request()->user();

        if (! $user) {
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
        $currentUser = Auth::user();
        $canViewBranches = $currentUser?->hasPermission('branches.view') ?? false;
        $canCreateBranches = $currentUser?->hasPermission('branches.create') ?? false;
        $canUpdateBranches = $currentUser?->hasPermission('branches.update') ?? false;
        $canDeleteBranches = $currentUser?->hasPermission('branches.delete') ?? false;
        $canManageExistingBranches = $canViewBranches || $canUpdateBranches || $canDeleteBranches;

        $this->checkAnyPermission([
            'branches.view',
            'branches.create',
            'branches.update',
            'branches.delete',
        ]);

        return Inertia::render('Systems/Branches', [
            'branches' => Branch::query()
                ->when(! $canManageExistingBranches, fn ($query) => $query->whereRaw('1 = 0'))
                ->orderBy('name')
                ->get(),
            'capabilities' => [
                'viewBranches' => $canViewBranches,
                'createBranches' => $canCreateBranches,
                'updateBranches' => $canUpdateBranches,
                'deleteBranches' => $canDeleteBranches,
            ],
            'googleMapsApiKey' => config('services.google_maps.api_key'),
            'googleMapsMapId' => config('services.google_maps.map_id'),
        ]);
    }

    public function geocode(Request $request)
    {
        $this->checkAnyPermission(['branches.create', 'branches.update']);

        $data = $request->validate([
            'address' => ['nullable', 'string', 'max:500', 'required_without_all:latitude,longitude'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_without:address'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_without:address'],
        ]);

        $apiKey = config('services.google_maps.geocoding_key');

        if (blank($apiKey)) {
            return response()->json([
                'message' => 'La llave privada de Geocoding no esta configurada.',
            ], 503);
        }

        $isReverseGeocode = filled($data['latitude'] ?? null) && filled($data['longitude'] ?? null);
        $lookup = $isReverseGeocode
            ? number_format((float) $data['latitude'], 7, '.', '').','.number_format((float) $data['longitude'], 7, '.', '')
            : trim($data['address']);
        $cacheKey = 'branch-geocode:'.sha1(($isReverseGeocode ? 'coordinates:' : 'address:').mb_strtolower($lookup));
        $result = Cache::get($cacheKey);

        if (! $result) {
            $parameters = [
                'key' => $apiKey,
                'region' => 'mx',
            ];
            $parameters[$isReverseGeocode ? 'latlng' : 'address'] = $lookup;
            $response = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/geocode/json', $parameters);

            if ($response->successful() && $response->json('status') === 'OK') {
                $location = $response->json('results.0.geometry.location');

                if (isset($location['lat'], $location['lng'])) {
                    $result = [
                        'formatted_address' => $response->json('results.0.formatted_address'),
                        'latitude' => (float) $location['lat'],
                        'longitude' => (float) $location['lng'],
                        'address_fields' => $this->addressFieldsFromGoogleComponents(
                            $response->json('results.0.address_components', []),
                        ),
                    ];

                    Cache::put($cacheKey, $result, now()->addDays(30));
                }
            }
        }

        if (! $result) {
            return response()->json([
                'message' => 'No fue posible encontrar esa direccion. Revisa los datos o marca el punto directamente en el mapa.',
            ], 422);
        }

        return response()->json([
            ...$result,
            'maps_url' => 'https://www.google.com/maps/search/?api=1&query='.$result['latitude'].','.$result['longitude'],
        ]);
    }

    private function addressFieldsFromGoogleComponents(array $components): array
    {
        $byType = [];

        foreach ($components as $component) {
            foreach ($component['types'] ?? [] as $type) {
                $byType[$type] ??= $component['long_name'] ?? null;
            }
        }

        return array_filter([
            'street' => $byType['route'] ?? null,
            'external_number' => $byType['street_number'] ?? null,
            'postal_code' => $byType['postal_code'] ?? null,
            'neighborhood' => $byType['neighborhood'] ?? $byType['sublocality'] ?? $byType['sublocality_level_1'] ?? null,
            'municipality' => $byType['locality'] ?? $byType['administrative_area_level_2'] ?? null,
            'address_state' => $byType['administrative_area_level_1'] ?? null,
        ], fn ($value) => filled($value));
    }

    public function store(Request $request)
    {
        $this->checkPermission('branches.create');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:20'],
            'street' => ['nullable', 'string', 'max:255'],
            'external_number' => ['nullable', 'string', 'max:50'],
            'internal_number' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'string', 'max:5'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'municipality' => ['nullable', 'string', 'max:255'],
            'address_state' => ['nullable', 'string', 'max:255'],
            'maps_url' => ['nullable', 'string', 'max:1000'],
            'attendance_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'attendance_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'attendance_geofence_radius_meters' => ['nullable', 'integer', 'min:10', 'max:1000'],
        ]);
        $slug = $this->validateAvailableSlug($data['name']);

        $branch = DB::transaction(function () use ($data, $slug) {
            $branch = Branch::create([
                'name' => $data['name'],
                'slug' => $slug,
                'color' => $data['color'] ?? null,
                'address' => $this->formatAddress($data),
                'street' => $data['street'] ?? null,
                'external_number' => $data['external_number'] ?? null,
                'internal_number' => $data['internal_number'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'neighborhood' => $data['neighborhood'] ?? null,
                'municipality' => $data['municipality'] ?? null,
                'address_state' => $data['address_state'] ?? null,
                'maps_url' => $data['maps_url'] ?? null,
                'attendance_latitude' => $data['attendance_latitude'] ?? null,
                'attendance_longitude' => $data['attendance_longitude'] ?? null,
                'attendance_geofence_radius_meters' => $data['attendance_geofence_radius_meters'] ?? 100,
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
            'street' => ['nullable', 'string', 'max:255'],
            'external_number' => ['nullable', 'string', 'max:50'],
            'internal_number' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'string', 'max:5'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'municipality' => ['nullable', 'string', 'max:255'],
            'address_state' => ['nullable', 'string', 'max:255'],
            'maps_url' => ['nullable', 'string', 'max:1000'],
            'active' => ['boolean'],
            'attendance_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'attendance_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'attendance_geofence_radius_meters' => ['nullable', 'integer', 'min:10', 'max:1000'],
        ]);
        $slug = $this->validateAvailableSlug($data['name'], $branch);

        $branch = DB::transaction(function () use ($request, $branch, $data, $slug) {
            $branch = $this->lockCurrentVersion($request, $branch);
            $branch->update([
                'name' => $data['name'],
                'slug' => $slug,
                'color' => $data['color'] ?? null,
                'address' => $this->formatAddress($data),
                'street' => $data['street'] ?? null,
                'external_number' => $data['external_number'] ?? null,
                'internal_number' => $data['internal_number'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'neighborhood' => $data['neighborhood'] ?? null,
                'municipality' => $data['municipality'] ?? null,
                'address_state' => $data['address_state'] ?? null,
                'maps_url' => $data['maps_url'] ?? null,
                'attendance_latitude' => $data['attendance_latitude'] ?? null,
                'attendance_longitude' => $data['attendance_longitude'] ?? null,
                'attendance_geofence_radius_meters' => $data['attendance_geofence_radius_meters'] ?? 100,
                'active' => $data['active'] ?? true,
            ]);

            return $branch;
        });

        Cache::forget('active_branches');
        broadcast(BranchChanged::fromBranch($branch, 'updated'))->toOthers();
        event(RealtimeActivityLogged::message('actualizó', 'la sucursal', $branch->name, 'Sistemas', 'updated'));

        return redirect()->back();
    }

    public function destroy(Request $request, Branch $branch)
    {
        $this->checkPermission('branches.delete');

        $branchId = $branch->id;
        $branchSlug = $branch->slug;
        $branchName = $branch->name;

        DB::transaction(function () use ($request, $branch) {
            $this->lockCurrentVersion($request, $branch)->delete();
        });

        Cache::forget('active_branches');
        broadcast(new BranchChanged('deleted', $branchId, $branchSlug))->toOthers();
        event(RealtimeActivityLogged::message('eliminó', 'la sucursal', $branchName, 'Sistemas', 'deleted'));

        return back()->with('success', 'Sucursal eliminada correctamente');
    }

    private function validateAvailableSlug(string $name, ?Branch $currentBranch = null): string
    {
        $slug = Str::slug($name);

        if ($slug === '') {
            throw ValidationException::withMessages([
                'name' => 'Escribe un nombre valido para la sucursal.',
            ]);
        }

        $exists = Branch::withTrashed()
            ->where('slug', $slug)
            ->when($currentBranch, fn ($query) => $query->whereKeyNot($currentBranch->id))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'Ya existe una sucursal registrada o eliminada con ese nombre. Cambia el nombre o recupera la sucursal eliminada.',
            ]);
        }

        return $slug;
    }

    private function formatAddress(array $data): ?string
    {
        $address = collect([
            $data['street'] ?? null,
            $data['external_number'] ?? null,
            $data['internal_number'] ?? null,
            $data['neighborhood'] ?? null,
            $data['municipality'] ?? null,
            $data['address_state'] ?? null,
            $data['postal_code'] ?? null,
        ])
            ->filter(fn ($value) => filled($value))
            ->implode(', ');

        return $address !== '' ? $address : null;
    }
}
