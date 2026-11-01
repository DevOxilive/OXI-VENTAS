<?php

namespace Tests\Unit;

use App\Models\Branch;
use App\Services\SaleFolioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SaleFolioServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_branch_can_reserve_the_same_next_folio_independently(): void
    {
        $firstBranch = Branch::create(['name' => 'Tiendita', 'slug' => 'tiendita', 'active' => true]);
        $secondBranch = Branch::create(['name' => 'San Cristobal', 'slug' => 'san-cristobal', 'active' => true]);
        DB::table('branches')->where('id', $firstBranch->id)->update(['next_sale_folio' => 302]);
        DB::table('branches')->where('id', $secondBranch->id)->update(['next_sale_folio' => 302]);

        $service = app(SaleFolioService::class);
        $folios = DB::transaction(fn () => [
            $service->nextForBranch($firstBranch->id),
            $service->nextForBranch($secondBranch->id),
        ]);

        $this->assertSame(['V-000302', 'V-000302'], $folios);
        $this->assertDatabaseHas('branches', ['id' => $firstBranch->id, 'next_sale_folio' => 303]);
        $this->assertDatabaseHas('branches', ['id' => $secondBranch->id, 'next_sale_folio' => 303]);
    }

    public function test_a_folio_cannot_be_reserved_outside_the_sale_transaction(): void
    {
        DB::shouldReceive('transactionLevel')->once()->andReturn(0);

        $this->expectException(\LogicException::class);
        app(SaleFolioService::class)->nextForBranch(1);
    }
}
