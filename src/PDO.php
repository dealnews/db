<?php

namespace DealNews\DB;

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

    protected const ERROR_CODES = [
        'mysql' => [
            'retry' => [
                1422, // Explicit or implicit commit is not allowed in stored function or trigger.
                1213, // Deadlock found when trying to get lock; try restarting transaction
                1205, // Lock wait timeout
            ],
            'reconnect' => [
                1040, // Too many connections
                1129, // Host '%s' is blocked because of many connection errors; unblock with 'mysqladmin flush-hosts'
                1130, // Host '%s' is not allowed to connect to this MySQL server
                1152, // Aborted connection
                1203, // User %s already has more than 'max_user_connections' active connections
                1218, // Error connecting to master: %s
                1429, // Unable to connect to foreign data source: %s
                2002, // Can't connect to local MySQL server through socket '%s' (%d)
                2003, // Can't connect to MySQL server on '%s' (%d)
                2006, // MySQL server has gone away
                2013, // Lost connection to MySQL server during query
                2055, // Lost connection to MySQL server at '%s', system error: %d
            ],
        ],
        'pgsql' => [
            'retry' => [
                '40000', // transaction_rollback
                '40002', // transaction_integrity_constraint_violation
                '40001', // serialization_failure
                '40003', // statement_completion_unknown
                '40P01', // deadlock_detected
            ],
            'reconnect' => [
                'HY000', // SQLSTATE[HY000]: General error: 7 server closed the connection unexpectedly This probably means the server terminated abnormally before or while processing the request.
                '08000', // connection_exception
                '08003', // connection_does_not_exist
                '08006', // connection_failure
                '08001', // sqlclient_unable_to_establish_sqlconnection
                '08004', // sqlserver_rejected_establishment_of_sqlconnection
                '08007', // transaction_resolution_unknown
                '08P01', // protocol_violation
            ],
        ],
    ];

    /**
     * @see https://www.php.net/manual/en/pdo.construct.php
     */
    public function __construct(string $dsn, ?string $username = '', ?string $passwd = '', ?array $options = []) {
        $this->dsn      = $dsn;
        $this->username = $username;
        $this->passwd   = $passwd;
        $this->options  = $options;

        // default the fetch mode to assoc rather than both as that
        // can cause unexpected results as all rows are duplicated
        $this->options[\PDO::ATTR_DEFAULT_FETCH_MODE] = \PDO::FETCH_ASSOC;

        // this library relies on exceptions to function properly
        $this->options[\PDO::ATTR_ERRMODE] = \PDO::ERRMODE_EXCEPTION;

        // to be able to retry PDOStatement::execute() calls, prepared
        // statements must be emulated in the client and not prepared
        // on the server.
        $this->options[\PDO::ATTR_EMULATE_PREPARES] = true;

        if (!array_key_exists(\PDO::ATTR_TIMEOUT, $this->options)) {
            // Set default timeout to 10 seconds
            $this->options[\PDO::ATTR_TIMEOUT] = 10;
        }

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
    public function connect($reconnect = false, ?string $pdo_class = \PDO::class) {
        if (empty($this->pdo) || $reconnect) {
            $this->pdo = null;
            for ($x = 1; $x <= $this::RETRY_LIMIT; $x++) {
                try {
                    $this->pdo = new $pdo_class($this->dsn, $this->username, $this->passwd, $this->options);

                    return;
                } catch (\PDOException $e) {
                    // add logging for failures
                    if ($x >= $this::RETRY_LIMIT) {
                        throw new \PDOException(
                            "Attempted to connect $x times and failed: " . $e->getMessage(),
                            $e->getCode(),
                            $e
                        );
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
            $this->query('select 1');
            $result = true;
        } catch (\PDOException $e) { // @phan-suppress-current-line PhanUnusedVariableCaughtException
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
        for ($x = 1; $x <= $this::RETRY_LIMIT; $x++) {
            try {
                return call_user_func_array(
                    [$this->pdo, $method],
                    $args
                );
            } catch (\PDOException $e) {
                if ($x >= $this::RETRY_LIMIT || !$this->checkErrorCode($e->getCode())) {
                    throw new \PDOException(
                        "Attempted to connect $x times and failed: " . $e->getMessage(),
                        (int)$e->getCode(),
                        $e
                    );
                }
            }
        }
    }

    /**
     * @see http://php.net/manual/en/pdo.prepare.php
     * @param  string $statement
     * @param  ?array $driver_options
     * @return PDOStatement
     * @phan-suppress PhanUnusedPublicNoOverrideMethodParameter
     */
    public function prepare(string $statement, ?array $driver_options = []) {
        $stmt = $this->__call(__FUNCTION__, func_get_args());

        // Convert \PDOStatement to a DealNews\DB\PDOStatement
        if ($stmt instanceof \PDOStatement) {
            $stmt = new PDOStatement($stmt, $this);
        }

        return $stmt;
    }

    /**
     * @see http://php.net/manual/en/pdo.query.php
     * @param  string $statement
     * @return PDOStatement
     * @phan-suppress PhanUnusedPublicNoOverrideMethodParameter, PhanPossiblyUndeclaredVariable
     */
    public function query(string $statement) {
        for ($x = 1; $x <= $this::RETRY_LIMIT; $x++) {
            try {
                $stmt = $this->__call(__FUNCTION__, func_get_args());

                // Convert \PDOStatement to a DealNews\DB\PDOStatement
                if ($stmt instanceof \PDOStatement) {
                    $stmt = new PDOStatement($stmt, $this);
                    break;
                }
            } catch (\PDOException $e) {
                if ($x >= $this::RETRY_LIMIT || !$this->checkErrorCode($e->getCode())) {
                    throw new \PDOException(
                        "Attempted to connect $x times and failed: " . $e->getMessage(),
                        $e->getCode(),
                        $e
                    );
                }
            }
        }

        return $stmt;
    }

    /**
     * Determines if an error code is one that should be retried
     *
     * @param  integer|string $code Error code from \PDOException
     *
     * @return bool
     */
    public function checkErrorCode($code): bool {
        $retry           = false;
        $retry_codes     = [];
        $reconnect_codes = [];

        if (!empty($this::ERROR_CODES[$this->driver])) {
            $retry_codes     = $this::ERROR_CODES[$this->driver]['retry'];
            $reconnect_codes = $this::ERROR_CODES[$this->driver]['reconnect'];
        }

        if (in_array($code, $reconnect_codes)) {
            $this->connect(true);
            $retry = true;
        } elseif (in_array($code, $retry_codes)) {
            $retry = true;
        }

        return $retry;
    }
}
