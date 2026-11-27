<?php

namespace App\Http\Controllers\Inventory;

use App\Events\PurchaseWorkflowChanged;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchasePreparationController extends Controller
{
    public function update(Request $request, Branch $branch)
    {
        abort_unless($request->user()->hasBranchAccess($branch->id), 403);
        $data = $request->validate([
            'action' => 'required|in:assign,add,remove',
            'responsible_id' => 'required_if:action,assign|nullable|integer|exists:users,id',
            'order_id' => 'required_unless:action,assign|integer',
        ]);
        DB::transaction(function () use ($request, $data) {
            DB::table('purchase_preparations')->insertOrIgnore(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $preparation = DB::table('purchase_preparations')->where('id', 1)->lockForUpdate()->first();
            if ($data['action'] === 'assign') {
                abort_unless($request->user()->hasPermission('inventory.purchase-orders.general.create'), 403);
                $currentResponsible = $preparation->responsible_id
                    ? User::query()->whereKey($preparation->responsible_id)->where('is_active', true)->first()
                    : null;
                abort_if($currentResponsible && (int) $currentResponsible->id !== (int) $request->user()->id, 422, 'Sólo la responsable actual puede pasar el mando.');

                $nextResponsible = User::query()->whereKey($data['responsible_id'])->where('is_active', true)->firstOrFail();
                abort_unless($nextResponsible->hasPermission('inventory.purchase-orders.general.create'), 422, 'La persona elegida no puede crear compras centrales.');
                DB::table('purchase_preparations')->where('id', 1)->update(['responsible_id' => $nextResponsible->id, 'updated_at' => now()]);
                return;
            }
            abort_unless($preparation->responsible_id, 422, 'Primero debe asumir la compra una responsable.');
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($data['order_id']);
            abort_unless($request->user()->hasBranchAccess($order->branch_id), 403);
            abort_unless($order->status === PurchaseOrder::STATUS_GENERATED && ! $order->general_purchase_order_id, 422);
            $ids = json_decode($preparation->order_ids ?? '[]', true);
            if ($data['action'] === 'add') {
                abort_unless($order->review_status === PurchaseOrder::REVIEW_APPROVED, 422, 'Aprueba la solicitud antes de agregarla.');
                $responsible = User::findOrFail($preparation->responsible_id);
                abort_unless($responsible->hasBranchAccess($order->branch_id), 422, 'La responsable no tiene acceso a esta sucursal.');
                $ids[] = $order->id;
            } else {
                $ids = array_diff($ids, [$order->id]);
            }
            DB::table('purchase_preparations')->where('id', 1)->update(['order_ids' => json_encode(array_values(array_unique($ids))), 'updated_at' => now()]);
        });
        PurchaseWorkflowChanged::dispatch('preparation_updated', isset($data['order_id']) ? (int) $data['order_id'] : null);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'La preparación de la compra se actualizó correctamente.',
            ]);
        }

        return back();
    }

    public function presence(Request $request, Branch $branch, PurchaseOrder $purchaseOrder)
    {
        abort_unless($request->user()->hasPermission('inventory.purchase-orders.source.view'), 403);
        abort_unless((int) $purchaseOrder->branch_id === (int) $branch->id, 404);
        $data = $request->validate(['open' => 'required|boolean']);
        $key = ['purchase_order_id' => $purchaseOrder->id, 'user_id' => $request->user()->id];
        DB::table('purchase_review_presence')->insertOrIgnore($key + ['opened_at' => now(), 'last_seen_at' => null]);
        $wasActive = DB::table('purchase_review_presence')->where($key)->whereNotNull('last_seen_at')->exists();
        DB::table('purchase_review_presence')->where($key)->update(['last_seen_at' => $data['open'] ? now() : null]);
        if (! $data['open'] || ! $wasActive) {
            PurchaseWorkflowChanged::dispatch($data['open'] ? 'review_opened' : 'review_closed', $purchaseOrder->id);
        }

        return response()->noContent();
    }
}
