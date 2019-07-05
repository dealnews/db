<?php

/**
 * Tests the DB Factory class
 *
 * @author      Brian Moon <brianm@dealnews.com>
 * @copyright   1997-Present DealNews.com, Inc
 * @package     DB
 *
 */

namespace DealNews\DB\Tests;

use \DealNews\DB\Factory;

class FactoryTest extends \PHPUnit\Framework\TestCase {

    protected static $containers = [
        "mysql" => [
            "name"    => "dealnews-db-mysql-test-instance",
            "run"     => __DIR__."/run_mysql.sh",
            "started" => false,
        ],
        "postgres" => [
            "name"    => "dealnews-db-postgres-test-instance",
            "run"     => __DIR__."/run_pgsql.sh",
            "started" => false,
        ],
    ];

    public function testGetConfigEmptyDB() {
        $gc = $this->getMockBuilder('\DealNews\GetConfig\GetConfig')
                   ->setMethods(['get'])
                   ->disableOriginalConstructor()
                   ->getMock();

        // Create a map of arguments to return values.
        $map = [
            ['db.factory.prefix',        null],
            ['test.type',    'type'],
            ['test.db',       null],
            ['test.user',    'user'],
            ['test.pass',    'pass'],
            ['test.dsn',     'dsn'],
            ['test.options', 'options'],
            ['test.server',  'server'],
            ['test.port',    'port'],
            ['test.charset', 'charset'],
        ];

        // Configure the stub.
        $gc->method('get')
             ->will($this->returnValueMap($map));

        $config = Factory::get_config("test", $gc);
        $this->assertEquals(
            [
                'type'    => 'type',
                'db'      => 'test',
                'user'    => 'user',
                'pass'    => 'pass',
                'dsn'     => 'dsn',
                'options' => 'options',
                'server'  => 'server',
                'port'    => 'port',
                'charset' => 'charset',
            ],
            $config
        );
    }

    public function testGetConfigDefaultPrefix() {
        $gc = $this->getMockBuilder('\DealNews\GetConfig\GetConfig')
                   ->setMethods(['get'])
                   ->disableOriginalConstructor()
                   ->getMock();

        // Create a map of arguments to return values.
        $map = [
            ['db.factory.prefix',        null],
            ['test.type',    'type'],
            ['test.db',      'db'],
            ['test.user',    'user'],
            ['test.pass',    'pass'],
            ['test.dsn',     'dsn'],
            ['test.options', 'options'],
            ['test.server',  'server'],
            ['test.port',    'port'],
            ['test.charset', 'charset'],
        ];

        // Configure the stub.
        $gc->method('get')
             ->will($this->returnValueMap($map));

        $config = Factory::get_config("test", $gc);
        $this->assertEquals(
            [
                'type'    => 'type',
                'db'      => 'db',
                'user'    => 'user',
                'pass'    => 'pass',
                'dsn'     => 'dsn',
                'options' => 'options',
                'server'  => 'server',
                'port'    => 'port',
                'charset' => 'charset',
            ],
            $config
        );
    }

    public function testGetConfigCustomPrefix() {
        $gc = $this->getMockBuilder('\DealNews\GetConfig\GetConfig')
                   ->setMethods(['get'])
                   ->disableOriginalConstructor()
                   ->getMock();

        // Create a map of arguments to return values.
        $map = [
            ['db.factory.prefix',        'test.prefix'],
            ['test.prefix.test.type',    'type'],
            ['test.prefix.test.db',      'db'],
            ['test.prefix.test.user',    'user'],
            ['test.prefix.test.pass',    'pass'],
            ['test.prefix.test.dsn',     'dsn'],
            ['test.prefix.test.options', 'options'],
            ['test.prefix.test.server',  'server'],
            ['test.prefix.test.port',    'port'],
            ['test.prefix.test.charset', 'charset'],
        ];

        // Configure the stub.
        $gc->method('get')
             ->will($this->returnValueMap($map));

        $config = Factory::get_config("test", $gc);
        $this->assertEquals(
            [
                'type'    => 'type',
                'db'      => 'db',
                'user'    => 'user',
                'pass'    => 'pass',
                'dsn'     => 'dsn',
                'options' => 'options',
                'server'  => 'server',
                'port'    => 'port',
                'charset' => 'charset',
            ],
            $config
        );
    }

