<?php

namespace DealNews\DB\PDO;

trait CheckErrorCode {

    protected static $error_codes = [
        'mysql' => [
            'retry' => [
                1422, // Explicit or implicit commit is not allowed in stored function or trigger.
                1213, // Deadlock found when trying to get lock; try restarting transaction
                1205, // Lock wait timeout
            ],
            'reconnect_codes' => [
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
    ];

    /**
     * Determines if an error code is one that should be retried
     *
     * @param  integer $code Error code from \PDOException
     *
     * @return bool
     */
    protected function checkErrorCode($code) {
        $retry           = false;
        $retry_codes     = [];
        $reconnect_codes = [];

        if (!empty($this::$error_codes[$this->driver])) {
            $retry_codes     = $this::$error_codes[$this->driver]['retry'];
            $reconnect_codes = $this::$error_codes[$this->driver]['reconnect'];
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
