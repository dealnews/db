<?php

namespace DealNews\DB\Util;

use \DealNews\DB\CRUD;
use \DealNews\DB\PDO;

/**
 * Fluent SQL query builder for complex SELECT queries
 *
 * Complements CRUD for queries requiring JOINs, subqueries, aggregations,
 * and complex WHERE conditions. Returns SQL and params for CRUD to execute.
 *
 * Usage:
 * ```php
 * $query = new Query($crud);
 * $query->select(['u.id', 'u.name', 'COUNT(p.id) AS post_count'])
 *     ->from('users', 'u')
 *     ->leftJoin('posts', 'p', 'p.user_id', '=', 'u.id')
 *     ->where('u.status', '=', 'active')
 *     ->groupBy(['u.id', 'u.name'])
 *     ->having('post_count', '>', 10)
 *     ->orderBy('post_count', 'DESC')
 *     ->limit(20);
 *
 * $rows = $crud->runFetch($query->getSql(), $query->getParams());
 * ```
 *
 * @author      Brian Moon <brianm@dealnews.com>
 * @copyright   1997-Present DealNews.com, Inc
 * @package     DB
 */
class Query {

    /**
     * Error code: No SELECT columns specified
     */
    public const ERR_NO_SELECT = 1;

    /**
     * Error code: No FROM table specified
     */
    public const ERR_NO_FROM = 2;

    /**
     * Error code: Invalid comparison operator
     */
    public const ERR_INVALID_OPERATOR = 3;

    /**
     * Error code: Invalid ORDER BY direction
     */
    public const ERR_INVALID_DIRECTION = 4;

    /**
     * Error code: Invalid JOIN type
     */
    public const ERR_INVALID_JOIN_TYPE = 5;

    /**
     * Valid comparison operators
     */
    public const VALID_OPERATORS = [
        '=',
        '!=',
        '<>',
        '<',
        '>',
        '<=',
        '>=',
        'LIKE',
        'NOT LIKE',
        'IN',
        'NOT IN',
        'IS',
        'IS NOT',
    ];

    /**
     * Valid ORDER BY directions
     */
    public const VALID_DIRECTIONS = ['ASC', 'DESC'];

    /**
     * Valid JOIN types
     */
    public const VALID_JOIN_TYPES = ['INNER', 'LEFT', 'RIGHT', 'FULL'];

    /**
     * Database driver name (mysql, pgsql, sqlite, etc.)
     *
     * @var string
     */
    protected string $driver = '';

    /**
     * Character used to quote column/table names
     *
     * @var string
     */
    protected string $quote_char = '"';

    /**
     * SELECT columns
     *
     * @var array<int, string|Raw>
     */
    protected array $select = [];

    /**
     * FROM table name
     *
     * @var string
     */
    protected string $from = '';

    /**
     * FROM table alias
     *
     * @var string
     */
    protected string $from_alias = '';

    /**
     * JOIN clauses
     *
     * @var array<int, array{type: string, table: string, alias: string,
     *     column1: string, operator: string, column2: string}>
     */
    protected array $joins = [];

    /**
     * WHERE conditions
     *
     * @var array<int, array{type: string, column: string|callable,
     *     operator: ?string, value: mixed, nested: ?array}>
     */
    protected array $wheres = [];

    /**
     * GROUP BY columns
     *
     * @var array<int, string>
     */
    protected array $group_by = [];

    /**
     * HAVING conditions
     *
     * @var array<int, array{type: string, column: string,
     *     operator: string, value: mixed}>
     */
    protected array $havings = [];

    /**
     * ORDER BY clauses
     *
     * @var array<int, array{column: string, direction: string}>
     */
    protected array $order_by = [];

    /**
     * LIMIT value
     *
     * @var ?int
     */
    protected ?int $limit = null;

    /**
     * OFFSET value
     *
     * @var ?int
     */
    protected ?int $offset = null;

