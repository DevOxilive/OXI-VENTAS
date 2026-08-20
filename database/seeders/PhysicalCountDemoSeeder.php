<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\PhysicalCount;
use App\Models\PhysicalCountEntry;
use App\Models\PhysicalCountRound;
use App\Models\PhysicalCountSnapshot;
use App\Models\PhysicalCountSnapshotItem;
use App\Models\ProductBatch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PhysicalCountDemoSeeder extends Seeder
{
    private const FOLIO_PREFIX = 'SEED-AUD-';

    private const PRODUCTS_PER_AUDIT = 48;

    public function run(): void
    {
        // Seeder intencionalmente vacío para pruebas limpias en servidor.
        return;

        DB::transaction(function () {
            $this->clearPreviousSeedData();

            $user = User::query()->where('email', 'ana.lilia@oxilive.com.mx')->first()
                ?? User::query()->firstOrFail();

            Branch::query()
                ->where('active', true)
                ->orderBy('name')
                ->take(2)
                ->get()
                ->each(fn (Branch $branch, int $index) => $this->seedAudit($branch, $user, $index));
        });
    }

    private function seedAudit(Branch $branch, User $user, int $index): void
    {
        $startedAt = now()->subDays(9 - $index)->setTime(8, 30);
        $closedAt = $startedAt->copy()->addHours(6);
        $products = BranchProduct::query()
            ->with(['product.category', 'product.subcategory', 'batches' => fn ($query) => $query->where('quantity', '>', 0)->orderBy('expiration_date')])
            ->where('branch_id', $branch->id)
            ->where('status', BranchProduct::STATUS_ACTIVE)
            ->orderBy('id')
            ->take(self::PRODUCTS_PER_AUDIT)
            ->get();

        if ($products->isEmpty()) {
            return;
        }

        $physicalCount = PhysicalCount::create([
            'folio' => self::FOLIO_PREFIX . strtoupper($branch->slug),
            'branch_id' => $branch->id,
            'created_by' => $user->id,
            'name' => 'Auditoría demo ' . $branch->name,
            'status' => $index === 0 ? 'closed' : 'open',
            'recapture_scope' => 'all',
            'started_at' => $startedAt,
            'closed_at' => $index === 0 ? $closedAt : null,
            'finalized_at' => $index === 0 ? $closedAt->copy()->addMinutes(30) : null,
            'finalized_by' => $index === 0 ? $user->id : null,
            'last_applied_at' => $index === 0 ? $closedAt->copy()->addMinutes(45) : null,
            'created_at' => $startedAt,
            'updated_at' => $closedAt,
        ]);

        $physicalCount->participants()->sync([$user->id]);

        $round = PhysicalCountRound::create([
            'physical_count_id' => $physicalCount->id,
            'round_number' => 1,
            'type' => 'original',
            'scope' => 'all',
            'opened_by' => $user->id,
            'started_at' => $startedAt,
            'closed_at' => $index === 0 ? $closedAt : null,
            'applied_at' => $index === 0 ? $closedAt->copy()->addMinutes(45) : null,
            'created_at' => $startedAt,
            'updated_at' => $closedAt,
        ]);

        $snapshot = PhysicalCountSnapshot::create([
            'physical_count_id' => $physicalCount->id,
            'branch_id' => $branch->id,
            'created_by' => $user->id,
            'captured_at' => $startedAt,
            'created_at' => $startedAt,
            'updated_at' => $startedAt,
        ]);

        foreach ($products as $productIndex => $branchProduct) {
            $batch = $branchProduct->batches->first();
            $systemStock = (float) $branchProduct->stock;
            $counted = max(0, $systemStock - (($productIndex % 3) === 0 ? 1 : 0));
            $damaged = ($productIndex % 4) === 0 ? 1 : 0;
            $expired = ($productIndex % 5) === 0 ? 1 : 0;

            PhysicalCountSnapshotItem::create([
                'physical_count_snapshot_id' => $snapshot->id,
                'branch_product_id' => $branchProduct->id,
                'product_id' => $branchProduct->product_id,
                'category_id' => $branchProduct->product?->category_id,
                'subcategory_id' => $branchProduct->product?->subcategory_id,
                'product_batch_id' => $batch?->id,
                'barcode' => $branchProduct->barcode,
                'product_name' => $branchProduct->product?->name ?? 'Producto sin nombre',
                'category_name' => $branchProduct->product?->category?->name,
                'subcategory_name' => $branchProduct->product?->subcategory?->name,
                'lot_number' => $batch?->lot_number,
                'expiration_date' => $batch?->expiration_date,
                'branch_product_status' => $branchProduct->status,
                'batch_status' => $batch?->status,
                'system_stock' => $systemStock,
                'batch_stock' => (float) ($batch?->quantity ?? 0),
                'created_at' => $startedAt,
                'updated_at' => $startedAt,
            ]);

            PhysicalCountEntry::create([
                'physical_count_id' => $physicalCount->id,
                'physical_count_round_id' => $round->id,
                'branch_product_id' => $branchProduct->id,
                'product_batch_id' => $batch?->id,
                'product_id' => $branchProduct->product_id,
                'user_id' => $user->id,
                'scanned_code' => $branchProduct->barcode,
                'counted_quantity' => $counted,
                'damaged_quantity' => $damaged,
                'expired_quantity' => $expired,
                'expiration_date' => $batch?->expiration_date,
                'notes' => $branchProduct->product?->has_box_presentation
                    ? 'Conteo demo capturado visualmente con opción piezas/cajas; stock base en piezas.'
                    : 'Conteo demo para validar reportes de auditoría.',
                'created_at' => $startedAt->copy()->addMinutes(20 + $productIndex),
                'updated_at' => $startedAt->copy()->addMinutes(20 + $productIndex),
            ]);
        }
    }

    private function clearPreviousSeedData(): void
    {
        PhysicalCount::withTrashed()
            ->where('folio', 'like', self::FOLIO_PREFIX . '%')
            ->get()
            ->each(function (PhysicalCount $physicalCount): void {
                $physicalCount->participants()->detach();
                $physicalCount->entries()->withTrashed()->forceDelete();
                $physicalCount->rounds()->delete();
                $physicalCount->snapshot?->items()->delete();
                $physicalCount->snapshot?->delete();
                $physicalCount->forceDelete();
            });
    }
}
