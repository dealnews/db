<?php

namespace DealNews\DB;

use \DealNews\Metrics\Collector;

/**
 * Wrapper for PDOStatement
 *
 * @author      Brian Moon <brianm@dealnews.com>
 * @copyright   1997-Present DealNews.com, Inc
 * @package     DealNews\DB
 */
class PDOStatement {
    use \DealNews\DB\PDO\CheckErrorCode;

    /**
     * Real \PDOStatement object
     *
     * @var \PDOStatement
     */
    protected $stmt;

    /**
     * PDO object which created the statement
     *
     * @var PDO
     */
    protected $pdo;

    /**
     * Creates the object
     *
     * @param \PDOStatement $stmt           PDOStatement object to wrap
     * @param PDO           $pdo            PDO object used to create the statement
     */
    public function __construct(\PDOStatement $stmt, PDO $pdo) {
        $this->stmt           = $stmt;
        $this->pdo            = $pdo;
    }

    /**
     * Wrapper for \PDOStatement object
     *
     * @param  string $method Method name
     * @param  array  $args   Arguments
     *
     * @return mixed
     */
    public function __call($method, $args = []) {
        return call_user_func_array(
            [$this->stmt, $method],
            $args
        );
    }

    /**
     * Wrapper for \PDOStatement object
     *
     * @param  string $property
     *
     * @return mixed
     */
    public function __get($property) {
        return $this->stmt->$property ?? null;
    }

    /**
     * @see https://www.php.net/manual/en/pdostatement.execute.php
     * @param  array $input_parameters
     * @return bool
     */
    public function execute(?array $input_parameters = []) {
        for ($x = 1; $x <= PDO::RETRY_LIMIT; $x++) {
            try {
                $result = $this->__call(__FUNCTION__, func_get_args());
                if ($result === true) {
                    break;
                }
            } catch (\PDOException $result) {
                // we may get an exception, ignore it
                // and act upon $result
            }

            // if we get here, we didn't get true in $result
            if ($x >= PDO::RETRY_LIMIT || !$this->checkErrorCode($this->stmt->errorCode())) {
                if (isset($result) && is_object($result)) {
                    throw $result;
                }
            }
        }

        return $result;
    }

    /**
     * Calls connect on PDO object
     *
     * @param  boolean $reconnect If true, a new object will be created
     *
     * @return void
     */
    public function connect($reconnect = false) {
        return $this->pdo->connect($reconnect);
    }
}
