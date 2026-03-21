<?php

namespace DealNews\DB\Tests;

use DealNews\DB\CRUD;
use PHPUnit\Framework\Attributes\Group;

/**
 * Exposes protected methods for testing
 */
class CRUDTestable extends CRUD {
    /**
     * Expose fieldToParam for testing
     *
     * @param  string  $field  The field name
     *
     * @return string  Encoded parameter name
     */
    public function fieldToParam(string $field): string {
        return parent::fieldToParam($field);
    }
}

#[Group('integration')]
class CRUDTest extends \PHPUnit\Framework\TestCase {
    use RequireDatabase {
        RequireDatabase::setUp as dbSetup;
    }

    protected $crud;

    public function setUp(): void {
        $this->dbSetup();
        $this->crud = new CRUD(\DealNews\DB\Factory::init('testdb'));
    }

    public function testFactory() {
        $crud = CRUD::factory('testdb');
        $this->assertTrue($crud instanceof CRUD);
    }

    public function testBuildSelectQuery() {
        $query = $this->crud->buildSelectQuery('table');
        $this->assertEquals(
            'SELECT * FROM "table"',
            $query
        );

        $query = $this->crud->buildSelectQuery(
            'table',
            ['foo' => 'bar'],
            100,
            200,
            ['some_col', 'foo'],
            'some_col'
        );
        $this->assertEquals(
            'SELECT "some_col", "foo" FROM "table" WHERE ("foo" = :foo0) ORDER BY "some_col" LIMIT 200, 100',
            $query
        );
    }

    public function testMySQLQuoteString() {
        $db = \DealNews\DB\Factory::init('mytestdb');
        $this->assertTrue(
            $db instanceof \DealNews\DB\PDO,
            'Are you running the docker container? See README.'
        );

        $crud = new CRUD($db);

        $query = $crud->buildSelectQuery('time_dimension', [], 1, 2, ['foo', 'bar'], 'time_key desc, foo, bar');
        $this->assertEquals(
            'SELECT `foo`, `bar` FROM `time_dimension` ORDER BY `time_key` desc, `foo`, `bar` LIMIT 2, 1',
            $query
        );
    }

    public function testPostgresLimit() {
        $db = \DealNews\DB\Factory::init('pgpdotestdb');
        $this->assertTrue(
            $db instanceof \DealNews\DB\PDO,
            'Are you running the docker container? See README.'
        );

        $crud = new CRUD($db);

        $query = $crud->buildSelectQuery('time_dimension', [], 1, 2, ['*'], 'time_key');
        $this->assertEquals(
            'SELECT * FROM "time_dimension" ORDER BY "time_key" LIMIT 1 OFFSET 2',
            $query
        );
    }

    public function testBadInsert() {
        $this->expectException('\\LogicException');
        $result = $this->crud->create(
            'test',
            [
                'name'        => [1],
            ]
        );
    }

    public function testBuildParametersException() {
        $this->expectException('\\LogicException');
        $result = $this->crud->buildParameters(
            [
                [
                    'OR' => [
                        'foo' => 1,
                        'bar' => 2,
                    ],
                    'AND' => [
                        'foo' => 3,
                        'bar' => 4,
                    ],
                ],
            ]
        );
    }

    public function testBuildWhereException() {
        $this->expectException('\\LogicException');
        $result = $this->crud->buildWhereClause(
            [
                'OR' => [
                    [
                        'foo' => 1,
                        'bar' => 2,
                    ],
                ],
                'AND' => [
                    [
                        'foo' => 3,
                        'bar' => 4,
                    ],
                ],
            ]
        );
    }

    public function testBuildUpdateException1() {
        $this->expectException('\\LogicException');
        $this->expectExceptionCode(1);
        $result = $this->crud->buildUpdateClause(
            [
                'foo',
            ]
        );
    }

    public function testBuildUpdateException2() {
        $this->expectException('\\LogicException');
        $this->expectExceptionCode(2);
        $result = $this->crud->buildUpdateClause(
            [
                null => 'foo',
            ]
        );
    }

