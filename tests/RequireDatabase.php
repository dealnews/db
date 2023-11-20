<?php

namespace DealNews\DB\Tests;

trait RequireDatabase {
    public function setUp(): void {

        global $MYSQL_CONNECTED, $PGSQL_CONNECTED;

        if (!INTEGRATION_TESTS) {
            return;
        }

        if ($MYSQL_CONNECTED === null) {
            $start     = microtime(true);
            $connected = false;
            do {
                try {
                    $crud            = \DealNews\DB\CRUD::factory('mytestdb');
                    $connected       = true;
                    $MYSQL_CONNECTED = true;
                } catch (\Throwable $e) {
                    echo $e->getMessage() . "\n";
                    $connected = false;
                    if ((microtime(true) - $start) >= 30) {
                        $MYSQL_CONNECTED = false;
                        break;
                    }
                }
            } while (!$connected);
        }

        if (!$MYSQL_CONNECTED) {
            $this->markTestSkipped('Could not connect to MySQL database');
        }

        if ($PGSQL_CONNECTED === null) {
            $start     = microtime(true);
            $connected = false;
            do {
                try {
                    $crud            = \DealNews\DB\CRUD::factory('pgtestdb');
                    $connected       = true;
                    $PGSQL_CONNECTED = true;
                } catch (\Throwable $e) {
                    $connected = false;
                    if ((microtime(true) - $start) >= 30) {
                        $PGSQL_CONNECTED = false;
                        break;
                    }
                }
            } while (!$connected);
        }

        if (!$PGSQL_CONNECTED) {
            $this->markTestSkipped('Could not connect to Postgres database');
        }
    }
}
