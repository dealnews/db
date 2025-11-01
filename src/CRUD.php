<?php

namespace DealNews\DB;

/**
 * Helper for doing CRUD operations with PDO
 *
 * This class provides helper functionality for working with PDO objects.
 *
 * @author      Brian Moon <brianm@dealnews.com>
 * @copyright   1997-Present DealNews.com, Inc
 * @package     DB
 */
class CRUD {

    /**
     * PDO Object
     * @var \DealNews\DB\PDO
     */
    protected $pdo;

    /**
     * Character used to quote column names in queries
     *
     * @var        string
     */
    protected $quote_column_char = '"';

    /**
     * Helper factory for creating singletons using only a db name
     *
     * @param      string      $db_name  The database name
     *
     * @return     CRUD
     */
    public static function factory(string $db_name): CRUD {
        static $instances = [];

        if (empty($instances[$db_name])) {
            $instances[$db_name] = new self(Factory::init($db_name));
        }

        return $instances[$db_name];
    }

    /**
     * Creates a new CRUD object
     *
     * @param \DealNews\DB\PDO $pdo PDO object
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        switch ($driver) {
            case 'mysql':
                $this->quote_column_char = '`';
                break;
        }
    }

    /**
     * Getter for getting the pdo object
     *
     * @param  string $var Property name. Only `pdo` is allowed.
     * @return \DealNews\DB\PDO
     */
    public function __get($var) {
        if ($var == 'pdo') {
            return $this->pdo;
        } else {
            throw new \LogicException("Invalid property $var for " . get_class($this));
        }
    }

    /**
     * Inserts a new row into a table
     *
     * @param  string $table Table name
     * @param  array  $data  Array of fields and their values to insert
     * @return bool
     * @throws \PDOException
     */
    public function create(string $table, array $data): bool {
        $params = [];

        foreach ($data as $key => $value) {
            if (!is_scalar($value) && !is_null($value)) {
                throw new \LogicException("Invalid insert value for $key");
            }
            $params[":$key"] = $value;
        }

        $keys = array_keys($data);

        $cols = [];

        foreach ($keys as $key) {
            $cols[] = $this->quoteField($key);
        }

        $this->run(
            'INSERT INTO ' . $this->quoteField($table) . '
                    (' . implode(', ', $cols) . ')
                    VALUES
                    (:' . implode(', :', $keys) . ')',
            $params
        );

        return true;
    }

    /**
     * Reads row from a table
     *
     * @param      string  $table   The table name
     * @param      array   $data    Fields and values to use in the where clause
     * @param      ?int    $limit   Number of rows to return
     * @param      ?int    $start   Row to start at
     * @param      array   $fields  List of fields to return
     * @param      string  $order   Order by clause
     *
     * @return     array
     * @throws \PDOException
     */
    public function read(string $table, array $data = [], ?int $limit = null, ?int $start = null, array $fields = ['*'], string $order = ''): array {
        $query = $this->buildSelectQuery($table, $data, $limit, $start, $fields, $order);

        return $this->runFetch(
            $query,
            $this->buildParameters($data)
        );
    }

    /**
     * Updates rows in a table
     *
     * @param  string $table Table name
     * @param  array  $data  Fields and values to update
     * @param  array  $where Fields and values to use in the where clause
     * @return bool
     * @throws \PDOException
     */
    public function update(string $table, array $data, array $where): bool {
        $this->run(
            'UPDATE ' . $this->quoteField($table) . ' SET ' .
            $this->buildUpdateClause($data) . ' ' .
            'WHERE ' . $this->buildWhereClause($where, 1),
            array_merge(
                $this->buildParameters($data),
                $this->buildParameters($where, 1)
            )
        );

        return true;
    }

    /**
     * Deletes rows from a table
     * @param  string $table Table name
     * @param  array  $data  Fields and values to use in the where clause
     * @return bool
     * @throws \PDOException
     */
    public function delete(string $table, array $data): bool {
        $this->run(
            'DELETE FROM ' . $this->quoteField($table) . ' WHERE ' . $this->buildWhereClause($data),
            $this->buildParameters($data)
        );

        return true;
    }

    /**
     * Prepares a query, executes it and returns the \PDOStatement
     * @param  string $query  Query to execute
     * @param  array  $params List of fields and values to bind to the query
     * @return PDOStatement
     * @throws \PDOException
     */
    public function run(string $query, array $params = []): PDOStatement {
        $sth = $this->pdo->prepare($query);
        foreach ($params as $key => $value) {
            $sth->bindValue($key, $value, (is_bool($value) ? \PDO::PARAM_BOOL : \PDO::PARAM_STR));
        }
        $sth->execute();

        return $sth;
    }

    /**
     * Prepares a query, executes it and fetches all rows
     * @param  string $query  Query to execute
     * @param  array  $params List of fields and values to bind to the query
     * @return array
     * @throws \PDOException
     */
    public function runFetch(string $query, array $params = []): array {
        $rows = [];

        $sth = $this->run(
            $query,
            $params
        );

        if ($sth) {
            $rows = $sth->fetchAll(\PDO::FETCH_ASSOC);
            if (empty($rows)) {
                $rows = [];
            }
        }

        return $rows;
    }

