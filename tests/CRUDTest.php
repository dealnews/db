<?php

namespace DealNews\DB\Tests;

use DealNews\DB\CRUD;
use PHPUnit\Framework\Attributes\Group;

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

    public function testBadGetter() {
        $this->expectException('\\LogicException');
        $result = $this->crud->foo;
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
