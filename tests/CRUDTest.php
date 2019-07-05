<?php

namespace DealNews\DB\Tests;

use \DealNews\DB\CRUD;

class CRUDTest extends \PHPUnit\Framework\TestCase {

    protected $crud;

    public function setUp() {
        $this->crud = new CRUD(\DealNews\DB\Factory::init("testdb"));
    }

    public function testBuildParameters() {

        $result = $this->crud->build_parameters(
            [
                "foo" => 1,
                "bar" => 2
            ]
        );

        $this->assertEquals(
            [
                ":foo0" => 1,
                ":bar0" => 2
            ],
            $result,
            "Simple field list"
        );

        $result = $this->crud->build_parameters(
            [
                [
                    "foo" => 1,
                    "bar" => 2
                ]
            ]
        );
        $this->assertEquals(
            [
                ":foo1" => 1,
                ":bar1" => 2
            ],
            $result,
            "Single depth level"
        );

        $result = $this->crud->build_parameters(
            [
                "OR" => [
                    "foo" => 1,
                    "bar" => 2
                ]
            ]
        );

        $this->assertEquals(
            [
                ":foo1" => 1,
                ":bar1" => 2
            ],
            $result,
            "Double depth level with OR"
        );

        $result = $this->crud->build_parameters(
            [
                [
                    "OR" => [
                        "foo" => 1,
                        "bar" => 2
                    ]
                ],
                [
                    "OR" => [
                        "foo" => 3,
                        "bar" => 4
                    ]
                ],
            ]
        );

        $this->assertEquals(
            [
                ":foo2" => 1,
                ":bar2" => 2,
                ":foo3" => 3,
                ":bar3" => 4
            ],
            $result,
            "Double depth level with OR"
        );
    }

    public function testBuildWhereClause() {

        $result = $this->crud->build_where_clause(
            []
        );
        $this->assertEquals(
            "",
            $result,
            "Empty field list"
        );

        $result = $this->crud->build_where_clause(
            [
                "foo" => 1,
                "bar" => 2
            ]
        );
        $this->assertEquals(
            "(foo = :foo0 AND bar = :bar0)",
            $result,
            "Simple field list"
        );

        $result = $this->crud->build_where_clause(
            [
                [
                    "foo" => 1,
                    "bar" => 2
                ]
            ]
        );
        $this->assertEquals(
            "((foo = :foo1 AND bar = :bar1))",
            $result,
            "Single depth level"
        );

        $result = $this->crud->build_where_clause(
            [
                "OR" => [
                    "foo" => 1,
                    "bar" => 2
                ]
            ]
        );

        $this->assertEquals(
            "(foo = :foo1 OR bar = :bar1)",
            $result,
            "Double depth level with OR"
        );

        $result = $this->crud->build_where_clause(
            [
                "OR" => [
                    [
                        "foo" => 1,
                        "bar" => 2
                    ],
                    [
                        "foo" => 3,
                        "bar" => 4
                    ]
                ],
            ]
        );

        $this->assertEquals(
            "((foo = :foo2 AND bar = :bar2) OR (foo = :foo3 AND bar = :bar3))",
            $result,
            "Triple depth level with OR"
        );
    }

    public function testCreateAndRead() {
        $this->createAndRead();
    }

    public function testUpdate() {

        $row = $this->createAndRead();

        $result = $this->crud->update(
            "test",
            [
                "name" => $row["name"]." 2"
            ],
            ["id" => (int)$row["id"]]
        );

        $this->assertNotEmpty(
            $result
        );

        $new_rows = $this->crud->read("test", ["id" => (int)$row["id"]]);

        $this->assertNotEmpty(
            $new_rows
        );

        $this->assertEquals(
            $row["name"]." 2",
            $new_rows[0]["name"]
        );
    }

    public function testDelete() {

        $row = $this->createAndRead();

        $result = $this->crud->delete(
            "test",
            ["id" => $row["id"]]
        );

        $this->assertNotEmpty(
            $result
        );

        $new_rows = $this->crud->read("test", ["id" => $row["id"]]);

        $this->assertEmpty(
            $new_rows
        );
    }

    public function testMultiValueWhere() {
        $names = [];
        for ($x = 1; $x <= 5; $x++) {
            $name = "Multi Test $x ".microtime(true);
            $names[] = $name;
            $result = $this->crud->create(
                "test",
                [
                    "name"        => $name,
                    "description" => "Description",
                ]
            );
            $this->assertNotEmpty(
                $result
            );
        }

        $new_rows = $this->crud->read(
            "test",
            [
                "name" => $names
            ]
        );

        $this->assertEquals(
            count($names),
            count($new_rows)
        );

    }

    public function testLimit() {
        for ($x = 0; $x < 10; $x++) {
            $name        = "Test $x ".time();
            $description = "Test Description ".time();

            $result = $this->crud->create(
                "test",
                [
                    "name"        => $name,
                    "description" => $description,
                ]
            );
        }

        $rows = $this->crud->read("test", [], 5);

        $this->assertEquals(
            5,
            count($rows)
        );

        $other_rows = $this->crud->read("test", [], 5, 5);

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

        $name        = "Test ".time();
        $description = "Test Description ".time();

        $result = $this->crud->create(
            "test",
            [
                "name"        => $name,
                "description" => $description,
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

        $row = $this->crud->read("test", ["id" => $id]);

        $this->assertNotEmpty(
            $row
        );

        $this->assertEquals(
            $name,
            $row[0]["name"]
        );

        $this->assertEquals(
            $description,
            $row[0]["description"]
        );

        return $row[0];
    }
}
