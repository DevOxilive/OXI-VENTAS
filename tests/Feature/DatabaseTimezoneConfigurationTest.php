<?php

namespace Tests\Feature;

use Tests\TestCase;

class DatabaseTimezoneConfigurationTest extends TestCase
{
    public function test_mysql_connections_are_pinned_to_utc(): void
    {
        $this->assertSame('+00:00', config('database.connections.mysql.timezone'));
        $this->assertSame('+00:00', config('database.connections.mariadb.timezone'));
        $this->assertSame('UTC', config('app.timezone'));
    }
}
