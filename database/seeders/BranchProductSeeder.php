<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Product;
use App\Models\BranchProduct;
use App\Models\ProductBatch;
use Illuminate\Database\Seeder;

class BranchProductSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::where('active', true)->get();
        $products = Product::where('active', true)->get();

        foreach ($branches as $branch) {
            foreach ($products as $product) {

                $tracksBatches = fake()->boolean(40);

                $tracksExpiration = $tracksBatches
                    ? fake()->boolean(80)
                    : false;

                $minStock = rand(5, 25);

                $stock = match (rand(1, 10)) {
                    1 => 0,
                    2, 3 => rand(1, $minStock),
                    default => rand($minStock + 1, 120),
                };

                $branchProduct = BranchProduct::updateOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'product_id' => $product->id,
                    ],
                    [
                        'price' => rand(50, 900) + rand(0, 99) / 100,
                        'stock' => $stock,
                        'min_stock' => $minStock,
                        'active' => rand(1, 10) <= 9,
                        'tracks_batches' => $tracksBatches,
                        'tracks_expiration' => $tracksExpiration,
                    ]
                );

                ProductBatch::where('branch_product_id', $branchProduct->id)->delete();

                if (! $tracksBatches || $stock <= 0) {
                    continue;
                }

                $remainingStock = $stock;
                $batchCount = rand(1, 3);

                for ($i = 1; $i <= $batchCount; $i++) {
                    $quantity = $i === $batchCount
                        ? $remainingStock
                        : rand(1, max(1, $remainingStock - ($batchCount - $i)));

                    $remainingStock -= $quantity;

                    ProductBatch::create([
                        'branch_product_id' => $branchProduct->id,
                        'lot_number' => strtoupper("LOT-{$branch->id}-{$product->id}-{$i}"),
                        'expiration_date' => $tracksExpiration
                            ? now()->addDays(rand(15, 180))->toDateString()
                            : null,
                        'initial_quantity' => $quantity,
                        'quantity' => $quantity,
                        'supplier' => fake()->company(),
                        'received_at' => now()->subDays(rand(1, 30))->toDateString(),
                        'status' => 'ACTIVE',
                    ]);
                }
            }
        }
    }
}