    /**
     * Bound parameters
     *
     * @var array<string, mixed>
     */
    protected array $params = [];

    /**
     * Parameter counter for unique naming
     *
     * @var int
     */
    protected int $param_counter = 0;

    /**
     * Creates a new Query builder instance
     *
     * @param CRUD|PDO|string $connection     Database connection or driver name
     * @param string|null      $driver_override Optional driver name override
     */
    public function __construct(CRUD|PDO|string $connection, ?string $driver_override = null) {
        if ($driver_override !== null) {
            $this->driver     = $driver_override;
            $this->quote_char = ($driver_override === 'mysql') ? '`' : '"';
        } elseif (is_string($connection)) {
            $this->driver     = $connection;
            $this->quote_char = ($connection === 'mysql') ? '`' : '"';
        } else {
            $this->detectDriver($connection);
        }
    }

    /**
     * Creates a Raw SQL fragment
     *
     * Use for expressions that should not be quoted or escaped.
     *
     * @param string $value  The raw SQL string
     * @param array  $params Parameters to bind (optional)
     *
     * @return Raw
     */
    public static function raw(string $value, array $params = []): Raw {
        return new Raw($value, $params);
    }

    /**
     * Sets the SELECT columns
     *
     * @param array<int, string|Raw> $columns Column names or Raw expressions
     *
     * @return self
     */
    public function select(array $columns): self {
        $this->select = $columns;

        return $this;
    }

    /**
     * Sets the FROM table
     *
     * @param string      $table Table name
     * @param string|null $alias Optional table alias
     *
     * @return self
     */
    public function from(string $table, ?string $alias = null): self {
        $this->from       = $table;
        $this->from_alias = $alias ?? '';

        return $this;
    }

    /**
     * Adds a JOIN clause
     *
     * @param string $table    Table to join
     * @param string $alias    Table alias
     * @param string $column1  First column (from joined table)
     * @param string $operator Comparison operator
     * @param string $column2  Second column (from existing table)
     * @param string $type     JOIN type (INNER, LEFT, RIGHT, FULL)
     *
     * @return self
     *
     * @throws \LogicException If invalid JOIN type
     */
    public function join(
        string $table,
        string $alias,
        string $column1,
        string $operator,
        string $column2,
        string $type = 'INNER'
    ): self {
        $type = strtoupper($type);

        if (!in_array($type, self::VALID_JOIN_TYPES, true)) {
            throw new \LogicException(
                "Invalid JOIN type: $type",
                self::ERR_INVALID_JOIN_TYPE
            );
        }

        $this->joins[] = [
            'type'     => $type,
            'table'    => $table,
            'alias'    => $alias,
            'column1'  => $column1,
            'operator' => $operator,
            'column2'  => $column2,
        ];

        return $this;
    }

    /**
     * Adds an INNER JOIN clause
     *
     * @param string $table    Table to join
     * @param string $alias    Table alias
     * @param string $column1  First column
     * @param string $operator Comparison operator
     * @param string $column2  Second column
     *
     * @return self
     */
    public function innerJoin(
        string $table,
        string $alias,
        string $column1,
        string $operator,
        string $column2
    ): self {
        return $this->join($table, $alias, $column1, $operator, $column2, 'INNER');
    }

    /**
     * Adds a LEFT JOIN clause
     *
     * @param string $table    Table to join
     * @param string $alias    Table alias
     * @param string $column1  First column
     * @param string $operator Comparison operator
     * @param string $column2  Second column
     *
     * @return self
     */
    public function leftJoin(
        string $table,
        string $alias,
        string $column1,
        string $operator,
        string $column2
    ): self {
        return $this->join($table, $alias, $column1, $operator, $column2, 'LEFT');
    }

