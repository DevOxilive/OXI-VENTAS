<?php

namespace Tests\Unit;

use App\Events\SaleReturnChanged;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use PHPUnit\Framework\TestCase;

class SaleReturnWorkflowTest extends TestCase
{
    public function test_return_change_is_published_only_as_an_after_commit_event(): void
    {
        $event = new SaleReturnChanged(
            action: 'registered',
            saleId: 213,
            cancellationId: 7,
            branchId: 1,
            amount: 6,
            applicationType: 'account_adjustment',
            saleStatus: 'completed',
        );

        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, $event);
        $this->assertSame('sale-return.changed', $event->broadcastAs());
        $this->assertSame(['sales.branch.1', 'systems'], array_map(
            fn ($channel) => $channel->name,
            $event->broadcastOn(),
        ));
        $this->assertSame([
            'action' => 'registered',
            'sale_id' => 213,
            'sale_cancellation_id' => 7,
            'branch_id' => 1,
            'amount' => 6.0,
            'application_type' => 'account_adjustment',
            'sale_status' => 'completed',
        ], $event->broadcastWith());
    }

    public function test_return_service_validates_all_items_before_creating_the_return(): void
    {
        $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/SaleCancellationService.php');

        $preparePosition = strpos($service, '$preparedItems = $this->prepareReturnItems');
        $createPosition = strpos($service, '$cancellation = SaleCancellation::create');

        $this->assertNotFalse($preparePosition);
        $this->assertNotFalse($createPosition);
        $this->assertLessThan($createPosition, $preparePosition);
        $this->assertStringContainsString('return DB::transaction(', $service);
        $this->assertStringContainsString('->lockForUpdate()', $service);
    }

    public function test_void_return_is_permissioned_audited_and_available_to_sellers(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = file_get_contents($root.'/routes/web.php');
        $service = file_get_contents($root.'/app/Services/SaleCancellationService.php');
        $model = file_get_contents($root.'/app/Models/SaleCancellation.php');
        $page = file_get_contents($root.'/resources/js/Pages/Ventas/SalesHistory.vue');
        $permissionMigration = file_get_contents($root.'/database/migrations/2026_10_23_091000_add_void_sale_return_permission.php');

        $this->assertStringContainsString("->middleware(['permission:sales.returns.void', 'idempotent'])", $routes);
        $this->assertStringContainsString('public function voidCancellation(', $service);
        $this->assertStringContainsString("action: 'voided'", $service);
        $this->assertStringContainsString("'voided_by_user_id'", $model);
        $this->assertStringContainsString('sales.returns.void', $page);
        $this->assertStringContainsString("['Vendedor', 'Administrador', 'Super Administrador']", $permissionMigration);
    }
}