    /**
     * @dataProvider loadConfigData
     */
    public function testLoadConfig($config, $options, $type, $expect) {
        $config = Factory::load_config($config, $options, $type);
        $this->assertEquals(
            $expect,
            $config
        );
    }

    public function loadConfigData() {
        return [
            // Basic PDO config
            [
                [
                        'type'    => 'pdo',
                    'db'      => null,
                    'user'    => 'user',
                    'pass'    => 'pass',
                    'dsn'     => 'dsn',
                    'options' => null,
                    'server'  => null,
                    'port'    => null,
                    'charset' => null,
                ],
                [],
                null,
                [
                    'type'    => 'pdo',
                    'db'      => null,
                    'user'    => 'user',
                    'pass'    => 'pass',
                    'dsn'     => 'dsn',
                    'options' => [],
                    'server'  => null,
                    'port'    => null,
                    'charset' => null,
                ],
            ],
            // PDO Options
            [
                [
                    'type'    => 'pdo',
                    'db'      => null,
                    'user'    => 'user',
                    'pass'    => 'pass',
                    'dsn'     => 'dsn',
                    'options' => '{"1":1,"2":2,"3":3}',
                    'server'  => null,
                    'port'    => null,
                    'charset' => null,
                ],
                [4=>4,5=>5,6=>6],
                null,
                [
                    'type'    => 'pdo',
                    'db'      => null,
                    'user'    => 'user',
                    'pass'    => 'pass',
                    'dsn'     => 'dsn',
                    'options' => [1=>1,2=>2,3=>3,4=>4,5=>5,6=>6],
                    'server'  => null,
                    'port'    => null,
                    'charset' => null,
                ],
            ],
            // MySQL Default
            [
                [
                    'db'      => 'test',
                    'user'    => 'user',
                    'pass'    => 'pass',
                    'dsn'     => null,
                    'options' => null,
                    'server'  => 'test',
                    'port'    => null,
                    'charset' => null,
                ],
                [],
                null,
                [
                    'type'    => 'mysql',
                    'db'      => 'test',
                    'user'    => 'user',
                    'pass'    => 'pass',
                    'dsn'     => 'mysql:host=test;port=;dbname=test;charsetutf8mb4',
                    'options' => [],
                    'server'  => ['test'],
                    'port'    => null,
                    'charset' => 'utf8mb4',
                ],
            ],
            // PgSQL
            [
                [
                    'db'      => 'test',
                    'user'    => 'user',
                    'pass'    => 'pass',
                    'dsn'     => null,
                    'options' => null,
                    'server'  => 'test',
                    'port'    => null,
                    'charset' => null,
                ],
                [],
                "pgsql",
                [
                    'type'    => 'pgsql',
                    'db'      => 'test',
                    'user'    => 'user',
                    'pass'    => 'pass',
                    'dsn'     => 'pgsql:host=test;port=;dbname=test',
                    'options' => [],
                    'server'  => ['test'],
                    'port'    => null,
                    'charset' => null,
                ],
            ]
        ];
    }

    /**
     * @group integration
     */
    public function testInit() {

        $drivers = \PDO::getAvailableDrivers();

        if (!in_array("sqlite", $drivers)) {
            $this->markTestSkipped("PDO SQLite Driver not installed");
        }

        $db1 = Factory::init("chinook");
        $db2 = Factory::init("chinook");

        $this->assertSame($db1, $db2);
    }

    /**
     * @group functional
     * @dataProvider buildData
     */
    public function testBuild($type, $container, $dbname, $fixture, $options = [], $expect = null) {

        $drivers = \PDO::getAvailableDrivers();

        if (!in_array($type, $drivers)) {
            $this->markTestSkipped("PDO Driver `$type` not installed");
        }

        if (!empty($container)) {
            if (!$this->startContainer($container)) {
                $this->markTestSkipped("docker not available");
            }
        }

        $db = Factory::build(Factory::load_config(Factory::get_config($dbname), $options));
        $this->assertTrue(
            $db instanceof \PDO,
            "Are you running the docker container? See README."
        );
        $sth = $db->prepare(
            file_get_contents(__DIR__."/fixtures/$fixture")
        );
        $success = $sth->execute();
        $err = $sth->errorInfo();
        if (!empty($err[2])) {
            $message = $err[2];
        } else {
            $message = "";
        }
        $this->assertTrue(
            $success,
            $message
        );

        if (!is_null($expect)) {
            $data = $sth->fetchAll(\PDO::FETCH_ASSOC);
            $this->assertEquals(
                $expect,
                $data
            );
        }
    }