    /**
     * Adds a RIGHT JOIN clause
     *
     * @param string $table    Table to join
     * @param string $alias    Table alias
     * @param string $column1  First column
     * @param string $operator Comparison operator
     * @param string $column2  Second column
     *
     * @return self
     */
    public function rightJoin(
        string $table,
        string $alias,
        string $column1,
        string $operator,
        string $column2
    ): self {
        return $this->join($table, $alias, $column1, $operator, $column2, 'RIGHT');
    }

    /**
     * Adds a WHERE condition (AND)
     *
     * @param string|callable $column   Column name or callable for nested
     * @param string|null     $operator Comparison operator (if column is string)
     * @param mixed           $value    Value to compare (if column is string)
     *
     * @return self
     *
     * @throws \LogicException If invalid operator
     */
    public function where(
        string|callable $column,
        ?string $operator = null,
        mixed $value = null
    ): self {
        return $this->addWhere('AND', $column, $operator, $value);
    }

    /**
     * Adds a WHERE condition (OR)
     *
     * @param string|callable $column   Column name or callable for nested
     * @param string|null     $operator Comparison operator (if column is string)
     * @param mixed           $value    Value to compare (if column is string)
     *
     * @return self
     *
     * @throws \LogicException If invalid operator
     */
    public function orWhere(
        string|callable $column,
        ?string $operator = null,
        mixed $value = null
    ): self {
        return $this->addWhere('OR', $column, $operator, $value);
    }

    /**
     * Adds a WHERE IN condition
     *
     * @param string $column Column name
     * @param array  $values Values to match
     *
     * @return self
     */
    public function whereIn(string $column, array $values): self {
        return $this->addWhere('AND', $column, 'IN', $values);
    }

    /**
     * Adds a WHERE NOT IN condition
     *
     * @param string $column Column name
     * @param array  $values Values to exclude
     *
     * @return self
     */
    public function whereNotIn(string $column, array $values): self {
        return $this->addWhere('AND', $column, 'NOT IN', $values);
    }

    /**
     * Adds a WHERE IS NULL condition
     *
     * @param string $column Column name
     *
     * @return self
     */
    public function whereNull(string $column): self {
        return $this->addWhere('AND', $column, 'IS', null);
    }

    /**
     * Adds a WHERE IS NOT NULL condition
     *
     * @param string $column Column name
     *
     * @return self
     */
    public function whereNotNull(string $column): self {
        return $this->addWhere('AND', $column, 'IS NOT', null);
    }

    /**
     * Adds a raw WHERE condition
     *
     * @param string $sql    Raw SQL condition
     * @param array  $params Parameters to bind
     *
     * @return self
     */
    public function whereRaw(string $sql, array $params = []): self {
        $raw = new Raw($sql, $params);

        $this->wheres[] = [
            'type'     => 'AND',
            'column'   => $raw,
            'operator' => null,
            'value'    => null,
            'nested'   => null,
        ];

        return $this;
    }

    /**
     * Sets the GROUP BY columns
     *
     * @param array<int, string> $columns Column names
     *
     * @return self
     */
    public function groupBy(array $columns): self {
        $this->group_by = $columns;

        return $this;
    }

    /**
     * Adds a HAVING condition (AND)
     *
     * @param string $column   Column name or aggregate
     * @param string $operator Comparison operator
     * @param mixed  $value    Value to compare
     *
     * @return self
     *
     * @throws \LogicException If invalid operator
     */
    public function having(string $column, string $operator, mixed $value): self {
        return $this->addHaving('AND', $column, $operator, $value);
    }

    /**
     * Adds a HAVING condition (OR)
     *
     * @param string $column   Column name or aggregate
     * @param string $operator Comparison operator
     * @param mixed  $value    Value to compare
     *
     * @return self
     *
     * @throws \LogicException If invalid operator
     */
    public function orHaving(string $column, string $operator, mixed $value): self {
        return $this->addHaving('OR', $column, $operator, $value);
    }