    /**
     * Builds a parameter list
     *
     * @param  array       $fields Array of fields and values
     * @param  int|integer $depth  Depth passed along during recursion
     * @return array
     */
    public function buildParameters(array $fields, int $depth = 0): array {
        $parameters = [];
        foreach ($fields as $field => $value) {
            if (!is_numeric($field) && $field != 'OR' && $field != 'AND') {
                if (is_array($value)) {
                    foreach ($value as $key => $val) {
                        $parameters[":{$field}{$key}{$depth}"] = $val;
                    }
                } else {
                    $parameters[":{$field}{$depth}"] = $value;
                }
            } elseif (is_array($value)) {
                if (count($fields) > 1 && ($field === 'OR' || $field === 'AND')) {
                    throw new \LogicException('Only one value allowed when AND/OR specified');
                }
                $depth++;
                $parameters = array_merge(
                    $parameters,
                    $this->buildParameters($value, $depth)
                );
            }
        }

        return $parameters;
    }

    /**
     * Builds a field list for a prepared statement and array of parameters
     * to bind to the query.
     *
     * @param  array  $fields  Array of fields and values
     * @param  int    $depth   Tracks depth of recursive calls
     * @return string
     */
    public function buildWhereClause(array $fields, int $depth = 0): string {
        $conjunction = 'AND';
        $clauses     = [];

        if (isset($fields['OR']) || isset($fields['AND'])) {
            if (count($fields) > 1) {
                throw new \LogicException('Only one value allowed when AND/OR specified');
            }
            $conjunction = key($fields);
            $fields      = current($fields);
            $depth++;
        }

        foreach ($fields as $field => $value) {
            if (!is_numeric($field)) {
                if (is_scalar($value)) {
                    $clauses[] = $this->quoteField($field) . " = :{$field}{$depth}";
                } elseif (is_array($value)) {
                    $field_clauses = [];
                    foreach ($value as $key => $val) {
                        $field_clauses[] = $this->quoteField($field) . " = :{$field}{$key}{$depth}";
                    }
                    if (count($field_clauses) > 1) {
                        $clauses[] = '(' . implode(' OR ', $field_clauses) . ')';
                    } else {
                        $clauses[] = reset($field_clauses);
                    }
                } else {
                    throw new \InvalidArgumentException('Invalid field value ' . gettype($value), 1);
                }
            } elseif (is_array($value)) {
                $depth++;
                $clauses[] = $this->buildWhereClause($value, $depth);
            }
        }

        $where = '';
        if (!empty($clauses)) {
            $where = '(' . implode(" $conjunction ", $clauses) . ')';
        }

        return $where;
    }

    /**
     * Builds and update clause from an array of fields
     *
     * @param  array  $fields      Array of fields and values
     * @return string
     */
    public function buildUpdateClause(array $fields, int $depth = 0): string {
        $clauses = [];
        foreach ($fields as $field => $value) {
            if (is_numeric($field)) {
                throw new \LogicException("Invalid field name $field for update clause.", 1);
            }
            if (empty($field)) {
                throw new \LogicException("Invalid value for $field in update.", 2);
            }
            $clauses[] = $this->quoteField($field) . " = :{$field}{$depth}";
        }

        return implode(', ', $clauses);
    }

    /**
     * Builds a select query.
     *
     * @param      string  $table   The table
     * @param      array   $data    The data
     * @param      ?int    $limit   The limit
     * @param      ?int    $start   The start
     * @param      array   $fields  The fields
     * @param      string  $order   The order
     *
     * @return     string  The select query.
     */
    public function buildSelectQuery(string $table, array $data = [], ?int $limit = null, ?int $start = null, array $fields = ['*'], string $order = ''): string {
        if ($fields != ['*']) {
            foreach ($fields as $key => $field) {
                $fields[$key] = $this->quoteField($field);
            }
        }

        $query = 'SELECT ' . implode(', ', $fields) . ' FROM ' . $this->quoteField($table);

        if (!empty($data)) {
            $query .= ' WHERE ' . $this->buildWhereClause($data);
        }

        if (!empty($order)) {
            if (strpos($order, ',') !== false || strpos($order, ' ') !== false) {
                $new_order = [];
                $cols      = explode(',', $order);

                foreach ($cols as $col) {
                    $col = trim($col);
                    if (strpos($col, ' ') !== false) {
                        $parts       = explode(' ', $col);
                        $new_order[] = $this->quoteField($parts[0]) . ' ' . $parts[1];
                    } else {
                        $new_order[] = $this->quoteField($col);
                    }
                }
                $order = implode(', ', $new_order);
            } else {
                $order = $this->quoteField($order);
            }
            $query .= ' ORDER BY ' . $order;
        }

        if (!empty($limit)) {
            if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) == 'pgsql') {
                $query .= ' LIMIT ' . ((int)$limit);
                if (!empty($start)) {
                    $query .= ' OFFSET ' . ((int)$start);
                }
            } else {
                $query .= ' LIMIT';
                if (!empty($start)) {
                    $query .= ' ' . ((int)$start) . ',';
                }
                $query .= ' ' . ((int)$limit);
            }
        }

        return $query;
    }

    /**
     * Quotes a field name using the correct quote character
     *
     * @param      string  $field  The field
     *
     * @return     string
     */
    protected function quoteField(string $field): string {
        return $this->quote_column_char . $field . $this->quote_column_char;
    }
}
