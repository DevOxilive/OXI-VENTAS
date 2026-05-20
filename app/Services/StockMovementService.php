<?php

namespace App\Services;

use App\Models\BranchProduct;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class StockMovementService
{
    public function move(
        BranchProduct $branchProduct,
        string $type,
        string $reason,
        int $quantity,
        ?string $notes = null,
        ?int $userId = null
    ): StockMovement {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a 0.');
        }

        return DB::transaction(function () use (
            $branchProduct,
            $type,
            $reason,
            $quantity,
            $notes,
            $userId
        ) {
            $branchProduct = BranchProduct::whereKey($branchProduct->id)
                ->lockForUpdate()
                ->firstOrFail();

            $previousStock = $branchProduct->stock;

            switch ($type) {
                case 'IN':
                    $newStock = $previousStock + $quantity;
                    break;

                case 'OUT':
                    $newStock = $previousStock - $quantity;
                    break;

                case 'ADJUSTMENT':
                    $newStock = $quantity;
                    break;

                default:
                    throw new InvalidArgumentException('Tipo de movimiento inválido.');
            }

            if ($newStock < 0) {
                throw new InvalidArgumentException('No hay stock suficiente para realizar este movimiento.');
            }

            $branchProduct->update([
                'stock' => $newStock,
            ]);

            return StockMovement::create([
                'branch_product_id' => $branchProduct->id,
                'type' => $type,
                'reason' => $reason,
                'quantity' => $quantity,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'user_id' => $userId ?? Auth::id(),
                'notes' => $notes,
            ]);
        });
    }
}
