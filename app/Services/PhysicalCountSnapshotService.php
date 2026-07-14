<?php

namespace App\Services;

use App\Models\BranchProduct;
use App\Models\PhysicalCount;
use App\Models\PhysicalCountSnapshot;
use App\Models\ProductBatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PhysicalCountSnapshotService
{
    public function ensureForAudit(PhysicalCount $physicalCount): PhysicalCountSnapshot
    {
        if ($physicalCount->relationLoaded('snapshot') && $physicalCount->snapshot) {
            return $physicalCount->snapshot;
        }

        $snapshot = PhysicalCountSnapshot::firstWhere('physical_count_id', $physicalCount->id);

        if ($snapshot) {
            $physicalCount->setRelation('snapshot', $snapshot->loadMissing('items'));

            return $physicalCount->snapshot;
        }

        $snapshot = DB::transaction(function () use ($physicalCount) {
            $existing = PhysicalCountSnapshot::lockForUpdate()
                ->firstWhere('physical_count_id', $physicalCount->id);

            if ($existing) {
                return $existing;
            }

            $snapshot = PhysicalCountSnapshot::create([
                'physical_count_id' => $physicalCount->id,
                'branch_id' => $physicalCount->branch_id,
                'created_by' => Auth::id() ?? $physicalCount->created_by,
                'captured_at' => $physicalCount->started_at ?? now(),
            ]);

            $this->createSnapshotItems($snapshot, $physicalCount);

            return $snapshot;
        });

        $physicalCount->setRelation('snapshot', $snapshot->load('items'));

        return $physicalCount->snapshot;
    }

    public function refreshForAudit(PhysicalCount $physicalCount, string $scope = 'all'): PhysicalCountSnapshot
    {
        return DB::transaction(function () use ($physicalCount, $scope) {
            $snapshot = PhysicalCountSnapshot::updateOrCreate(
                ['physical_count_id' => $physicalCount->id],
                [
                    'branch_id' => $physicalCount->branch_id,
                    'created_by' => Auth::id() ?? $physicalCount->created_by,
                    'captured_at' => now(),
                ]
            );

            $snapshot->items()->delete();
            $this->createSnapshotItems($snapshot, $physicalCount, $scope);

            $physicalCount->setRelation('snapshot', $snapshot->load('items'));

            return $physicalCount->snapshot;
        });
    }

    public function buildProductRows(Collection $audits): Collection
    {
        return $audits
            ->filter(fn ($audit) => $audit->snapshot !== null)
            ->flatMap(function ($audit) {
                return $audit->snapshot->items
                    ->groupBy('branch_product_id')
                    ->map(function ($items, $branchProductId) use ($audit) {
                        $first = $items->first();

                        return [
                            'id' => $audit->id.'-'.$branchProductId,
                            'physical_count_id' => $audit->id,
                            'audit_name' => $audit->name ?? 'Sin auditoria',
                            'folio' => $audit->folio ?? 'Sin folio',
                            'audit_date' => optional($audit->started_at)->toDateString(),
                            'branch_name' => $audit->branch?->name ?? 'Sin sucursal',
                            'branch_product_id' => (int) $branchProductId,
                            'product_id' => $first->product_id,
                            'category_id' => $first->category_id,
                            'subcategory_id' => $first->subcategory_id,
                            'product_name' => $first->product_name ?: 'Sin producto',
                            'category_name' => $first->category_name ?: 'Sin categoria',
                            'subcategory_name' => $first->subcategory_name ?: 'Sin subcategoria',
                            'scanned_code' => $first->barcode ?: '-',
                            'system_stock' => (float) $items->max('system_stock'),
                            'batch_stock' => (float) $items->sum('batch_stock'),
                            'snapshot_batches' => $items
                                ->filter(fn ($item) => $item->product_batch_id !== null)
                                ->map(fn ($item) => [
                                    'id' => $item->product_batch_id,
                                    'lot_number' => $item->lot_number,
                                    'expiration_date' => optional($item->expiration_date)->toDateString(),
                                    'quantity' => (float) $item->batch_stock,
                                ])
                                ->values()
                                ->all(),
                        ];
                    })
                    ->values();
            })
            ->values();
    }

    private function createSnapshotItems(
        PhysicalCountSnapshot $snapshot,
        PhysicalCount $physicalCount,
        string $scope = 'all'
    ): void {
        $branchProducts = BranchProduct::with([
            'product.category:id,name',
            'product.subcategory:id,name,category_id',
            'batches' => fn ($query) => $query->orderBy('expiration_date')->orderBy('id'),
        ])
            ->where('branch_id', $physicalCount->branch_id)
            ->whereIn('status', [
                BranchProduct::STATUS_ACTIVE,
                BranchProduct::STATUS_SEASONAL,
            ])
            ->when($scope === 'zero_stock', fn ($query) => $query->where('stock', '<=', 0))
            ->orderBy('id')
            ->get();

        $items = [];

        foreach ($branchProducts as $branchProduct) {
            $product = $branchProduct->product;
            $systemStock = (float) $branchProduct->stock;
            $batches = $branchProduct->batches;

            if ($batches->isEmpty()) {
                $items[] = $this->makeSnapshotItemPayload(
                    $snapshot->id,
                    $branchProduct,
                    null,
                    $product?->name ?? 'Sin producto',
                    $product?->category?->name,
                    $product?->subcategory?->name,
                    $product?->category_id,
                    $product?->subcategory_id,
                    $systemStock,
                    $systemStock
                );

                continue;
            }

            foreach ($batches as $batch) {
                $items[] = $this->makeSnapshotItemPayload(
                    $snapshot->id,
                    $branchProduct,
                    $batch,
                    $product?->name ?? 'Sin producto',
                    $product?->category?->name,
                    $product?->subcategory?->name,
                    $product?->category_id,
                    $product?->subcategory_id,
                    $systemStock,
                    (float) $batch->quantity
                );
            }
        }

        if ($items !== []) {
            $snapshot->items()->createMany($items);
        }
    }

    private function makeSnapshotItemPayload(
        int $snapshotId,
        BranchProduct $branchProduct,
        ?ProductBatch $batch,
        string $productName,
        ?string $categoryName,
        ?string $subcategoryName,
        $categoryId,
        $subcategoryId,
        float $systemStock,
        float $batchStock
    ): array {
        return [
            'physical_count_snapshot_id' => $snapshotId,
            'branch_product_id' => $branchProduct->id,
            'product_id' => $branchProduct->product_id,
            'category_id' => $categoryId,
            'subcategory_id' => $subcategoryId,
            'product_batch_id' => $batch?->id,
            'barcode' => $branchProduct->barcode,
            'product_name' => $productName,
            'category_name' => $categoryName,
            'subcategory_name' => $subcategoryName,
            'lot_number' => $batch?->lot_number,
            'expiration_date' => $batch?->expiration_date,
            'branch_product_status' => $branchProduct->status,
            'batch_status' => $batch?->status,
            'system_stock' => $systemStock,
            'batch_stock' => $batchStock,
        ];
    }
}
