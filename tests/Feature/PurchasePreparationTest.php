<?php

namespace Tests\Feature;

use App\Models\{Branch, User, Role, Permission, PurchaseOrder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchasePreparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_selection_and_exclusive_responsibility(): void
    {
        $this->withoutMiddleware([\App\Http\Middleware\PreventDuplicateSubmission::class]);
        $branch = Branch::create(['name' => 'Compras', 'slug' => 'compras', 'active' => true]);
        $role = Role::create(['name' => 'Compras test']);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'inventory.purchase-orders.general.create']));
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'inventory.purchase-orders.source.view']));
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'inventory.purchase-orders.source.review']));
        $first = User::factory()->create(['role_id' => $role->id]);
        $second = User::factory()->create(['role_id' => $role->id]);
        $first->branches()->attach($branch);
        $second->branches()->attach($branch);
        $url = route('inventory.branches.reports.purchase-orders.preparation', $branch);
        $this->actingAs($first)->put($url, ['action' => 'assign', 'responsible_id' => $first->id])->assertRedirect();
        $this->actingAs($second)->putJson($url, ['action' => 'assign', 'responsible_id' => $second->id])->assertStatus(422);
        $this->actingAs($first)->put($url, ['action' => 'assign', 'responsible_id' => $second->id])->assertRedirect();
        $this->assertSame($second->id, (int) DB::table('purchase_preparations')->value('responsible_id'));
        $this->actingAs($second)->put($url, ['action' => 'assign', 'responsible_id' => $first->id])->assertRedirect();
        $order = PurchaseOrder::create(['branch_id' => $branch->id, 'user_id' => $first->id, 'status' => 'GENERATED', 'review_status' => 'APPROVED']);
        $this->put($url, ['action' => 'add', 'order_id' => $order->id])->assertRedirect();
        $this->assertSame([$order->id], json_decode(DB::table('purchase_preparations')->value('order_ids'), true));
        $this->postJson(route('inventory.branches.reports.purchase-orders.consolidate', $branch), ['order_ids' => [$order->id]])->assertForbidden();
        $this->actingAs($first)->put($url, ['action' => 'remove', 'order_id' => $order->id])->assertRedirect();
        $this->assertSame([], json_decode(DB::table('purchase_preparations')->value('order_ids'), true));
        $order->update(['review_status' => 'PENDING']);
        $this->putJson($url, ['action' => 'add', 'order_id' => $order->id])->assertStatus(422);
        $presence = route('inventory.branches.reports.purchase-orders.presence', ['branch' => $branch->id, 'purchaseOrder' => $order->id]);
        $this->putJson($presence, ['open' => true])->assertNoContent();
        $this->assertNotNull(DB::table('purchase_review_presence')->value('last_seen_at'));
        $this->putJson($presence, ['open' => false])->assertNoContent();
        $this->assertNull(DB::table('purchase_review_presence')->value('last_seen_at'));
        $this->assertNotNull(DB::table('purchase_review_presence')->value('opened_at'));

        $reviewOrder = PurchaseOrder::create(['branch_id' => $branch->id, 'user_id' => $first->id, 'status' => 'GENERATED', 'review_status' => 'PENDING']);
        $reviewUrl = route('inventory.branches.reports.purchase-orders.source-orders.review', [
            'branch' => $branch->id,
            'purchaseOrder' => $reviewOrder->id,
        ]);
        $this->actingAs($first)->put($reviewUrl, ['review_status' => 'APPROVED'])->assertRedirect();
        $this->assertContains($reviewOrder->id, json_decode(DB::table('purchase_preparations')->value('order_ids'), true));
        $this->put($reviewUrl, ['review_status' => 'REJECTED'])->assertRedirect();
        $this->assertNotContains($reviewOrder->id, json_decode(DB::table('purchase_preparations')->value('order_ids'), true));
        $this->put($reviewUrl, ['review_status' => 'PENDING'])->assertRedirect();
        $this->assertSame('PENDING', $reviewOrder->fresh()->review_status);
        $this->put($reviewUrl, ['review_status' => 'REJECTED'])->assertRedirect();
        $this->delete(route('inventory.branches.reports.purchase-orders.source-orders.destroy', [
            'branch' => $branch->id,
            'purchaseOrder' => $reviewOrder->id,
        ]))->assertRedirect();
        $this->assertDatabaseMissing('purchase_orders', ['id' => $reviewOrder->id]);

        $first->delete();
        $this->actingAs($second)->put($url, ['action' => 'assign', 'responsible_id' => $second->id])->assertRedirect();
        $this->assertSame($second->id, (int) DB::table('purchase_preparations')->value('responsible_id'));
    }
}
