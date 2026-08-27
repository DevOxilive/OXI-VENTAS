<?php

namespace App\Http\Controllers\Inventory;

use App\Events\ProductChanged;
use App\Events\RealtimeActivityLogged;
use App\Http\Controllers\Concerns\AuthorizesBranchAccess;
use App\Http\Controllers\Concerns\ValidatesRecordVersion;
use App\Http\Controllers\Controller;
use App\Models\Barcode;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductDepartment;
use App\Support\FlexibleSearch;
use App\Support\SystemPermission;
use App\Support\TablePagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

class ProductController extends Controller
{
    use AuthorizesBranchAccess, ValidatesRecordVersion;

    private const PRODUCT_IMAGE_PRIVATE_DISK = 'local';
    private const PRODUCT_IMAGE_LEGACY_DISK = 'public';

    private function calculateMarginPercentage($cost, $salePrice): ?float
    {
        $cost = (float) $cost;
        $salePrice = (float) $salePrice;

        if ($cost <= 0) {
            return null;
        }

        return round((($salePrice - $cost) / $cost) * 100, 2);
    }

    private function canManagePricing(Request $request): bool
    {
        return in_array($request->user()?->role?->name, [
            'Administrador',
            'Super Administrador',
        ], true);
    }

    private function resolvePresentationPricing(array &$data, bool $canManagePricing): void
    {
        $hasBoxPresentation = (bool) ($data['has_box_presentation'] ?? false);
        $costPerPiece = (float) $data['cost_per_piece'];
        $costPerBox = $hasBoxPresentation ? (float) $data['cost_per_box'] : null;

        if (!$canManagePricing) {
            $data['sale_price_per_piece'] = round($costPerPiece * 1.10, 2);
            $data['sale_price_per_box'] = $hasBoxPresentation
                ? round($costPerBox * 1.10, 2)
                : null;
        }

        $pieceMargin = $this->calculateMarginPercentage($costPerPiece, $data['sale_price_per_piece']);
        $boxMargin = $hasBoxPresentation
            ? $this->calculateMarginPercentage($costPerBox, $data['sale_price_per_box'])
            : null;

        $margins = array_filter([$pieceMargin, $boxMargin], fn ($margin) => $margin !== null);

        if ($margins !== [] && min($margins) < 10 && !($canManagePricing && ($data['allow_low_margin'] ?? false))) {
            throw ValidationException::withMessages([
                'sale_price_per_piece' => 'El porcentaje de ganancia no puede ser menor al 10%. Un administrador debe autorizar esta excepción.',
            ]);
        }

        // Existing modules keep using the base-unit fields until their own migration.
        $data['cost'] = $costPerPiece;
        $data['sale_price'] = $data['sale_price_per_piece'];
        $data['margin_percentage'] = $pieceMargin;
    }

    /**
     * El precio de venta y su margen son datos administrados. Inventario puede
     * registrar el producto, pero el sistema calcula un margen inicial del 10%.
     */
    private function resolvePricing(array &$data, bool $canManagePricing, ?Product $currentProduct = null): void
    {
        if (!$canManagePricing) {
            $data['sale_price'] = $currentProduct
                ? (float) $currentProduct->sale_price
                : round(((float) $data['cost']) * 1.10, 2);
        }

        $margin = $this->calculateMarginPercentage($data['cost'], $data['sale_price']);
        $pricingChanged = !$currentProduct
            || (float) $currentProduct->cost !== (float) $data['cost']
            || (float) $currentProduct->sale_price !== (float) $data['sale_price'];

        if ($pricingChanged && $margin !== null && $margin < 10 && !($canManagePricing && ($data['allow_low_margin'] ?? false))) {
            throw ValidationException::withMessages([
                'sale_price' => 'El porcentaje de ganancia no puede ser menor al 10%. Un administrador debe autorizar esta excepción.',
            ]);
        }

        $data['margin_percentage'] = $margin;
    }

    public function index(Request $request, Branch $branch)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        $perPage = TablePagination::resolvePerPage($request);

        $query = BranchProduct::query()
            ->with([
                'branch:id,name,slug',
                'product:id,name,image,description,category_id,cost,sale_price,margin_percentage,unit,inventory_unit,pieces_per_box,has_box_presentation,inventory_quantity_mode,cost_per_piece,sale_price_per_piece,cost_per_box,sale_price_per_box,active,created_at,updated_at',
                'product.category:id,product_department_id,name',
                'product.category.productDepartment:id,name',
                'product.barcodes:id,product_id,code',
            ])
            ->where('branch_id', $branch->id)
            ->where('status', BranchProduct::STATUS_ACTIVE)
            ->orderByDesc('id');

