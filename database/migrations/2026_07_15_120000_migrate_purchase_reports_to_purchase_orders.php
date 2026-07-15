<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_reports') || ! Schema::hasTable('purchase_orders')) {
            return;
        }

        DB::statement(
            "ALTER TABLE purchase_orders MODIFY status ENUM('DRAFT', 'GENERATED', 'COMPLETED', 'CANCELLED') NOT NULL DEFAULT 'DRAFT'"
        );

        DB::transaction(function () {
            DB::table('purchase_reports')
                ->orderBy('id')
                ->each(function (object $legacyReport) {
                    $folio = $legacyReport->folio ?: sprintf('LEGACY-%06d', $legacyReport->id);
                    $orderId = DB::table('purchase_orders')
                        ->where('folio', $folio)
                        ->value('id');

                    if (! $orderId) {
                        $orderId = DB::table('purchase_orders')->insertGetId([
                            'branch_id' => $legacyReport->branch_id,
                            'user_id' => $legacyReport->user_id,
                            'folio' => $folio,
                            'source' => 'LEGACY_REPORT',
                            'status' => $legacyReport->status,
                            'estimated_total' => 0,
                            'actual_total' => 0,
                            'notes' => $legacyReport->notes,
                            'generated_at' => $legacyReport->generated_at,
                            'created_at' => $legacyReport->created_at,
                            'updated_at' => $legacyReport->updated_at,
                        ]);
                    }

                    $estimatedTotal = 0;

                    DB::table('purchase_report_items')
                        ->where('purchase_report_id', $legacyReport->id)
                        ->orderBy('id')
                        ->each(function (object $legacyItem) use ($orderId, &$estimatedTotal) {
                            $productId = DB::table('branch_products')
                                ->where('id', $legacyItem->branch_product_id)
                                ->value('product_id');
                            $estimatedTotal += (float) ($legacyItem->estimated_total ?? 0);

                            DB::table('purchase_order_items')->updateOrInsert(
                                [
                                    'purchase_order_id' => $orderId,
                                    'branch_product_id' => $legacyItem->branch_product_id,
                                ],
                                [
                                    'product_id' => $productId,
                                    'current_stock' => $legacyItem->current_stock,
                                    'min_stock' => $legacyItem->min_stock,
                                    'requested_quantity' => $legacyItem->requested_quantity,
                                    'estimated_price' => $legacyItem->estimated_price,
                                    'estimated_total' => $legacyItem->estimated_total,
                                    'status' => 'REQUESTED',
                                    'created_at' => $legacyItem->created_at,
                                    'updated_at' => $legacyItem->updated_at,
                                ]
                            );
                        });

                    DB::table('purchase_orders')
                        ->where('id', $orderId)
                        ->update(['estimated_total' => $estimatedTotal]);
                });
        });
    }

    public function down(): void
    {
        $legacyOrderIds = DB::table('purchase_orders')
            ->where('source', 'LEGACY_REPORT')
            ->pluck('id');

        DB::table('purchase_order_items')
            ->whereIn('purchase_order_id', $legacyOrderIds)
            ->delete();

        DB::table('purchase_orders')
            ->whereIn('id', $legacyOrderIds)
            ->delete();
    }
};
