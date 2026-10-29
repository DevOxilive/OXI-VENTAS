<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Concerns\AuthorizesBranchAccess;
use App\Http\Controllers\Controller;
use App\Models\BranchProduct;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Models\StockMovementBatch;
use App\Services\InventoryBackgroundContext;
use App\Services\InventoryOutbox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductBatchController extends Controller
{
    use AuthorizesBranchAccess;

    public function update(Request $request, ProductBatch $productBatch)
    {
        $productBatch->loadMissing('branchProduct.branch', 'branchProduct.product');
        $this->abortIfUserCannotAccessBranch($request, $productBatch->branchProduct->branch);

        $validated = $request->validate([
            'lot_number' => ['nullable', 'string', 'max:255'],
            'expiration_date' => ['nullable', 'date'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'received_at' => ['nullable', 'date'],
            'quantity_delta' => ['sometimes', 'required', 'numeric', 'prohibits:quantity'],
            'quantity' => ['sometimes', 'required', 'numeric', 'prohibits:quantity_delta'],
            'expected_quantity' => ['required_with:quantity', 'numeric'],
            'season_start_date' => ['nullable', 'date'],
            'season_end_date' => ['nullable', 'date'],
            'status' => [
                'sometimes',
                'required',
                Rule::in([
                    ProductBatch::STATUS_ACTIVE,
                    ProductBatch::STATUS_INACTIVE,
                    ProductBatch::STATUS_SEASONAL,
                ]),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
            'expected_values' => ['sometimes', 'array:lot_number,expiration_date,supplier,received_at,season_start_date,season_end_date,status'],
            'expected_values.*' => ['nullable', 'string', 'max:255'],
            'expected_values.status' => ['required_with:quantity_delta,quantity', Rule::in([
                ProductBatch::STATUS_ACTIVE, ProductBatch::STATUS_INACTIVE, ProductBatch::STATUS_SEASONAL,
            ])],
        ]);

        app(InventoryBackgroundContext::class)->run(fn () => DB::transaction(function () use ($validated, $productBatch, $request) {
            // Todos los movimientos reservan primero el inventario y después el lote.
            $branchProduct = BranchProduct::whereKey($productBatch->branch_product_id)
                ->lockForUpdate()
                ->firstOrFail();

            $productBatch = ProductBatch::whereKey($productBatch->id)
                ->where('branch_product_id', $branchProduct->id)
                ->lockForUpdate()
                ->firstOrFail();

            $branchProduct->load('product:id,name,unit,inventory_unit,inventory_quantity_mode,cost,cost_per_piece');

            if ($branchProduct->isInactive() && (
                array_key_exists('quantity_delta', $validated)
                || array_key_exists('quantity', $validated)
                || array_key_exists('status', $validated)
            )) {
                throw ValidationException::withMessages([
                    'quantity' => 'El producto fue deshabilitado en esta sucursal. Consulta su estado antes de modificar existencias.',
                ]);
            }

            if ($branchProduct->product?->inventory_quantity_mode === 'legacy_presentation') {
                throw ValidationException::withMessages([
                    'quantity' => 'Este producto conserva existencias históricas por conciliar antes de modificar lotes.',
                ]);
            }

            $previousData = [
                'lot_number' => $productBatch->lot_number,
                'expiration_date' => optional($productBatch->expiration_date)->format('Y-m-d'),
                'supplier' => $productBatch->supplier,
                'received_at' => optional($productBatch->received_at)->format('Y-m-d'),
                'quantity' => (float) $productBatch->quantity,
                'season_start_date' => optional($productBatch->season_start_date)->format('Y-m-d'),
                'season_end_date' => optional($productBatch->season_end_date)->format('Y-m-d'),
                'status' => $productBatch->status,
            ];

            if ((array_key_exists('quantity_delta', $validated) || array_key_exists('quantity', $validated))
                && $validated['expected_values']['status'] !== $previousData['status']) {
                throw ValidationException::withMessages([
                    'status' => 'Otra persona cambió el estado del lote. Revisa su estado actual antes de modificar la cantidad.',
                ]);
            }

            $newData = $previousData;
            foreach (array_keys($previousData) as $field) {
                if ($field === 'quantity' || ! array_key_exists($field, $validated)) {
                    continue;
                }

                $value = $validated[$field];
                if ((string) ($previousData[$field] ?? '') === (string) ($value ?? '')) {
                    continue;
                }

                $expectedValues = $validated['expected_values'] ?? [];
                if (! array_key_exists($field, $expectedValues)
                    || (string) ($previousData[$field] ?? '') !== (string) ($expectedValues[$field] ?? '')) {
                    throw ValidationException::withMessages([
                        $field => 'Otra persona modificó este dato del lote. Tus cambios siguen en el formulario; revisa la información actual antes de guardar.',
                    ]);
                }

                $newData[$field] = $value;
            }

            if (array_key_exists('quantity', $validated)) {
                if (abs((float) $validated['expected_quantity'] - $previousData['quantity']) > 0.0000001) {
                    throw ValidationException::withMessages([
                        'quantity' => 'La cantidad del lote cambió. Vuelve a consultar el saldo antes de establecer una cantidad exacta.',
                    ]);
                }
                $newData['quantity'] = (float) $validated['quantity'];
            } elseif (array_key_exists('quantity_delta', $validated)) {
                $this->validateQuantityForUnit(
                    $validated['quantity_delta'],
                    (string) ($branchProduct->product?->inventory_unit ?? $branchProduct->product?->unit ?? 'pza'),
                );
                $newData['quantity'] = $previousData['quantity'] + (float) $validated['quantity_delta'];
            }

            $this->validateQuantityForUnit(
                $newData['quantity'],
                (string) ($branchProduct->product?->inventory_unit ?? $branchProduct->product?->unit ?? 'pza'),
            );
            $newData['quantity'] = round($newData['quantity'], 3);

            if ($newData['season_start_date'] && $newData['season_end_date']
                && $newData['season_end_date'] < $newData['season_start_date']) {
                throw ValidationException::withMessages([
                    'season_end_date' => 'La fecha final debe ser igual o posterior a la fecha inicial de temporada.',
                ]);
            }

            $changedFields = collect($newData)
                ->filter(fn ($value, $field) => (string) ($previousData[$field] ?? '') !== (string) ($value ?? ''))
                ->keys()
                ->values()
                ->all();

            if (empty($changedFields)) {
                return [$branchProduct, false];
            }

            $previousQuantity = $previousData['quantity'];
            $newQuantity = $newData['quantity'];

            $previousStock = (float) ProductBatch::where('branch_product_id', $branchProduct->id)
                ->whereIn('status', [
                    ProductBatch::STATUS_ACTIVE,
                    ProductBatch::STATUS_SEASONAL,
                ])
                ->sum('quantity');

            $productBatch->update($newData);

            $newStock = (float) ProductBatch::where('branch_product_id', $branchProduct->id)
                ->whereIn('status', [
                    ProductBatch::STATUS_ACTIVE,
                    ProductBatch::STATUS_SEASONAL,
                ])
                ->sum('quantity');

            $branchProduct->update([
                'stock' => $newStock,
            ]);
            $stockDifference = round($newStock - $previousStock, 3);

            $movementNotes = $this->buildMovementNotes(
                changedFields: $changedFields,
                previousData: $previousData,
                newData: $newData,
                userNotes: $validated['notes'] ?? null
            );

            $movement = StockMovement::create([
                'branch_product_id' => $branchProduct->id,
                'user_id' => $request->user()?->id,
                'type' => StockMovement::TYPE_ADJUSTMENT,
                'reason' => StockMovement::REASON_INVENTORY_DIFFERENCE,
                'quantity' => $stockDifference,
                'unit_cost' => $branchProduct->product?->cost_per_piece ?? $branchProduct->product?->cost ?? 0,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'notes' => $movementNotes,
            ]);

            StockMovementBatch::create([
                'stock_movement_id' => $movement->id,
                'product_batch_id' => $productBatch->id,
                'quantity' => abs($stockDifference),
                'previous_batch_quantity' => $previousQuantity,
                'new_batch_quantity' => $newQuantity,
                'allocation_method' => StockMovementBatch::ALLOCATION_MANUAL,
            ]);

            app(InventoryOutbox::class)->stockChanged(
                $branchProduct, 'actualizó', 'el lote del producto', 'batch_updated',
            );

            return [$branchProduct, true];
        }, attempts: 3));

        return back()->with('success', 'Lote actualizado correctamente.');
    }

    private function buildMovementNotes(array $changedFields, array $previousData, array $newData, ?string $userNotes = null): string
    {
        $labels = [
            'lot_number' => 'número de lote',
            'expiration_date' => 'fecha de caducidad',
            'supplier' => 'proveedor',
            'received_at' => 'fecha de ingreso',
            'quantity' => 'cantidad',
            'season_start_date' => 'inicio de temporada',
            'season_end_date' => 'fin de temporada',
            'status' => 'estado',
        ];

        $changedLabels = collect($changedFields)
            ->map(fn ($field) => ($labels[$field] ?? $field).': '
                .json_encode($previousData[$field], JSON_UNESCAPED_UNICODE).' → '
                .json_encode($newData[$field], JSON_UNESCAPED_UNICODE))
            ->implode('; ');

        $baseNote = "Ajuste manual de lote. Cambios: {$changedLabels}.";

        if ($userNotes) {
            return "{$baseNote} Nota: {$userNotes}";
        }

        return $baseNote;
    }

    private function validateQuantityForUnit(mixed $quantity, string $unit): void
    {
        $value = (float) $quantity;

        if ($unit === 'kg') {
            if (abs($value) <= 999999999.999 && abs($value - round($value, 3)) <= 0.0000001) {
                return;
            }

            throw ValidationException::withMessages([
                'quantity' => 'El saldo en kilogramos debe estar entre -999999999.999 y 999999999.999, con un máximo de tres decimales.',
            ]);
        }

        if (abs($value) <= 999999999 && abs($value - round($value)) <= 0.0000001) {
            return;
        }

        throw ValidationException::withMessages([
            'quantity' => 'El saldo debe ser un número entero entre -999999999 y 999999999.',
        ]);
    }
}
