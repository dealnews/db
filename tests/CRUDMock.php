<?php

namespace DealNews\DB\Tests;

class CRUDMock extends \DealNews\DB\CRUD {
    use GetStack;

    public $pdo;

    public function __construct(array $stacks) {
        $this->stacks = $stacks;

        $this->pdo = new class($this->stacks['pdo'] ?? []) extends \DealNews\DB\PDO {
            use GetStack;

            public function __construct(array $stacks) {
                $this->stacks = $stacks;
            }

            public function inTransaction() {
                $return = $this->getStack(__FUNCTION__, false);

                return $return;
            }

            public function beginTransaction() {
                $return = $this->getStack(__FUNCTION__, true);

                return $return;
            }

            public function commit() {
                $return = $this->getStack(__FUNCTION__, true);

                return $return;
            }

            public function rollBack() {
                $return = $this->getStack(__FUNCTION__, true);

                return $return;
            }

            public function lastInsertId() {
                $return = $this->getStack(__FUNCTION__, 1);

                return $return;
            }
        };
    }

    public function __get($var) {
        $return = $this->getStack(__FUNCTION__, null);

        return $return;
    }

    public function create(string $table, array $data): bool {
        $return = $this->getStack(__FUNCTION__, true);

        return $return;
    }

    public function read(string $table, array $data = [], int $limit = null, int $start = null, array $fields = ['*'], string $order = ''): array {
        $return = $this->getStack(__FUNCTION__, []);

        return $return;
    }

    public function update(string $table, array $data, array $where): bool {
        $return = $this->getStack(__FUNCTION__, true);

        return $return;
    }

    public function delete(string $table, array $data): bool {
        $return = $this->getStack(__FUNCTION__, true);

        return $return;
    }

    public function run(string $query, array $params = []): \DealNews\DB\PDOStatement {
        $result                = new class($this->stacks['run'] ?? []) extends \DealNews\DB\PDOStatement {
            public $mockResult = [];

            public function __construct(array $stacks) {
                $this->stacks = $stacks;
            }

            public function __call($method, $args = []) {
                $return = $this->getStack($method, null);

                return $return;
            }

            public function __get($property) {
                $return = $this->getStack($method, null);

                return $return;
            }

            public function execute(?array $input_parameters = []) {
                $return = $this->getStack($method, true);

                return $return;
            }

            public function connect($reconnect = false) {
            }
        };

        return $result;
    }

    public function buildParameters(array $fields, int $depth = 0): array {
        $return = $this->getStack(__FUNCTION__, []);

        return $return;
    }

    public function buildWhereClause(array $fields, int $depth = 0): string {
        $return = $this->getStack(__FUNCTION__, []);

        return $return;
    }

    public function buildUpdateClause(array $fields, int $depth = 0): string {
        $return = $this->getStack(__FUNCTION__, '');

        return $return;
    }

    public function buildSelectQuery(string $table, array $data = [], int $limit = null, int $start = null, array $fields = ['*'], string $order = ''): string {
        $return = $this->getStack(__FUNCTION__, '');

        return $return;
    }
}

trait GetStack {
    protected $stacks;

    public $stack_counts = [];

    protected function getStack(string $func, $default) {
        if (empty($this->stack_counts[$func])) {
            $this->stack_counts[$func] = 0;
        }

        $this->stack_counts[$func]++;

        return empty($this->stacks[$func]) ? $default : array_shift($this->stacks[$func]);
    }
}