        $categoryIds = $this->selectedIntegerIds($request->input('category_id'));
        $productDepartmentIds = $this->selectedIntegerIds($request->input('product_department_id'));

        if ($categoryIds !== []) {
            $query->whereHas('product', function ($productQuery) use ($categoryIds) {
                $productQuery->whereIn('category_id', $categoryIds);
            });
        }

        if ($productDepartmentIds !== []) {
            $query->whereHas('product.category', function ($categoryQuery) use ($productDepartmentIds) {
                $categoryQuery->whereIn('product_department_id', $productDepartmentIds);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;

            FlexibleSearch::apply($query, $search, function ($searchQuery, $phrase, $terms) {
                FlexibleSearch::orWhereColumns($searchQuery, [
                    'branch_products.barcode',
                ], $phrase, $terms);

                FlexibleSearch::orWhereHasColumns($searchQuery, 'product', [
                    'name',
                ], $phrase, $terms);

                FlexibleSearch::orWhereHasColumns($searchQuery, 'product.barcodes', [
                    'code',
                ], $phrase, $terms);

                FlexibleSearch::orWhereHasColumns($searchQuery, 'product.category.productDepartment', [
                    'name',
                ], $phrase, $terms);
            });
        }

        $productsDB = $query->paginate($perPage)->withQueryString();
        $productIds = $productsDB->getCollection()
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values();

        $branchIdsByProduct = BranchProduct::query()
            ->whereIn('product_id', $productIds)
            ->where('status', BranchProduct::STATUS_ACTIVE)
            ->get(['product_id', 'branch_id'])
            ->groupBy('product_id')
            ->map(fn ($items) => $items
                ->pluck('branch_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all());

        $productsDB->getCollection()->transform(
            fn ($item) => $this->serializeProductRow(
                $item,
                $branchIdsByProduct->get($item->product_id, []),
            )
        );

        return Inertia::render('Inventory/Home', [
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'slug' => $branch->slug,
            ],
            'productsDB' => $productsDB,
            'productDepartmentsDB' => $this->productDepartmentOptions(),
            'categoriesDB' => $this->categoryOptions(),
            'branchesDB' => Branch::select('id', 'name', 'slug')
                ->where('active', true)
                ->orderBy('name')
                ->get(),
            'filters' => [
                'search' => $request->search,
                'product_department_id' => $productDepartmentIds,
                'category_id' => $categoryIds,
                'per_page' => $perPage,
            ],
            'canManagePricing' => $this->canManagePricing($request),
        ]);
    }

    public function snapshot(Request $request, Branch $branch, int $productId): JsonResponse
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        $branchProduct = BranchProduct::query()
            ->with([
                'branch:id,name,slug',
                'product:id,name,image,description,category_id,cost,sale_price,margin_percentage,unit,inventory_unit,pieces_per_box,has_box_presentation,inventory_quantity_mode,cost_per_piece,sale_price_per_piece,cost_per_box,sale_price_per_box,active,created_at',
                'product.category:id,product_department_id,name',
                'product.category.productDepartment:id,name',
                'product.barcodes:id,product_id,code',
            ])
            ->where('branch_id', $branch->id)
            ->where('product_id', $productId)
            ->where('status', BranchProduct::STATUS_ACTIVE)
            ->first();

        if (!$branchProduct) {
            return response()->json([
                'product' => null,
            ]);
        }

        $branchIds = BranchProduct::query()
            ->where('product_id', $productId)
            ->where('status', BranchProduct::STATUS_ACTIVE)
            ->pluck('branch_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return response()->json([
            'product' => $this->serializeProductRow($branchProduct, $branchIds),
        ]);
    }

    public function image(Product $product)
    {
        if (!$product->image) {
            abort(404);
        }

        $disk = $this->resolveImageDisk($product->image);

        if (!$disk) {
            abort(404);
        }

        return Storage::disk($disk)->response($product->image);
    }