    public function testBuildParameters() {
        $result = $this->crud->buildParameters(
            [
                'foo' => 1,
                'bar' => 2,
            ]
        );

        $this->assertEquals(
            [
                ':foo0' => 1,
                ':bar0' => 2,
            ],
            $result,
            'Simple field list'
        );

        $result = $this->crud->buildParameters(
            [
                [
                    'foo' => 1,
                    'bar' => 2,
                ],
            ]
        );
        $this->assertEquals(
            [
                ':foo1' => 1,
                ':bar1' => 2,
            ],
            $result,
            'Single depth level'
        );

        $result = $this->crud->buildParameters(
            [
                'OR' => [
                    'foo' => 1,
                    'bar' => 2,
                ],
            ]
        );

        $this->assertEquals(
            [
                ':foo1' => 1,
                ':bar1' => 2,
            ],
            $result,
            'Double depth level with OR'
        );

        $result = $this->crud->buildParameters(
            [
                [
                    'OR' => [
                        'foo' => 1,
                        'bar' => 2,
                    ],
                ],
                [
                    'OR' => [
                        'foo' => 3,
                        'bar' => 4,
                    ],
                ],
            ]
        );

        $this->assertEquals(
            [
                ':foo2' => 1,
                ':bar2' => 2,
                ':foo3' => 3,
                ':bar3' => 4,
            ],
            $result,
            'Double depth level with OR'
        );
    }

    public function testBuildWhereClause() {
        $result = $this->crud->buildWhereClause(
            []
        );
        $this->assertEquals(
            '',
            $result,
            'Empty field list'
        );

        $result = $this->crud->buildWhereClause(
            [
                'foo' => 1,
                'bar' => 2,
            ]
        );
        $this->assertEquals(
            '("foo" = :foo0 AND "bar" = :bar0)',
            $result,
            'Simple field list'
        );

        $result = $this->crud->buildWhereClause(
            [
                [
                    'foo' => 1,
                    'bar' => 2,
                ],
            ]
        );
        $this->assertEquals(
            '(("foo" = :foo1 AND "bar" = :bar1))',
            $result,
            'Single depth level'
        );

        $result = $this->crud->buildWhereClause(
            [
                'OR' => [
                    'foo' => 1,
                    'bar' => 2,
                ],
            ]
        );

        $this->assertEquals(
            '("foo" = :foo1 OR "bar" = :bar1)',
            $result,
            'Double depth level with OR'
        );

        $result = $this->crud->buildWhereClause(
            [
                'OR' => [
                    [
                        'foo' => 1,
                        'bar' => 2,
                    ],
                    [
                        'foo' => 3,
                        'bar' => 4,
                    ],
                ],
            ]
        );

        $this->assertEquals(
            '(("foo" = :foo2 AND "bar" = :bar2) OR ("foo" = :foo3 AND "bar" = :bar3))',
            $result,
            'Triple depth level with OR'
        );
    }

    public function testCreateAndRead() {
        $this->createAndRead();
    }

    public function testUpdate() {
        $row = $this->createAndRead();

        $result = $this->crud->update(
            'test',
            [
                'name' => $row['name'] . ' 2',
            ],
            ['id' => (int)$row['id']]
        );

        $this->assertNotEmpty(
            $result
        );

        $new_rows = $this->crud->read('test', ['id' => (int)$row['id']]);

        $this->assertNotEmpty(
            $new_rows
        );

        $this->assertEquals(
            $row['name'] . ' 2',
            $new_rows[0]['name']
        );
    }

    public function testDelete() {
        $row = $this->createAndRead();

        $result = $this->crud->delete(
            'test',
            ['id' => $row['id']]
        );

        $this->assertNotEmpty(
            $result
        );

        $new_rows = $this->crud->read('test', ['id' => $row['id']]);

        $this->assertEmpty(
            $new_rows
        );
    }

    public function testMultiValueWhere() {
        $names = [];
        for ($x = 1; $x <= 5; $x++) {
            $name    = "Multi Test $x " . microtime(true);
            $names[] = $name;
            $result  = $this->crud->create(
                'test',
                [
                    'name'        => $name,
                    'description' => 'Description',
                ]
            );
            $this->assertNotEmpty(
                $result
            );
        }

        $new_rows = $this->crud->read(
            'test',
            [
                'name' => $names,
            ]
        );

        $this->assertEquals(
            count($names),
            count($new_rows)
        );
    }

