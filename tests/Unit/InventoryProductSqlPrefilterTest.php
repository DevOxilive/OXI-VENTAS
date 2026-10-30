<?php

namespace Tests\Unit;

use App\Search\InventoryProductSqlPrefilter;
use App\Search\ProductSearchOptions;
use Illuminate\Database\Connection;
use Mockery;
use PDO;
use Tests\TestCase;

class InventoryProductSqlPrefilterTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_prefilter_is_opt_in_and_survives_lot_option_copy(): void
    {
        $this->assertFalse((new ProductSearchOptions)->inventorySqlPrefilter);
        $options = new ProductSearchOptions(includeLotNumbers: true, inventorySqlPrefilter: true);
        $this->assertTrue($options->withoutLotNumbers()->inventorySqlPrefilter);
        $this->assertFalse($options->withoutLotNumbers()->includeLotNumbers);
    }

    public function test_fragments_preserve_numeric_zeroes_and_expand_only_allowed_typo_lengths(): void
    {
        $prefilter = app(InventoryProductSqlPrefilter::class);
        $this->assertSame(['0012345678901'], $prefilter->fragments('0012345678901'));
        $this->assertSame(['coc'], $prefilter->fragments('coc'));
        $this->assertSame(['co', 'caa'], $prefilter->fragments('cocaa'));
        $this->assertSame(['cho', 'col', 'atte'], $prefilter->fragments('chocolatte'));
    }

    public function test_json_table_requires_a_supported_server_version_including_mariadb_through_mysql(): void
    {
        $prefilter = app(InventoryProductSqlPrefilter::class);
        foreach ([
            ['mysql', '5.7.44', false], ['mysql', '8.0.3', false], ['mysql', '8.0.4', true],
            ['mysql', '8.0.46-0ubuntu0.22.04.2', true], ['mysql', '8.4.1', true],
            ['mariadb', '10.5.29-MariaDB', false], ['mariadb', '10.6.0-MariaDB', true],
            ['mysql', '5.5.5-10.5.29-MariaDB', false], ['mysql', '5.5.5-10.6.21-MariaDB', true],
            ['mysql', '11.8.3-MariaDB', true], ['mysql', 'unknown', false],
            ['pgsql', '16.0.0', false], ['sqlsrv', '16.0.0', false], ['sqlite', '3.45.0', true],
        ] as [$driver, $version, $supported]) {
            $this->assertSame($supported, $prefilter->supportsVersion($driver, $version), $driver.' '.$version);
        }
    }

    public function test_server_compatibility_uses_pdo_metadata_without_running_sql(): void
    {
        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('getAttribute')->once()->with(PDO::ATTR_SERVER_VERSION)->andReturn('5.5.5-10.6.21-MariaDB');
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->once()->andReturn('mysql');
        $connection->shouldReceive('getPdo')->once()->andReturn($pdo);
        $connection->shouldNotReceive('select');

        $this->assertTrue(app(InventoryProductSqlPrefilter::class)->supportsConnection($connection));
    }
}
