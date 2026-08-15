<?php

namespace Tests\Unit;

use App\Support\TablePagination;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TablePaginationTest extends TestCase
{
    public function test_it_uses_the_global_default_when_per_page_is_missing(): void
    {
        $this->assertSame(25, TablePagination::resolvePerPage(Request::create('/')));
    }

    #[DataProvider('allowedPageSizes')]
    public function test_it_accepts_every_global_page_size(int $pageSize): void
    {
        $request = Request::create('/', 'GET', ['per_page' => $pageSize]);

        $this->assertSame($pageSize, TablePagination::resolvePerPage($request));
    }

    public function test_it_rejects_page_sizes_outside_the_global_contract(): void
    {
        $request = Request::create('/', 'GET', ['per_page' => 30]);

        $this->assertSame(25, TablePagination::resolvePerPage($request));
    }

    public static function allowedPageSizes(): array
    {
        return [[10], [25], [50], [100], [200]];
    }
}
