<?php

namespace DealNews\DB;

use \DealNews\Metrics\StatsD;

/**
 * Wrapper for \PDO.
 *
 * This not a child class of PDO. For static functions and class constants
 * code should still reference \PDO.
 *
 * @author      Brian Moon <brianm@dealnews.com>
 * @copyright   1997-Present DealNews.com, Inc
 * @package     DealNews\DB
 */
class PDO {
    use \DealNews\DB\PDO\CheckErrorCode;

    /**
     * Number of times to retry after retriable errors
     */
    const RETRY_LIMIT = 3;

    /**
     * Real \PDO instance
     *
     * @var \PDO
     */
    protected $pdo;

    /**
     * PDO Driver
     *
     * @var string
     */
    protected $driver = '';

    /**
     * Database name
     *
     * @var string
     */
    protected $db = '';

    /**
     * Database server address
     *
     * @var string
     */
    protected $server = '';

    /**
     * PDO DSN
     *
     * @var string
     */
    protected $dsn = '';

    /**
     * Database username
     *
     * @var string
     */
    protected $username = '';

    /**
     * Database password
     *
     * @var string
     */
    protected $passwd = '';

    /**
     * PDO options
     *
     * @var array
     */
    protected $options = [];

    /**
     * Determines if debug info is logged
     *
     * @var boolean
     */
    protected static $debug = false;

    /**
     * @see https://www.php.net/manual/en/pdo.construct.php
     */
    public function __construct(string $dsn, ?string $username = '', ?string $passwd = '', ?array $options = []) {
        $this->dsn      = $dsn;
        $this->username = $username;
        $this->passwd   = $passwd;
        $this->options  = $options;

        // this library relies on exceptions to function properly
        $this->options[\PDO::ATTR_ERRMODE] = \PDO::ERRMODE_EXCEPTION;

        // to be able to retry PDOStatement::execute() calls, prepared
        // statements must be emulated in the client and not prepared
        // on the server.
        $this->options[\PDO::ATTR_EMULATE_PREPARES] = true;

        // parse the dsn to get other info out
        list($this->driver, $remainder) = explode(':', $dsn, 2);
        parse_str(str_replace(';', '&', $remainder), $parts);
        if (isset($parts['dbname'])) {
            $this->db = $parts['dbname'];
        }
        if (isset($parts['host'])) {
            $this->server = $parts['host'];
        }
    }

    /**
     * Connects to the database by creating the real \PDO object
     *
     * @param  boolean $reconnect If true, a new object will be created
     *
     * @return void
     */
    public function connect($reconnect = false) {
        if (empty($this->pdo) || $reconnect) {
            for ($x = 1; $x <= $this::RETRY_LIMIT; $x++) {
                try {
                    if (self::$debug) {
                        $start = microtime(true);
                    }
                    $this->pdo = new \PDO($this->dsn, $this->username, $this->passwd, $this->options);
                    return;
                } catch (\PDOException $e) {
                    // add logging for failures
                    if ($x >= $this::RETRY_LIMIT) {
                        throw $e;
                    }
                }
            }
        }
    }

    /**
     * Emulates a ping to the server to see if it's up
     *
     * @return bool
     */
    public function ping() {
        try {
            $statement = $this->query('select 1');
            $result    = true;
        } catch (\PDOException $e) {
            $result = false;
        }

        return $result;
    }

    /**
     * Toggles debug on and off
     *
     * @param  bool   $toggle Set to true to turn on debug
     *
     * @return bool   Previous value
     */
    public static function debug(bool $toggle) {
        $current     = self::$debug;
        self::$debug = $toggle;

        return $current;
    }

    /**
     * Wrapper for \PDO object
     *
     * @param  string $method Method name
     * @param  array  $args   Arguments
     *
     * @return mixed
     */
    public function __call($method, $args = []) {
        $this->connect();
        try {
            return call_user_func_array(
                [$this->pdo, $method],
                $args
            );
        } catch (\PDOException $e) {
            // add logging and stats collection for failures
            throw $e;
        }
    }

    /**
     * @see http://php.net/manual/en/pdo.prepare.php
     * @param  string $statement
     * @param  array  $driver_options
     * @return PDOStatement
     */
    public function prepare(string $statement, ?array $driver_options = []) {
        $stmt = $this->__call(__FUNCTION__, func_get_args());
        if ($stmt instanceof \PDOStatement) {
            $stmt = new PDOStatement($stmt, $this);
        }

        return $stmt;
    }

    /**
     * @see http://php.net/manual/en/pdo.query.php
     * @param  string $statement
     * @return PDOStatement
     */
    public function query(string $statement) {
        for ($x = 1; $x <= $this::RETRY_LIMIT; $x++) {
            try {
                $stmt = $this->__call(__FUNCTION__, func_get_args());
                if ($stmt instanceof \PDOStatement) {
                    $stmt = new PDOStatement($stmt, $this);
                    break;
                }
            } catch (\PDOException $e) {
                if ($x >= $this::RETRY_LIMIT || !$this->checkErrorCode($e->getCode())) {
                    throw $e;
                }
            }
        }

        return $stmt;
    }
}
