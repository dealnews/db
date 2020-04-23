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

use \DealNews\DB\Factory;

class PDOTest extends \PHPUnit\Framework\TestCase {
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
