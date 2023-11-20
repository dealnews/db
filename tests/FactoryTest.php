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
use \DealNews\DB\PDO;

/**
 * @group integration
 */
class FactoryTest extends \PHPUnit\Framework\TestCase {
    use RequireDatabase;

    /**
     * @group unit
     */
    public function testGetConfigEmptyDB() {
        $gc = $this->getMockBuilder('\\DealNews\\GetConfig\\GetConfig')
                   ->setMethods(['get'])
                   ->disableOriginalConstructor()
                   ->getMock();

        // Create a map of arguments to return values.
        $map = [
            ['db.factory.prefix',        null],
            [Factory::DEFAULT_CONFIG_PREFIX . '.test.type',    'type'],
            [Factory::DEFAULT_CONFIG_PREFIX . '.test.db',       null],
            [Factory::DEFAULT_CONFIG_PREFIX . '.test.user',    'user'],
            [Factory::DEFAULT_CONFIG_PREFIX . '.test.pass',    'pass'],
            [Factory::DEFAULT_CONFIG_PREFIX . '.test.dsn',     'dsn'],
            [Factory::DEFAULT_CONFIG_PREFIX . '.test.options', 'options'],
            [Factory::DEFAULT_CONFIG_PREFIX . '.test.server',  'server'],
            [Factory::DEFAULT_CONFIG_PREFIX . '.test.port',    'port'],
            [Factory::DEFAULT_CONFIG_PREFIX . '.test.charset', 'charset'],
        ];

        // Configure the stub.
        $gc->method('get')
             ->will($this->returnValueMap($map));

        $config = Factory::getConfig('test', $gc);
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

    /**
     * @group unit
     */
    public function testGetConfigDefaultPrefix() {
        $gc = $this->getMockBuilder('\\DealNews\\GetConfig\\GetConfig')
                   ->setMethods(['get'])
                   ->disableOriginalConstructor()
                   ->getMock();

        // Create a map of arguments to return values.
        $map = [
            ['db.factory.prefix',        null],
            [Factory::DEFAULT_CONFIG_PREFIX . '.test.type',    'type'],
            [Factory::DEFAULT_CONFIG_PREFIX . '.test.db',      'db'],
            [Factory::DEFAULT_CONFIG_PREFIX . '.test.user',    'user'],
            [Factory::DEFAULT_CONFIG_PREFIX . '.test.pass',    'pass'],
            [Factory::DEFAULT_CONFIG_PREFIX . '.test.dsn',     'dsn'],
            [Factory::DEFAULT_CONFIG_PREFIX . '.test.options', 'options'],
            [Factory::DEFAULT_CONFIG_PREFIX . '.test.server',  'server'],
            [Factory::DEFAULT_CONFIG_PREFIX . '.test.port',    'port'],
            [Factory::DEFAULT_CONFIG_PREFIX . '.test.charset', 'charset'],
        ];

        // Configure the stub.
        $gc->method('get')
             ->will($this->returnValueMap($map));

        $config = Factory::getConfig('test', $gc);
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
     * @group unit
     */
    public function testGetConfigCustomPrefix() {
        $gc = $this->getMockBuilder('\\DealNews\\GetConfig\\GetConfig')
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

        $config = Factory::getConfig('test', $gc);
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
     * @group unit
     * @dataProvider loadConfigData
     */
    public function testLoadConfig($config, $options, $type, $expect) {
        $config = Factory::loadConfig($config, $options, $type);
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
                    'db'          => null,
                    'user'        => 'user',
                    'pass'        => 'pass',
                    'dsn'         => 'dsn',
                    'options'     => null,
                    'server'      => null,
                    'port'        => null,
                    'charset'     => null,
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
                [4 => 4, 5 => 5, 6 => 6],
                null,
                [
                    'type'    => 'pdo',
                    'db'      => null,
                    'user'    => 'user',
                    'pass'    => 'pass',
                    'dsn'     => 'dsn',
                    'options' => [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6],
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
                    'dsn'     => 'mysql:host=test;port=;dbname=test;charset=utf8mb4',
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
                'pgsql',
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
            ],
        ];
    }

    /**
     * @group integration
     */
    public function testInit() {
        $drivers = \PDO::getAvailableDrivers();

        if (!in_array('sqlite', $drivers)) {
            $this->markTestSkipped('PDO SQLite Driver not installed');
        }

        $db1 = Factory::init('chinook');
        $db2 = Factory::init('chinook');

        $this->assertSame($db1, $db2);
    }

    /**
     * @dataProvider buildData
     */
    public function testBuild($type, $dbname, $fixture, $options = [], $expect = null) {
        $drivers = \PDO::getAvailableDrivers();

        if (!in_array($type, $drivers)) {
            $this->markTestSkipped("PDO Driver `$type` not installed");
        }

        $db = Factory::build(Factory::loadConfig(Factory::getConfig($dbname), $options));
        $this->assertTrue(
            $db instanceof PDO,
            'Are you running the docker container? See README.'
        );
        $sth = $db->prepare(
            file_get_contents(__DIR__ . "/fixtures/$fixture")
        );
        $success = $sth->execute();
        $err     = $sth->errorInfo();
        $this->assertTrue(
            $success,
            $err[2] ?? 'Unknown Error'
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
            'chinook' => [
                'sqlite',
                'chinook',
                'sqlite/select.sql',
                [],
                [
                    [
                        'count' => 347,
                    ],
                ],
            ],
            'pgpdotestdb' => [
                'pgsql',
                'pgpdotestdb',
                'pgsql/create_table.sql',
            ],
            'pgtestdb' => [
                'pgsql',
                'pgtestdb',
                'pgsql/create_table.sql',
            ],
            'mypdotestdb' => [
                'mysql',
                'mypdotestdb',
                'mysql/create_table.sql',
            ],
            'mytestdb' => [
                'mysql',
                'mytestdb',
                'mysql/create_table.sql',
            ],
            'mytestdb_show' => [
                'mysql',
                'mytestdb',
                'mysql/show_variables.sql',
                [
                    // 1002 = \PDO::MYSQL_ATTR_INIT_COMMAND
                    1002 => "SET SESSION sql_mode='TRADITIONAL'",
                ],
                [
                    [
                        'Variable_name' => 'sql_mode',
                        'Value'         => 'STRICT_TRANS_TABLES,STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,TRADITIONAL,NO_ENGINE_SUBSTITUTION',
                    ],
                ],
            ],
        ];
    }

    /**
     * @group unit
     */
    public function testNoDSN() {
        $this->expectException('\\UnexpectedValueException');
        $this->expectExceptionCode(1);
        Factory::loadConfig(
            [
                'type'   => 'pdo',
                'server' => '127.0.0.1',
                'user'   => 'test',
                'pass'   => 'test',
            ]
        );
    }

    /**
     * @group unit
     */
    public function testBadType() {
        $this->expectException('\\UnexpectedValueException');
        $this->expectExceptionCode(2);
        Factory::loadConfig(
            [
                'type'   => 'mssql',
                'server' => '127.0.0.1',
                'port'   => '666',
                'db'     => 'NONONO',
                'user'   => 'test',
                'pass'   => 'test',
            ]
        );
    }

    /**
     * @group unit
     */
    public function testNoServer() {
        $this->expectException('\\LogicException');
        $this->expectExceptionCode(3);
        Factory::loadConfig(
            [
                'type'   => 'mysql',
                'port'   => '00000',
                'db'     => 'noserver',
                'user'   => 'test',
                'pass'   => 'test',
            ]
        );
    }

    /**
     * @group unit
     */
    public function testBadOptions() {
        $this->expectException('\\UnexpectedValueException');
        $this->expectExceptionCode(4);
        Factory::loadConfig(
            [
                'type'    => 'mysql',
                'port'    => '00000',
                'server'  => 'noserver',
                'db'      => 'noserver',
                'user'    => 'test',
                'pass'    => 'test',
                'options' => "['foo':1,2,3]",
            ]
        );
    }

    /**
     * @group unit
     */
    public function testNoDB() {
        $this->expectException('\\UnexpectedValueException');
        $this->expectExceptionCode(5);
        Factory::loadConfig(
            [
                'type'    => 'mysql',
                'port'    => '00000',
                'server'  => 'noserver',
                'user'    => 'test',
                'pass'    => 'test',
                'options' => null,
            ]
        );
    }
}