    public function buildData() {
        // $type, $container, $dbname, $fixture
        return [
            [
                "sqlite",
                null,
                "chinook",
                "sqlite/select.sql",
                [],
                [
                    [
                        "count" => 347
                    ]
                ]
            ],
            [
                "pgsql",
                "postgres",
                "pgpdotestdb",
                "pgsql/create_table.sql",
            ],
            [
                "pgsql",
                "postgres",
                "pgtestdb",
                "pgsql/create_table.sql",
            ],
            [
                "mysql",
                "mysql",
                "mypdotestdb",
                "mysql/create_table.sql",
            ],
            [
                "mysql",
                "mysql",
                "mytestdb",
                "mysql/create_table.sql",
            ],
            [
                "mysql",
                "mysql",
                "mytestdb",
                "mysql/show_variables.sql",
                [
                    // 1002 = \PDO::MYSQL_ATTR_INIT_COMMAND
                    1002 => "SET SESSION sql_mode='TRADITIONAL'"
                ],
                [
                    [
                        "Variable_name" => "sql_mode",
                        "Value"         => "STRICT_TRANS_TABLES,STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,TRADITIONAL,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION"
                    ]
                ]
            ],
        ];
    }


    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionCode 1
     */
    public function testNoDSN() {
        Factory::load_config(
            [
                "type"   => "pdo",
                "server" => "127.0.0.1",
                "user"   => "test",
                "pass"   => "test",
            ]
        );
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionCode 2
     */
    public function testBadType() {
        Factory::load_config(
            [
                "type"   => "mssql",
                "server" => "127.0.0.1",
                "port"   => "666",
                "db"     => "NONONO",
                "user"   => "test",
                "pass"   => "test",
            ]
        );
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionCode 3
     */
    public function testNoServer() {
        Factory::load_config(
            [
                "type"   => "mysql",
                "port"   => "00000",
                "db"     => "noserver",
                "user"   => "test",
                "pass"   => "test",
            ]
        );
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionCode 4
     */
    public function testBadOptions() {
        Factory::load_config(
            [
                "type"    => "mysql",
                "port"    => "00000",
                "server"  => "noserver",
                "db"      => "noserver",
                "user"    => "test",
                "pass"    => "test",
                "options" => "['foo':1,2,3]",
            ]
        );
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionCode 5
     */
    public function testNoDB() {
        Factory::load_config(
            [
                "type"    => "mysql",
                "port"    => "00000",
                "server"  => "noserver",
                "user"    => "test",
                "pass"    => "test",
                "options" => null,
            ]
        );
    }

    protected function startContainer($name) {

        $docker_prog = trim(`which docker`);
        if (!empty($docker_prog)) {
            if (isset(self::$containers[$name])) {
                $container = self::$containers[$name];
                $container_name = "dealnews-db-{$container["name"]}-test-instance";
                $running_container = strlen(trim(`docker ps | fgrep {$container["name"]}`)) > 0;
                if (!$running_container) {
                    $has_container = strlen(trim(`docker ps --all | fgrep {$container["name"]}`)) > 0;
                    if (!$has_container) {
                        fwrite(STDERR, "\nRunning $name\n");
                        passthru($container["run"]);
                    } else {
                        fwrite(STDERR, "\nStarting $name\n");
                        passthru("docker start {$container["name"]}");
                    }
                    self::$containers[$name]["started"] = true;
                    // let the container start up
                    sleep(10);
                    register_shutdown_function(["\DealNews\DB\Tests\FactoryTest", "stopContainers"]);
                }
                return true;
            }
        }
        return false;
    }

    public static function stopContainers() {
        foreach (self::$containers as $name => $container) {
            if (!empty($container["started"])) {
                fwrite(STDERR, "Stopping $name\n");
                passthru("docker stop {$container["name"]}");
                self::$containers[$name]["started"] = false;
            }
        }
    }
}