    public function store(Request $request, Branch $branch)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);
        $canManagePricing = $this->canManagePricing($request);
        $this->normalizeProductPayload($request);

        $data = $request->validate([
            'barcodes' => ['nullable', 'array'],
            'barcodes.*' => ['nullable', 'string', 'max:100', 'distinct'],
            'inventory_unit' => ['required', Rule::in(['pza', 'kg'])],
            'has_box_presentation' => ['nullable', 'boolean'],
            'pieces_per_box' => [Rule::requiredIf(fn () => (bool) $request->boolean('has_box_presentation')), 'nullable', 'integer', 'min:2', 'max:999'],
            'name' => ['required', 'string', 'max:255', 'regex:/^[\pL\pN\s.,\/_%&+()#°-]+$/u'],
            'image' => ['nullable', 'image', 'max:2048'],

            'min_stock' => $this->minimumStockRules((string) $request->input('inventory_unit')),
            'product_department_id' => ['nullable', 'required_with:category_name', 'exists:product_departments,id'],
            'category_id' => ['nullable', 'required_without:category_name', 'exists:categories,id'],
            'category_name' => ['nullable', 'required_without:category_id', 'string', 'max:255'],
            'cost_per_piece' => ['required', 'numeric', 'min:0'],
            'sale_price_per_piece' => $canManagePricing
                ? ['required', 'numeric', 'min:0']
                : ['nullable', 'numeric', 'min:0'],
            'cost_per_box' => [Rule::requiredIf(fn () => (bool) $request->boolean('has_box_presentation')), 'nullable', 'numeric', 'min:0'],
            'sale_price_per_box' => $canManagePricing
                ? [Rule::requiredIf(fn () => (bool) $request->boolean('has_box_presentation')), 'nullable', 'numeric', 'min:0']
                : ['nullable', 'numeric', 'min:0'],
            'allow_low_margin' => ['nullable', 'boolean'],
            'entry_date' => ['required', 'date'],
            'active' => ['boolean'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['exists:branches,id'],
        ]);

        $data['has_box_presentation'] = $request->boolean('has_box_presentation');

        $this->resolvePresentationPricing($data, $canManagePricing);

        if ($data['inventory_unit'] === 'kg' && $data['has_box_presentation']) {
            throw ValidationException::withMessages([
                'has_box_presentation' => 'La presentación por caja solo aplica a productos inventariados por pieza.',
            ]);
        }

        $data['category_id'] = $this->resolveCategoryId($data);

        $barcodes = collect($data['barcodes'] ?? [])
            ->filter(fn ($code) => filled($code))
            ->values();
        $reusableDeletedProduct = $this->resolveReusableDeletedProduct($barcodes);

        $requestedBranchIds = collect($data['branch_ids'] ?? [])
            ->push($branch->id)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $this->abortIfAnyBranchIsInaccessible($request, $requestedBranchIds);

        $imagePath = null;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', self::PRODUCT_IMAGE_PRIVATE_DISK);
        }

        $previousImagePath = $reusableDeletedProduct?->image;

        try {
            [$product, $branchIds, $activityAction] = DB::transaction(function () use ($data, $imagePath, $barcodes, $requestedBranchIds, $reusableDeletedProduct) {
                $product = $reusableDeletedProduct
                    ? Product::withTrashed()->lockForUpdate()->findOrFail($reusableDeletedProduct->id)
                    : new Product();
                $activityAction = $product->exists ? 'restored' : 'created';

                if ($product->trashed()) {
                    $product->restore();
                }

                $product->fill([
                    'name' => $data['name'],
                    'description' => null,
                    'image' => $imagePath ?? ($product->exists ? $product->image : null),
                    'cost' => $data['cost'],
                    'sale_price' => $data['sale_price'],
                    'margin_percentage' => $data['margin_percentage'],
                    'unit' => $data['inventory_unit'],
                    'inventory_unit' => $data['inventory_unit'],
                    'has_box_presentation' => (bool) $data['has_box_presentation'],
                    'inventory_quantity_mode' => 'base',
                    'pieces_per_box' => $data['has_box_presentation'] ? $data['pieces_per_box'] : null,
                    'cost_per_piece' => $data['cost_per_piece'],
                    'sale_price_per_piece' => $data['sale_price_per_piece'],
                    'cost_per_box' => $data['has_box_presentation'] ? $data['cost_per_box'] : null,
                    'sale_price_per_box' => $data['has_box_presentation'] ? $data['sale_price_per_box'] : null,
                    'category_id' => $data['category_id'],
                    'active' => $data['active'] ?? true,
                ]);
                $product->save();

                $this->syncProductBarcodes($product, $barcodes);

                foreach ($requestedBranchIds as $branchId) {
                    $this->activateProductForBranch($product, $branchId, [
                        ...$data,
                        'stock' => $data['stock'] ?? 0,
                    ]);
                }

                return [$product, $requestedBranchIds->all(), $activityAction];
            });
        } catch (Throwable $exception) {
            $this->deleteProductImage($imagePath);
            throw $exception;
        }

        if ($imagePath && $previousImagePath && $imagePath !== $previousImagePath) {
            $this->deleteProductImage($previousImagePath);
        }

        broadcast(new ProductChanged($activityAction, $product->id, $branchIds))->toOthers();
        event(RealtimeActivityLogged::message(
            $activityAction === 'restored' ? 'reactivó' : 'creó',
            'el producto',
            $product->name,
            'Inventario',
            $activityAction,
        ));

        return back()->with('success', 'Producto creado correctamente');
    }

    public function update(Request $request, Branch $branch, Product $product)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);
        $canManagePricing = $this->canManagePricing($request);
        $this->normalizeProductPayload($request);

        $data = $request->validate([
            'barcodes' => ['nullable', 'array'],
            'barcodes.*' => ['nullable', 'string', 'max:100', 'distinct'],
            'inventory_unit' => ['required', Rule::in(['pza', 'kg'])],
            'has_box_presentation' => ['nullable', 'boolean'],
            'pieces_per_box' => [Rule::requiredIf(fn () => (bool) $request->boolean('has_box_presentation')), 'nullable', 'integer', 'min:2', 'max:999'],
            'name' => ['required', 'string', 'max:255', 'regex:/^[\pL\pN\s.,\/_%&+()#°-]+$/u'],
            'image' => ['nullable', 'image', 'max:2048'],
            'min_stock' => $this->minimumStockRules((string) $request->input('inventory_unit')),
            'product_department_id' => ['nullable', 'required_with:category_name', 'exists:product_departments,id'],
            'category_id' => ['nullable', 'required_without:category_name', 'exists:categories,id'],
            'category_name' => ['nullable', 'required_without:category_id', 'string', 'max:255'],
            'cost_per_piece' => ['required', 'numeric', 'min:0'],
            'sale_price_per_piece' => $canManagePricing
                ? ['required', 'numeric', 'min:0']
                : ['nullable', 'numeric', 'min:0'],
            'cost_per_box' => [Rule::requiredIf(fn () => (bool) $request->boolean('has_box_presentation')), 'nullable', 'numeric', 'min:0'],
            'sale_price_per_box' => $canManagePricing
                ? [Rule::requiredIf(fn () => (bool) $request->boolean('has_box_presentation')), 'nullable', 'numeric', 'min:0']
                : ['nullable', 'numeric', 'min:0'],
            'allow_low_margin' => ['nullable', 'boolean'],
            'entry_date' => ['required', 'date'],
            'active' => ['boolean'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['exists:branches,id'],
        ]);

        $data['has_box_presentation'] = $request->boolean('has_box_presentation');

        $this->resolvePresentationPricing($data, $canManagePricing);

        if ($data['inventory_unit'] === 'kg' && $data['has_box_presentation']) {
            throw ValidationException::withMessages([
                'has_box_presentation' => 'La presentación por caja solo aplica a productos inventariados por pieza.',
            ]);
        }

        $data['category_id'] = $this->resolveCategoryId($data);

        $barcodes = collect($data['barcodes'] ?? [])
            ->filter(fn ($code) => filled($code))
            ->values();
        $this->assertBarcodesAreAvailableForProduct($barcodes, $product->id);

        $branchIds = collect($data['branch_ids'] ?? [])
            ->push($branch->id)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $this->abortIfAnyBranchIsInaccessible($request, $branchIds);

        $previousBranchIds = BranchProduct::where('product_id', $product->id)
            ->pluck('branch_id')
            ->map(fn ($id) => (int) $id)
            ->values();
        $preservedBranchIds = $previousBranchIds
            ->reject(fn (int $branchId) => $request->user()->hasBranchAccess($branchId));
        $branchIds = $branchIds->merge($preservedBranchIds)->unique()->values();

        $currentBarcodes = $product->barcodes()->orderBy('id')->pluck('code')->values();
        $isLegacyPresentationProduct = $product->inventory_quantity_mode === 'legacy_presentation';
        $storageUnit = $isLegacyPresentationProduct
            ? $product->unit
            : $data['inventory_unit'];

        if ($isLegacyPresentationProduct) {
            // The historical stock is still expressed as boxes. Its legacy
            // price fields remain unchanged until the inventory migration.
            $data['cost'] = $product->cost;
            $data['sale_price'] = $product->sale_price;
            $data['margin_percentage'] = $product->margin_percentage;
        }

        $changesGlobalProduct = $request->hasFile('image')
            || (string) $product->name !== (string) $data['name']
            || (int) $product->category_id !== (int) $data['category_id']
            || (float) $product->cost_per_piece !== (float) $data['cost_per_piece']
            || (float) $product->sale_price_per_piece !== (float) $data['sale_price_per_piece']
            || (float) $product->cost_per_box !== (float) ($data['has_box_presentation'] ? $data['cost_per_box'] : 0)
            || (float) $product->sale_price_per_box !== (float) ($data['has_box_presentation'] ? $data['sale_price_per_box'] : 0)
            || (string) $product->inventory_unit !== (string) $data['inventory_unit']
            || (bool) $product->has_box_presentation !== (bool) $data['has_box_presentation']
            || (int) $product->pieces_per_box !== (int) ($data['has_box_presentation'] ? $data['pieces_per_box'] : 0)
            || (bool) $product->active !== (bool) ($data['active'] ?? true)
            || $currentBarcodes->all() !== $barcodes->all();

        if ($changesGlobalProduct && $preservedBranchIds->isNotEmpty()) {
            return back()->withErrors([
                'product' => 'No puedes modificar los datos globales de un producto asignado a sucursales sin acceso.',
            ]);
        }

        $blockedRetirements = BranchProduct::query()
            ->with('branch:id,name')
            ->where('product_id', $product->id)
            ->whereNotIn('branch_id', $branchIds->all())
            ->get()
            ->filter(fn (BranchProduct $branchProduct) => $this->hasProtectedInventory($branchProduct));

        if ($blockedRetirements->isNotEmpty()) {
            return back()->withErrors([
                'branch_ids' => $this->protectedInventoryMessage($blockedRetirements),
            ]);
        }

        $previousImagePath = $product->image;
        $newImagePath = $request->hasFile('image')
            ? $request->file('image')->store('products', self::PRODUCT_IMAGE_PRIVATE_DISK)
            : $previousImagePath;

        try {
            $branchIds = DB::transaction(function () use ($request, $data, $product, $barcodes, $branchIds, $newImagePath, $storageUnit, $isLegacyPresentationProduct) {

            $product = $this->lockCurrentVersion($request, $product);

            $product->update([
                'name' => $data['name'],
                'image' => $newImagePath,
                'category_id' => $data['category_id'],
                'cost' => $data['cost'],
                'sale_price' => $data['sale_price'],
                'margin_percentage' => $data['margin_percentage'],
                'unit' => $storageUnit,
                'inventory_unit' => $data['inventory_unit'],
                'has_box_presentation' => (bool) $data['has_box_presentation'],
                'inventory_quantity_mode' => $isLegacyPresentationProduct ? 'legacy_presentation' : 'base',
                'pieces_per_box' => $data['has_box_presentation'] ? $data['pieces_per_box'] : null,
                'cost_per_piece' => $data['cost_per_piece'],
                'sale_price_per_piece' => $data['sale_price_per_piece'],
                'cost_per_box' => $data['has_box_presentation'] ? $data['cost_per_box'] : null,
                'sale_price_per_box' => $data['has_box_presentation'] ? $data['sale_price_per_box'] : null,
                'active' => $data['active'] ?? true,
            ]);

            $this->syncProductBarcodes($product, $barcodes);

            foreach ($branchIds as $branchId) {
                $this->activateProductForBranch($product, $branchId, $data);
            }

            BranchProduct::where('product_id', $product->id)
                ->whereNotIn('branch_id', $branchIds->all())
                ->update([
                    'status' => BranchProduct::STATUS_INACTIVE,
                ]);

                return $branchIds->all();
            });
        } catch (Throwable $exception) {
            if ($newImagePath !== $previousImagePath) {
                $this->deleteProductImage($newImagePath);
            }
            throw $exception;
        }

        if ($newImagePath !== $previousImagePath) {
            $this->deleteProductImage($previousImagePath);
        }

        $affectedBranchIds = $previousBranchIds
            ->merge($branchIds)
            ->unique()
            ->values()
            ->all();

        broadcast(new ProductChanged('updated', $product->id, $affectedBranchIds))->toOthers();
        event(RealtimeActivityLogged::message('actualizó', 'el producto', $product->name, 'Inventario', 'updated'));

        return back()->with('success', 'Producto actualizado correctamente');
    }

    public function destroy(Request $request, Branch $branch, Product $product)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        $data = $request->validate([
            'delete_globally' => ['nullable', 'boolean'],
        ]);

        $deleteGlobally = (bool) ($data['delete_globally'] ?? false);

        if ($deleteGlobally && ! $request->user()?->hasPermission(SystemPermission::BRANCHES_ACCESS_ALL)) {
            abort(403, 'Solo un usuario con acceso global a sucursales puede eliminar el producto globalmente.');
        }
        $branchProduct = BranchProduct::query()
            ->where('branch_id', $branch->id)
            ->where('product_id', $product->id)
            ->firstOrFail();

        $productId = $product->id;
        $productName = $product->name;

        if (! $deleteGlobally) {
            if ($this->hasProtectedInventory($branchProduct)) {
                return back()->withErrors([
                    'product' => 'No se puede retirar este producto de la sucursal porque todavia tiene stock o lotes vigentes.',
                ]);
            }

            DB::transaction(function () use ($request, $product, $branchProduct) {
                $this->lockCurrentVersion($request, $product);
                $lockedBranchProduct = BranchProduct::query()->whereKey($branchProduct->id)->lockForUpdate()->firstOrFail();

                if ($this->hasProtectedInventory($lockedBranchProduct)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'product' => 'No se puede retirar este producto porque el inventario cambio mientras confirmabas la operacion.',
                    ]);
                }

                $lockedBranchProduct->delete();
            });

            broadcast(new ProductChanged('deleted', $productId, [$branch->id]))->toOthers();
            event(RealtimeActivityLogged::message(
                'retiro',
                'el producto de la sucursal',
                "{$productName} ({$branch->name})",
                'Inventario',
                'deleted',
            ));

            return back()->with('success', "Producto retirado de {$branch->name} correctamente");
        }

        $branchProducts = BranchProduct::query()
            ->with('branch:id,name')
            ->where('product_id', $product->id)
            ->get();

        $blockedRetirements = $branchProducts
            ->filter(fn (BranchProduct $branchProduct) => $this->hasProtectedInventory($branchProduct));

        if ($blockedRetirements->isNotEmpty()) {
            return back()->withErrors([
                'product' => $this->protectedInventoryMessage($blockedRetirements),
            ]);
        }

        $branchIds = $branchProducts
            ->pluck('branch_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        DB::transaction(function () use ($request, $product) {
            $product = $this->lockCurrentVersion($request, $product);
            // La eliminacion global retira el producto maestro y sus asignaciones.
            $product->branchProducts()->delete();
            $product->barcodes()->delete();
            $product->delete();
        });

        broadcast(new ProductChanged('deleted', $productId, $branchIds))->toOthers();
        event(RealtimeActivityLogged::message('eliminó', 'el producto', $productName, 'Inventario', 'deleted'));

        return back()->with('success', 'Producto eliminado de todas las sucursales correctamente');
    }

    private function activateProductForBranch(Product $product, int $branchId, array $data): BranchProduct
    {
        $branchProduct = BranchProduct::withTrashed()->firstOrNew([
            'branch_id' => $branchId,
            'product_id' => $product->id,
        ]);
        $isNewAssignment = ! $branchProduct->exists;

        if ($branchProduct->exists && $branchProduct->trashed()) {
            $branchProduct->restore();
        }

        if ($isNewAssignment) {
            $branchProduct->stock = $data['stock'] ?? 0;
            $branchProduct->tracks_batches = false;
            $branchProduct->tracks_expiration = false;
            $branchProduct->entry_date = $data['entry_date'] ?? now()->toDateString();
        }

        $branchProduct->min_stock = $data['min_stock'] ?? 0;
        $branchProduct->status = BranchProduct::STATUS_ACTIVE;
        $branchProduct->save();

        return $branchProduct;
    }

    private function resolveReusableDeletedProduct($barcodes): ?Product
    {
        if ($barcodes->isEmpty()) {
            return null;
        }

        $matchingBarcodes = Barcode::withTrashed()
            ->with(['product' => fn ($query) => $query->withTrashed()])
            ->whereIn('code', $barcodes->all())
            ->get();

        $activeBarcode = $matchingBarcodes->first(function (Barcode $barcode) {
            return ! $barcode->trashed() && ! $barcode->product?->trashed();
        });

        if ($activeBarcode) {
            throw ValidationException::withMessages([
                $this->barcodeErrorField($barcodes, $activeBarcode->code) => $this->barcodeAlreadyInUseMessage($activeBarcode),
            ]);
        }

        $blockedBarcode = $matchingBarcodes->first(function (Barcode $barcode) {
            return ! $barcode->product?->trashed();
        });

        if ($blockedBarcode) {
            throw ValidationException::withMessages([
                $this->barcodeErrorField($barcodes, $blockedBarcode->code) => 'Este código de barras pertenece a un producto activo. Reactívalo desde la edición del producto.',
            ]);
        }

        $deletedProductIds = $matchingBarcodes
            ->filter(fn (Barcode $barcode) => $barcode->product?->trashed())
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values();

        if ($deletedProductIds->count() > 1) {
            throw ValidationException::withMessages([
                'barcodes.0' => 'Estos códigos pertenecen a productos eliminados diferentes. Registra solo los códigos del mismo producto.',
            ]);
        }

        if ($deletedProductIds->isEmpty()) {
            return null;
        }

        return Product::withTrashed()->find($deletedProductIds->first());
    }

    private function assertBarcodesAreAvailableForProduct($barcodes, int $productId): void
    {
        if ($barcodes->isEmpty()) {
            return;
        }

        $conflictingBarcode = Barcode::withTrashed()
            ->with(['product' => fn ($query) => $query->withTrashed()])
            ->whereIn('code', $barcodes->all())
            ->where('product_id', '!=', $productId)
            ->get()
            ->first(fn (Barcode $barcode) => ! $barcode->trashed() || ! $barcode->product?->trashed());

        if (! $conflictingBarcode) {
            return;
        }

        throw ValidationException::withMessages([
            $this->barcodeErrorField($barcodes, $conflictingBarcode->code) => $this->barcodeAlreadyInUseMessage($conflictingBarcode),
        ]);
    }

    private function syncProductBarcodes(Product $product, $barcodes): void
    {
        $codes = $barcodes->values();
        $existingBarcodes = Barcode::withTrashed()
            ->where('product_id', $product->id)
            ->get()
            ->keyBy('code');

        $staleBarcodeQuery = Barcode::withTrashed()
            ->where('product_id', $product->id)
            ->when($codes->isNotEmpty(), fn ($query) => $query->whereNotIn('code', $codes->all()));

        $staleBarcodeQuery->get()
            ->each
            ->forceDelete();

        foreach ($codes as $index => $code) {
            $barcode = $existingBarcodes->get($code) ?? new Barcode([
                'product_id' => $product->id,
                'code' => $code,
            ]);

            if ($barcode->exists && $barcode->trashed()) {
                $barcode->restore();
            }

            $barcode->fill([
                'product_id' => $product->id,
                'code' => $code,
                'type' => $index === 0 ? 'PRINCIPAL' : 'ALTERNO',
                'base_quantity' => 1,
                'active' => true,
            ]);
            $barcode->save();
        }
    }

    private function barcodeErrorField($barcodes, string $code): string
    {
        $index = $barcodes->search(fn ($barcode) => (string) $barcode === (string) $code);

        return 'barcodes.'.($index === false ? 0 : $index);
    }

    private function barcodeAlreadyInUseMessage(Barcode $barcode): string
    {
        $productName = $barcode->product?->name;

        return $productName
            ? "Este código de barras ya pertenece a {$productName}."
            : 'Este código de barras ya pertenece a otro producto.';
    }

    private function normalizeProductPayload(Request $request): void
    {
        $normalized = [];

        if (! $request->filled('inventory_unit') && $request->filled('unit')) {
            $normalized['inventory_unit'] = match (mb_strtolower((string) $request->input('unit'))) {
                'kg', 'kilo', 'kilos', 'kilogramo', 'kilogramos' => 'kg',
                default => 'pza',
            };
        }

        if (! $request->filled('cost_per_piece') && $request->filled('cost')) {
            $normalized['cost_per_piece'] = $request->input('cost');
        }

        if (! $request->filled('sale_price_per_piece') && $request->filled('sale_price')) {
            $normalized['sale_price_per_piece'] = $request->input('sale_price');
        }

        if ($normalized !== []) {
            $request->merge($normalized);
        }
    }

    private function abortIfAnyBranchIsInaccessible(Request $request, $branchIds): void
    {
        $hasInaccessibleBranch = collect($branchIds)
            ->contains(fn ($branchId) => ! $request->user()?->hasBranchAccess((int) $branchId));

        abort_if($hasInaccessibleBranch, 403, 'No tienes acceso a una de las sucursales seleccionadas.');
    }

    private function hasProtectedInventory(BranchProduct $branchProduct): bool
    {
        if ((float) $branchProduct->stock > 0) {
            return true;
        }

        return $branchProduct->batches()
            ->where(function ($query) {
                $query
                    ->where('quantity', '>', 0)
                    ->orWhere('status', ProductBatch::STATUS_ACTIVE);
            })
            ->exists();
    }

    private function protectedInventoryMessage($branchProducts): string
    {
        $branchNames = $branchProducts
            ->map(fn (BranchProduct $branchProduct) => $branchProduct->branch?->name)
            ->filter()
            ->unique()
            ->values()
            ->implode(', ');

        $suffix = $branchNames ? " Sucursales afectadas: {$branchNames}." : '';

        return "No se puede retirar el producto porque todavia tiene stock o lotes vigentes.{$suffix}";
    }

    private function resolveImageDisk(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        foreach ([self::PRODUCT_IMAGE_PRIVATE_DISK, self::PRODUCT_IMAGE_LEGACY_DISK] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return $disk;
            }
        }

        return null;
    }

    private function deleteProductImage(?string $path): void
    {
        $disk = $this->resolveImageDisk($path);

        if ($disk) {
            Storage::disk($disk)->delete($path);
        }
    }

    private function minimumStockRules(string $unit): array
    {
        if ($unit === 'kg') {
            return ['nullable', 'numeric', 'decimal:0,3', 'min:0', 'max:999.999'];
        }

        return ['nullable', 'integer', 'min:0', 'max:999'];
    }

    private function productDepartmentOptions()
    {
        return ProductDepartment::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'icon']);
    }

    private function categoryOptions()
    {
        return Category::query()
            ->with('productDepartment:id,name')
            ->where('active', true)
            ->orderBy('product_department_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'product_department_id', 'name', 'sort_order'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'product_department_id' => $category->product_department_id,
                'name' => $category->name,
                'department_name' => $category->productDepartment?->name,
            ])
            ->values();
    }

    private function selectedIntegerIds($value): array
    {
        return collect(is_array($value) ? $value : (filled($value) ? [$value] : []))
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function resolveCategoryId(array $data): int
    {
        if (filled($data['category_id'] ?? null)) {
            $category = Category::query()->findOrFail($data['category_id']);

            if (
                filled($data['product_department_id'] ?? null)
                && (int) $category->product_department_id !== (int) $data['product_department_id']
            ) {
                throw ValidationException::withMessages([
                    'category_id' => 'La categoria seleccionada no pertenece al departamento elegido.',
                ]);
            }

            return (int) $category->id;
        }

        $departmentId = (int) ($data['product_department_id'] ?? 0);

        if ($departmentId <= 0) {
            throw ValidationException::withMessages([
                'product_department_id' => 'Selecciona el departamento antes de crear una categoria.',
            ]);
        }

        $categoryName = trim((string) ($data['category_name'] ?? ''));

        $category = Category::firstOrCreate(
            [
                'product_department_id' => $departmentId,
                'name' => $categoryName,
            ],
            [
                'active' => true,
            ],
        );

        return (int) $category->id;
    }

    private function serializeProductRow(BranchProduct $branchProduct, array $branchIds = []): array
    {
        $product = $branchProduct->product;
        $imageUrl = $product?->image
            ? route('inventory.products.image', ['product' => $product->id])
            : null;

        return [
            'id' => $product?->id,
            'branch_product_id' => $branchProduct->id,
            'branch_id' => $branchProduct->branch_id,
            'branch_slug' => $branchProduct->branch?->slug,
            'branch_ids' => collect($branchIds)->map(fn ($id) => (int) $id)->values()->all(),
            'barcodes' => $product?->barcodes?->pluck('code')->values() ?? [],
            'barcode' => $product?->barcodes?->first()?->code ?? 'Sin código',
            'unit' => $product?->unit ?? '',
            'inventory_unit' => $product?->inventory_unit ?? ($product?->unit === 'kg' ? 'kg' : 'pza'),
            'pieces_per_box' => $product?->pieces_per_box,
            'has_box_presentation' => (bool) ($product?->has_box_presentation ?? $product?->unit === 'cj'),
            'inventory_quantity_mode' => $product?->inventory_quantity_mode ?? 'base',
            'name' => $product?->name ?? 'Producto sin nombre',
            'image' => $imageUrl,
            'image_path' => $product?->image,
            'product_department_id' => $product?->category?->product_department_id,
            'product_department_name' => $product?->category?->productDepartment?->name ?? 'Sin departamento',
            'category_id' => $product?->category_id,
            'category_name' => $product?->category?->name ?? 'Sin categoría',
            'category' => $product?->category?->name ?? 'Sin categoría',
            'min_stock' => $branchProduct->min_stock ?? 0,
            'cost' => $product?->cost ?? 0,
            'cost_per_piece' => $product?->cost_per_piece ?? $product?->cost ?? 0,
            'cost_per_box' => $product?->cost_per_box,
            'price' => $product?->sale_price ?? 0,
            'sale_price' => $product?->sale_price ?? 0,
            'salePrice' => $product?->sale_price ?? 0,
            'sale_price_per_piece' => $product?->sale_price_per_piece ?? $product?->sale_price ?? 0,
            'sale_price_per_box' => $product?->sale_price_per_box,
            'margin_percentage' => $product?->margin_percentage
                ?? $this->calculateMarginPercentage($product?->cost ?? 0, $product?->sale_price ?? 0),
            'profit' => number_format(
                ((float) ($product?->sale_price ?? 0)) - ((float) ($product?->cost ?? 0)),
                2
            ),
            'active' => $product?->active ?? true,
            'record_version' => $product?->updated_at?->toJSON(),
            'status' => $branchProduct->status,
            'tracks_batches' => $branchProduct->tracks_batches,
            'tracks_expiration' => $branchProduct->tracks_expiration,
            'entry_date' => $branchProduct->entry_date
                ?? optional($branchProduct->created_at)->format('Y-m-d')
                ?? 'Sin fecha',
        ];
    }
}
