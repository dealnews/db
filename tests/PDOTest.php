<?php

/**
 * Tests the PDO wrapper class
 *
 * @author      Brian Moon <brianm@dealnews.com>
 * @copyright   1997-Present DealNews.com, Inc
 * @package     DB
 *
 */

namespace DealNews\DB\Tests;

use DealNews\DB\Factory;
use DealNews\DB\PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
class PDOTest extends \PHPUnit\Framework\TestCase {
    use RequireDatabase;

    #[DataProvider('errorCodeData')]
    public function testCheckErrorCode($code, $reconnect, $retry, $driver) {
        $class = new class($driver) extends PDO {
            protected $driver;

            public function __construct($driver) {
                $this->driver  = $driver;
            }

            public $reconnect = false;

            public function connect($reconnect = false, ?string $pdo_class = \PDO::class) {
                $this->reconnect = $reconnect;
            }

            public function callCheckErrorCode($code): bool {
                return $this->checkErrorCode($code);
            }
        };

        $result = $class->callCheckErrorCode($code);

        $this->assertEquals($retry, $result);

        $this->assertEquals($reconnect, $class->reconnect);
    }

    public static function errorCodeData() {
        return [
            'MySQL Retry Only' => [
                1422,
                false,
                true,
                'mysql',
            ],
            'MySQL Reconnect' => [
                1040,
                true,
                true,
                'mysql',
            ],
            'MySQL No Retry' => [
                9999,
                false,
                false,
                'mysql',
            ],
            'Postgres Retry' => [
                '40P01',
                false,
                true,
                'pgsql',
            ],
            'Postgres Reconnect' => [
                'HY000',
                true,
                true,
                'pgsql',
            ],
            'Postgres No Retry' => [
                '00000',
                false,
                false,
                'pgsql',
            ],
        ];
    }

    public function testClose() {
        $config = Factory::loadConfig(Factory::getConfig('mytestdb'));

        MockPDO::$mock_attempt_count = 0;
        MockPDO::$mock_throw         = false;
        $pdo                         = new class($config['dsn'], $config['user'], $config['pass'], $config['options']) extends \DealNews\DB\PDO {
            public function connect($reconnect = false, ?string $pdo_class = \PDO::class) {
                parent::connect($reconnect, MockPDO::class);
            }
        };
        $this->assertTrue($pdo->ping());
        $this->assertEquals(1, MockPDO::$mock_attempt_count);
        $this->assertTrue($pdo->ping());
        $this->assertEquals(1, MockPDO::$mock_attempt_count);

        $pdo->close();
        $this->assertTrue($pdo->ping());
        $this->assertEquals(2, MockPDO::$mock_attempt_count);
    }

    public function testConnect() {
        MockPDO::$mock_attempt_count = 0;
        MockPDO::$mock_throw         = true;
        $pdo                         = new \DealNews\DB\PDO('foo:bar');
        $pdo->connect(true, MockPDO::class);
        $this->assertEquals(2, MockPDO::$mock_attempt_count);
    }

    public function testPing() {
        $db = Factory::init('mytestdb');
        $this->assertTrue($db->ping());
    }

    public function testDebug() {
        $db = Factory::init('mytestdb');
        $this->assertFalse($db->debug(true));
        $this->assertTrue($db->debug(false));
    }
}

class MockPDO extends \PDO {
    public static int $mock_attempt_count = 0;

    public static $mock_throw = true;

    public function __construct(string $dsn, ?string $username = '', ?string $passwd = '', ?array $options = []) {
        self::$mock_attempt_count++;
        if (self::$mock_throw) {
            if (self::$mock_attempt_count <= 1) {
                throw new \PDOException('Test Exception', 0);
            }
        } else {
            parent::__construct($dsn, $username, $passwd, $options);
        }
    }
}