    public function testLimit() {
        for ($x = 0; $x < 10; $x++) {
            $name        = "Test $x " . time();
            $description = 'Test Description ' . time();

            $result = $this->crud->create(
                'test',
                [
                    'name'        => $name,
                    'description' => $description,
                ]
            );
        }

        $rows = $this->crud->read('test', [], 5);

        $this->assertEquals(
            5,
            count($rows)
        );

        $other_rows = $this->crud->read('test', [], 5, 5);

        $this->assertEquals(
            5,
            count($other_rows)
        );

        $this->assertNotEquals(
            $rows,
            $other_rows
        );
    }

    /**
     * Tests quoteField with dot-notation (table.field)
     */
    public function testQuoteFieldDotNotation() {
        // Two-part: table.field
        $result = $this->crud->quoteField('users.id');
        $this->assertEquals(
            '"users"."id"',
            $result,
            'table.field dot notation'
        );

        // Three-part: schema.table.field
        $result = $this->crud->quoteField('public.users.id');
        $this->assertEquals(
            '"public"."users"."id"',
            $result,
            'schema.table.field dot notation'
        );

        // Wildcard: table.*
        $result = $this->crud->quoteField('users.*');
        $this->assertEquals(
            '"users".*',
            $result,
            'table.* wildcard notation'
        );

        // Simple field without dot
        $result = $this->crud->quoteField('id');
        $this->assertEquals(
            '"id"',
            $result,
            'simple field name'
        );

        // Plain wildcard
        $result = $this->crud->quoteField('*');
        $this->assertEquals(
            '*',
            $result,
            'plain wildcard'
        );
    }

    /**
     * Tests fieldToParam encoding of special characters
     */
    public function testFieldToParam() {
        // Need testable subclass to access protected method
        $testable = new CRUDTestable($this->crud->pdo);

        // Simple field name unchanged
        $result = $testable->fieldToParam('id');
        $this->assertEquals('id', $result, 'simple field unchanged');

        // Underscore preserved
        $result = $testable->fieldToParam('user_id');
        $this->assertEquals('user_id', $result, 'underscore preserved');

        // Dot encoded as _2e (0x2e = 46 decimal = '.')
        $result = $testable->fieldToParam('users.id');
        $this->assertEquals('users_2eid', $result, 'dot encoded as hex');

        // Multiple dots
        $result = $testable->fieldToParam('schema.table.field');
        $this->assertEquals('schema_2etable_2efield', $result, 'multiple dots encoded');

        // Verify no collision between users.id and users_id
        $dotted     = $testable->fieldToParam('users.id');
        $underscored = $testable->fieldToParam('users_id');
        $this->assertNotEquals(
            $dotted,
            $underscored,
            'users.id and users_id must produce different param names'
        );
    }

    /**
     * Tests buildParameters with dot-notation fields
     */
    public function testBuildParametersDotNotation() {
        $result = $this->crud->buildParameters([
            'users.id' => 1,
        ]);
        $this->assertEquals(
            [':users_2eid0' => 1],
            $result,
            'dotted field in parameters'
        );

        // Multiple dotted fields
        $result = $this->crud->buildParameters([
            'users.id'     => 1,
            'posts.author' => 'John',
        ]);
        $this->assertEquals(
            [
                ':users_2eid0'     => 1,
                ':posts_2eauthor0' => 'John',
            ],
            $result,
            'multiple dotted fields'
        );

        // Mixed dotted and simple fields
        $result = $this->crud->buildParameters([
            'users.id' => 1,
            'name'     => 'test',
        ]);
        $this->assertEquals(
            [
                ':users_2eid0' => 1,
                ':name0'       => 'test',
            ],
            $result,
            'mixed dotted and simple fields'
        );

        // Array values with dotted field
        $result = $this->crud->buildParameters([
            'users.status' => ['active', 'pending'],
        ]);
        $this->assertEquals(
            [
                ':users_2estatus00' => 'active',
                ':users_2estatus10' => 'pending',
            ],
            $result,
            'array values with dotted field'
        );
    }