    /**
     * Adds an ORDER BY clause
     *
     * @param string $column    Column name
     * @param string $direction Sort direction (ASC or DESC)
     *
     * @return self
     *
     * @throws \LogicException If invalid direction
     */
    public function orderBy(string $column, string $direction = 'ASC'): self {
        $direction = strtoupper($direction);

        if (!in_array($direction, self::VALID_DIRECTIONS, true)) {
            throw new \LogicException(
                "Invalid ORDER BY direction: $direction",
                self::ERR_INVALID_DIRECTION
            );
        }

        $this->order_by[] = [
            'column'    => $column,
            'direction' => $direction,
        ];

        return $this;
    }

    /**
     * Sets the LIMIT value
     *
     * @param int $limit Maximum rows to return
     *
     * @return self
     */
    public function limit(int $limit): self {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Sets the OFFSET value
     *
     * @param int $offset Number of rows to skip
     *
     * @return self
     */
    public function offset(int $offset): self {
        $this->offset = $offset;

        return $this;
    }

    /**
     * Builds and returns the SQL query string
     *
     * @return string The complete SQL query
     *
     * @throws \LogicException If required parts are missing
     */
    public function getSql(): string {
        // Reset params for fresh build
        $this->params        = [];
        $this->param_counter = 0;

        if (empty($this->select)) {
            throw new \LogicException(
                'No SELECT columns specified',
                self::ERR_NO_SELECT
            );
        }

        if (empty($this->from)) {
            throw new \LogicException(
                'No FROM table specified',
                self::ERR_NO_FROM
            );
        }

        $sql = $this->buildSelect();
        $sql .= $this->buildFrom();
        $sql .= $this->buildJoins();
        $sql .= $this->buildWhere();
        $sql .= $this->buildGroupBy();
        $sql .= $this->buildHaving();
        $sql .= $this->buildOrderBy();
        $sql .= $this->buildLimitOffset();

        return $sql;
    }

    /**
     * Returns the bound parameters for the query
     *
     * Call this after getSql() to get the parameter array.
     *
     * @return array<string, mixed>
     */
    public function getParams(): array {
        return $this->params;
    }

    /**
     * Detects the database driver from the connection
     *
     * @param CRUD|PDO $connection Database connection
     *
     * @return void
     */
    protected function detectDriver(CRUD|PDO $connection): void {
        if ($connection instanceof CRUD) {
            $pdo = $connection->pdo;
        } else {
            $pdo = $connection;
        }

        $this->driver     = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $this->quote_char = ($this->driver === 'mysql') ? '`' : '"';
    }

    /**
     * Adds a WHERE condition
     *
     * @param string          $type     AND or OR
     * @param string|callable $column   Column or callable for nested
     * @param string|null     $operator Comparison operator
     * @param mixed           $value    Value to compare
     *
     * @return self
     *
     * @throws \LogicException If invalid operator
     */
    protected function addWhere(
        string $type,
        string|callable $column,
        ?string $operator,
        mixed $value
    ): self {

        if (is_callable($column)) {
            // Nested conditions via callable
            $nested_query = new self($this->driver);
            $column($nested_query);
            $nested = $nested_query->wheres;

            $this->wheres[] = [
                'type'     => $type,
                'column'   => null,
                'operator' => null,
                'value'    => null,
                'nested'   => $nested,
            ];
        } else {
            if ($operator !== null) {
                $operator = strtoupper($operator);

                if (!in_array($operator, self::VALID_OPERATORS, true)) {
                    throw new \LogicException(
                        "Invalid operator: $operator",
                        self::ERR_INVALID_OPERATOR
                    );
                }
            }

            $this->wheres[] = [
                'type'     => $type,
                'column'   => $column,
                'operator' => $operator,
                'value'    => $value,
                'nested'   => null,
            ];
        }

        return $this;
    }

    /**
     * Adds a HAVING condition
     *
     * @param string $type     AND or OR
     * @param string $column   Column or aggregate
     * @param string $operator Comparison operator
     * @param mixed  $value    Value to compare
     *
     * @return self
     *
     * @throws \LogicException If invalid operator
     */
    protected function addHaving(
        string $type,
        string $column,
        string $operator,
        mixed $value
    ): self {
        $operator = strtoupper($operator);

        if (!in_array($operator, self::VALID_OPERATORS, true)) {
            throw new \LogicException(
                "Invalid operator: $operator",
                self::ERR_INVALID_OPERATOR
            );
        }

        $this->havings[] = [
            'type'     => $type,
            'column'   => $column,
            'operator' => $operator,
            'value'    => $value,
        ];

        return $this;
    }

    /**
     * Adds a parameter and returns the placeholder name
     *
     * @param mixed $value The value to bind
     *
     * @return string The placeholder name (e.g., :qb_param_0)
     */
    protected function addParam(mixed $value): string {
        $param_name                 = ':qb_param_' . $this->param_counter++;
        $this->params[$param_name]  = $value;

        return $param_name;
    }

    /**
     * Quotes a column or table name
     *
     * @param string $name The name to quote
     *
     * @return string The quoted name
     */
    protected function quoteIdentifier(string $name): string {
        // Handle qualified names (e.g., "table.column")
        if (strpos($name, '.') !== false) {
            $parts = explode('.', $name, 2);

            return $this->quote_char . $parts[0] . $this->quote_char .
                   '.' .
                   $this->quote_char . $parts[1] . $this->quote_char;
        }

        return $this->quote_char . $name . $this->quote_char;
    }

    /**
     * Builds the SELECT clause
     *
     * @return string
     */
    protected function buildSelect(): string {
        $columns = [];

        foreach ($this->select as $column) {
            if ($column instanceof Raw) {
                $columns[] = $column->value;
                foreach ($column->params as $key => $value) {
                    $this->params[$key] = $value;
                }
            } elseif ($column instanceof self) {
                // Subquery in SELECT
                $columns[] = '(' . $column->getSql() . ')';
                foreach ($column->getParams() as $key => $value) {
                    $this->params[$key] = $value;
                }
            } elseif ($column === '*') {
                $columns[] = '*';
            } elseif (stripos($column, ' AS ') !== false) {
                // Handle "column AS alias" syntax
                $parts     = preg_split('/\\s+AS\\s+/i', $column, 2);
                $columns[] = $this->quoteIdentifier($parts[0]) .
                             ' AS ' .
                             $this->quoteIdentifier($parts[1]);
            } else {
                $columns[] = $this->quoteIdentifier($column);
            }
        }

        return 'SELECT ' . implode(', ', $columns);
    }

    /**
     * Builds the FROM clause
     *
     * @return string
     */
    protected function buildFrom(): string {
        $sql = ' FROM ' . $this->quoteIdentifier($this->from);

        if (!empty($this->from_alias)) {
            $sql .= ' ' . $this->quoteIdentifier($this->from_alias);
        }

        return $sql;
    }

    /**
     * Builds the JOIN clauses
     *
     * @return string
     */
    protected function buildJoins(): string {
        $sql = '';

        foreach ($this->joins as $join) {
            $sql .= ' ' . $join['type'] . ' JOIN ';
            $sql .= $this->quoteIdentifier($join['table']);
            $sql .= ' ' . $this->quoteIdentifier($join['alias']);
            $sql .= ' ON ' . $this->quoteIdentifier($join['column1']);
            $sql .= ' ' . $join['operator'] . ' ';
            $sql .= $this->quoteIdentifier($join['column2']);
        }

        return $sql;
    }

    /**
     * Builds the WHERE clause
     *
     * @return string
     */
    protected function buildWhere(): string {
        $sql = '';

        if (!empty($this->wheres)) {
            $sql = ' WHERE ' . $this->buildWhereConditions($this->wheres);
        }

        return $sql;
    }

    /**
     * Builds WHERE conditions recursively
     *
     * @param array $conditions The conditions to build
     *
     * @return string
     */
    protected function buildWhereConditions(array $conditions): string {
        $clauses = [];

        foreach ($conditions as $index => $condition) {
            if ($condition['nested'] !== null) {
                // Nested conditions
                $clause = '(' . $this->buildWhereConditions($condition['nested']) . ')';
            } elseif ($condition['column'] instanceof Raw) {
                // Raw SQL
                $raw    = $condition['column'];
                $clause = $raw->value;
                foreach ($raw->params as $key => $value) {
                    $this->params[$key] = $value;
                }
            } else {
                // Regular condition
                $clause = $this->buildCondition(
                    $condition['column'],
                    $condition['operator'],
                    $condition['value']
                );
            }

            if ($index === 0) {
                $clauses[] = $clause;
            } else {
                $clauses[] = $condition['type'] . ' ' . $clause;
            }
        }

        return implode(' ', $clauses);
    }

    /**
     * Builds a single condition
     *
     * @param string      $column   Column name
     * @param string|null $operator Operator
     * @param mixed       $value    Value
     *
     * @return string
     */
    protected function buildCondition(
        string $column,
        ?string $operator,
        mixed $value
    ): string {
        $quoted_column = $this->quoteIdentifier($column);

        if ($operator === 'IS' || $operator === 'IS NOT') {
            return $quoted_column . ' ' . $operator . ' NULL';
        }

        if ($operator === 'IN' || $operator === 'NOT IN') {
            $placeholders = [];
            foreach ($value as $v) {
                $placeholders[] = $this->addParam($v);
            }

            return $quoted_column . ' ' . $operator .
                   ' (' . implode(', ', $placeholders) . ')';
        }

        $placeholder = $this->addParam($value);

        return $quoted_column . ' ' . $operator . ' ' . $placeholder;
    }

    /**
     * Builds the GROUP BY clause
     *
     * @return string
     */
    protected function buildGroupBy(): string {
        $sql = '';

        if (!empty($this->group_by)) {
            $columns = [];
            foreach ($this->group_by as $column) {
                $columns[] = $this->quoteIdentifier($column);
            }
            $sql = ' GROUP BY ' . implode(', ', $columns);
        }

        return $sql;
    }

    /**
     * Builds the HAVING clause
     *
     * @return string
     */
    protected function buildHaving(): string {
        $sql = '';

        if (!empty($this->havings)) {
            $clauses = [];

            foreach ($this->havings as $index => $having) {
                $placeholder = $this->addParam($having['value']);
                $clause      = $having['column'] . ' ' .
                               $having['operator'] . ' ' .
                               $placeholder;

                if ($index === 0) {
                    $clauses[] = $clause;
                } else {
                    $clauses[] = $having['type'] . ' ' . $clause;
                }
            }

            $sql = ' HAVING ' . implode(' ', $clauses);
        }

        return $sql;
    }

    /**
     * Builds the ORDER BY clause
     *
     * @return string
     */
    protected function buildOrderBy(): string {
        $sql = '';

        if (!empty($this->order_by)) {
            $clauses = [];
            foreach ($this->order_by as $order) {
                $clauses[] = $this->quoteIdentifier($order['column']) .
                             ' ' . $order['direction'];
            }
            $sql = ' ORDER BY ' . implode(', ', $clauses);
        }

        return $sql;
    }

    /**
     * Builds the LIMIT/OFFSET clause
     *
     * @return string
     */
    protected function buildLimitOffset(): string {
        $sql = '';

        if ($this->limit !== null) {
            if ($this->driver === 'pgsql') {
                $sql = ' LIMIT ' . $this->limit;
                if ($this->offset !== null) {
                    $sql .= ' OFFSET ' . $this->offset;
                }
            } else {
                // MySQL style
                $sql = ' LIMIT';
                if ($this->offset !== null) {
                    $sql .= ' ' . $this->offset . ',';
                }
                $sql .= ' ' . $this->limit;
            }
        }

        return $sql;
    }
}
