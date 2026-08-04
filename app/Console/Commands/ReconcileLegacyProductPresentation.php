<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReconcileLegacyProductPresentation extends Command
{
    protected $signature = 'inventory:reconcile-presentation
        {product : ID del producto}
        {--source= : Interpretación actual: boxes o pieces}
        {--pieces-per-box= : Piezas contenidas en cada caja}
        {--cost-piece= : Costo independiente por pieza}
        {--sale-piece= : Precio independiente por pieza}
        {--cost-box= : Costo independiente por caja}
        {--sale-box= : Precio independiente por caja}
        {--dry-run : Solo muestra el alcance}';

    protected $description = 'Concilia un producto histórico de caja y normaliza sus existencias a piezas.';

    public function handle(): int
    {
        $product = Product::query()->findOrFail((int) $this->argument('product'));
        $source = (string) $this->option('source');
        $factor = (int) $this->option('pieces-per-box');

        if (! in_array($source, ['boxes', 'pieces'], true) || $factor < 2) {
            $this->error('Debes indicar --source=boxes|pieces y --pieces-per-box con un valor mínimo de 2.');
            return self::FAILURE;
        }

        foreach (['cost-piece', 'sale-piece', 'cost-box', 'sale-box'] as $option) {
            if (! is_numeric($this->option($option)) || (float) $this->option($option) < 0) {
                $this->error("Debes proporcionar --{$option} con un valor válido.");
                return self::FAILURE;
            }
        }

        $branchProductIds = DB::table('branch_products')->where('product_id', $product->id)->pluck('id');
        $this->table(['Dato', 'Valor'], [
            ['Producto', "{$product->id} - {$product->name}"],
            ['Interpretación histórica', $source],
            ['Factor', $factor],
            ['Sucursales afectadas', $branchProductIds->count()],
        ]);

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($product, $source, $factor, $branchProductIds) {
            if ($source === 'boxes') {
                $this->scale('branch_products', 'id', $branchProductIds, ['stock', 'min_stock'], $factor);
                $this->scale('product_batches', 'branch_product_id', $branchProductIds, ['initial_quantity', 'quantity'], $factor);
                $this->scale('stock_movements', 'branch_product_id', $branchProductIds, ['quantity', 'previous_stock', 'new_stock'], $factor);
                $movementIds = DB::table('stock_movements')->whereIn('branch_product_id', $branchProductIds)->pluck('id');
                $this->scale('stock_movement_batches', 'stock_movement_id', $movementIds, ['quantity', 'previous_batch_quantity', 'new_batch_quantity'], $factor);
                $this->scale('physical_count_entries', 'branch_product_id', $branchProductIds, ['counted_quantity', 'damaged_quantity', 'expired_quantity'], $factor);
                $this->scale('physical_count_snapshot_items', 'branch_product_id', $branchProductIds, ['system_stock', 'batch_stock'], $factor);

                DB::table('stock_movements')->whereIn('branch_product_id', $branchProductIds)
                    ->whereNotNull('unit_cost')->update(['unit_cost' => DB::raw("unit_cost / {$factor}")]);
                DB::table('sale_details')->where('product_id', $product->id)->update([
                    'sale_unit' => 'box',
                    'pieces_per_box' => $factor,
                    'base_quantity' => DB::raw("quantity * {$factor}"),
                ]);
            } else {
                DB::table('sale_details')->where('product_id', $product->id)->update([
                    'sale_unit' => 'piece',
                    'base_quantity' => DB::raw('quantity'),
                    'pieces_per_box' => null,
                ]);
            }

            $product->update([
                'unit' => 'pza',
                'inventory_unit' => 'pza',
                'has_box_presentation' => true,
                'inventory_quantity_mode' => 'base',
                'pieces_per_box' => $factor,
                'cost_per_piece' => (float) $this->option('cost-piece'),
                'sale_price_per_piece' => (float) $this->option('sale-piece'),
                'cost_per_box' => (float) $this->option('cost-box'),
                'sale_price_per_box' => (float) $this->option('sale-box'),
                'cost' => (float) $this->option('cost-piece'),
                'sale_price' => (float) $this->option('sale-piece'),
            ]);
        });

        $this->info('Producto conciliado. Sus existencias operativas ya están expresadas en piezas.');
        return self::SUCCESS;
    }

    private function scale(string $table, string $key, $ids, array $columns, int $factor): void
    {
        if (! Schema::hasTable($table) || $ids->isEmpty()) {
            return;
        }

        $values = collect($columns)
            ->filter(fn ($column) => Schema::hasColumn($table, $column))
            ->mapWithKeys(fn ($column) => [$column => DB::raw("`{$column}` * {$factor}")])
            ->all();

        if ($values) {
            DB::table($table)->whereIn($key, $ids)->update($values);
        }
    }
}