    /**
     * Tests buildWhereClause with dot-notation fields
     */
    public function testBuildWhereClauseDotNotation() {
        // Single dotted field
        $result = $this->crud->buildWhereClause([
            'users.id' => 1,
        ]);
        $this->assertEquals(
            '("users"."id" = :users_2eid0)',
            $result,
            'dotted field in where clause'
        );

        // Multiple dotted fields
        $result = $this->crud->buildWhereClause([
            'users.id'    => 1,
            'users.name'  => 'John',
        ]);
        $this->assertEquals(
            '("users"."id" = :users_2eid0 AND "users"."name" = :users_2ename0)',
            $result,
            'multiple dotted fields in where clause'
        );

        // OR with dotted fields
        $result = $this->crud->buildWhereClause([
            'OR' => [
                'users.status' => 'active',
                'users.role'   => 'admin',
            ],
        ]);
        $this->assertEquals(
            '("users"."status" = :users_2estatus1 OR "users"."role" = :users_2erole1)',
            $result,
            'OR clause with dotted fields'
        );

        // Array values with dotted field
        $result = $this->crud->buildWhereClause([
            'users.id' => [1, 2, 3],
        ]);
        $this->assertEquals(
            '(("users"."id" = :users_2eid00 OR "users"."id" = :users_2eid10 OR "users"."id" = :users_2eid20))',
            $result,
            'array values with dotted field'
        );
    }

    /**
     * Tests buildUpdateClause with dot-notation fields
     */
    public function testBuildUpdateClauseDotNotation() {
        $result = $this->crud->buildUpdateClause([
            'users.name'   => 'John',
            'users.status' => 'active',
        ]);
        $this->assertEquals(
            '"users"."name" = :users_2ename0, "users"."status" = :users_2estatus0',
            $result,
            'dotted fields in update clause'
        );
    }

    /**
     * Tests buildSelectQuery with dot-notation in fields list
     */
    public function testBuildSelectQueryDotNotation() {
        // Dotted fields in SELECT
        $query = $this->crud->buildSelectQuery(
            'users',
            [],
            null,
            null,
            ['users.id', 'users.name']
        );
        $this->assertEquals(
            'SELECT "users"."id", "users"."name" FROM "users"',
            $query,
            'dotted fields in SELECT list'
        );

        // Wildcard with table prefix
        $query = $this->crud->buildSelectQuery(
            'users',
            [],
            null,
            null,
            ['users.*']
        );
        $this->assertEquals(
            'SELECT "users".* FROM "users"',
            $query,
            'table.* in SELECT list'
        );

        // Dotted WHERE clause
        $query = $this->crud->buildSelectQuery(
            'users',
            ['users.id' => 1],
            null,
            null,
            ['*']
        );
        $this->assertEquals(
            'SELECT * FROM "users" WHERE ("users"."id" = :users_2eid0)',
            $query,
            'dotted field in WHERE'
        );

        // Schema.table in FROM
        $query = $this->crud->buildSelectQuery('public.users');
        $this->assertEquals(
            'SELECT * FROM "public"."users"',
            $query,
            'schema.table in FROM'
        );
    }

    protected function createAndRead() {
        $name        = 'Test ' . time();
        $description = 'Test Description ' . time();
        // if PDO isn't properly binding booleans, true will work, but false will fail
        $active      = false;

        $result = $this->crud->create(
            'test',
            [
                'name'        => $name,
                'description' => $description,
                'active'      => $active,
            ]
        );

        $this->assertEquals(
            true,
            $result
        );

        $id = $this->crud->pdo->lastInsertId();

        $this->assertNotEmpty(
            $result
        );

        $row = $this->crud->read('test', ['id' => $id]);

        $this->assertNotEmpty(
            $row
        );

        $this->assertEquals(
            $name,
            $row[0]['name']
        );

        $this->assertEquals(
            $description,
            $row[0]['description']
        );

        $this->assertEquals(
            0,
            $row[0]['active']
        );

        return $row[0];
    }
}
