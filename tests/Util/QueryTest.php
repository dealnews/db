<?php

namespace DealNews\DB\Tests\Util;

use \DealNews\DB\Util\Query;
use \DealNews\DB\Util\Raw;
use \PHPUnit\Framework\TestCase;

/**
 * Unit tests for Query builder
 *
 * Tests use driver override to avoid needing real PDO connections.
 *
 * @author      Brian Moon <brianm@dealnews.com>
 * @copyright   1997-Present DealNews.com, Inc
 * @package     DB
 */
class QueryTest extends TestCase {

    /**
     * Creates a Query instance for MySQL driver testing
     *
     * @return Query
     */
    protected function createMySqlQuery(): Query {
        return new Query('mysql');
    }

    /**
     * Creates a Query instance for PostgreSQL driver testing
     *
     * @return Query
     */
    protected function createPgSqlQuery(): Query {
        return new Query('pgsql');
    }

    /**
     * Tests basic SELECT query
     */
    public function testBasicSelect(): void {
        $query = $this->createMySqlQuery();

        $query->select(['id', 'name', 'email'])
            ->from('users');

        $sql = $query->getSql();

        $this->assertStringContainsString('SELECT', $sql);
        $this->assertStringContainsString('`id`', $sql);
        $this->assertStringContainsString('`name`', $sql);
        $this->assertStringContainsString('`email`', $sql);
        $this->assertStringContainsString('FROM `users`', $sql);
    }

    /**
     * Tests SELECT with table alias
     */
    public function testSelectWithTableAlias(): void {
        $query = $this->createMySqlQuery();

        $query->select(['id', 'name'])
            ->from('users', 'u');

        $sql = $query->getSql();

        $this->assertStringContainsString('FROM `users` `u`', $sql);
    }

    /**
     * Tests SELECT with column alias using AS syntax
     */
    public function testSelectWithColumnAlias(): void {
        $query = $this->createMySqlQuery();

        $query->select(['id AS user_id', 'name AS full_name'])
            ->from('users');

        $sql = $query->getSql();

        $this->assertStringContainsString('`id` AS `user_id`', $sql);
        $this->assertStringContainsString('`name` AS `full_name`', $sql);
    }

    /**
     * Tests SELECT with star (all columns)
     */
    public function testSelectStar(): void {
        $query = $this->createMySqlQuery();

        $query->select(['*'])
            ->from('users');

        $sql = $query->getSql();

        $this->assertStringContainsString('SELECT *', $sql);
    }

    /**
     * Tests WHERE condition
     */
    public function testWhereCondition(): void {
        $query = $this->createMySqlQuery();

        $query->select(['id', 'name'])
            ->from('users')
            ->where('status', '=', 'active');

        $sql    = $query->getSql();
        $params = $query->getParams();

        $this->assertStringContainsString('WHERE `status` =', $sql);
        $this->assertArrayHasKey(':qb_param_0', $params);
        $this->assertEquals('active', $params[':qb_param_0']);
    }

    /**
     * Tests multiple WHERE conditions with AND
     */
    public function testMultipleWhereConditions(): void {
        $query = $this->createMySqlQuery();

        $query->select(['id'])
            ->from('users')
            ->where('status', '=', 'active')
            ->where('age', '>', 18);

        $sql    = $query->getSql();
        $params = $query->getParams();

        $this->assertStringContainsString('WHERE `status` =', $sql);
        $this->assertStringContainsString('AND `age` >', $sql);
        $this->assertEquals('active', $params[':qb_param_0']);
        $this->assertEquals(18, $params[':qb_param_1']);
    }

    /**
     * Tests OR WHERE condition
     */
    public function testOrWhereCondition(): void {
        $query = $this->createMySqlQuery();

        $query->select(['id'])
            ->from('users')
            ->where('status', '=', 'active')
            ->orWhere('role', '=', 'admin');

        $sql = $query->getSql();

        $this->assertStringContainsString('WHERE `status` =', $sql);
        $this->assertStringContainsString('OR `role` =', $sql);
    }

    /**
     * Tests nested WHERE conditions
     */
    public function testNestedWhereConditions(): void {
        $query = $this->createMySqlQuery();

        $query->select(['id'])
            ->from('users')
            ->where('status', '=', 'active')
            ->where(function ($q) {
                $q->where('role', '=', 'admin')
                  ->orWhere('role', '=', 'moderator');
            });

        $sql    = $query->getSql();
        $params = $query->getParams();

        $this->assertStringContainsString('WHERE `status` =', $sql);
        $this->assertStringContainsString('AND (`role` =', $sql);
        $this->assertStringContainsString('OR `role` =', $sql);
        $this->assertEquals('active', $params[':qb_param_0']);
        $this->assertEquals('admin', $params[':qb_param_1']);
        $this->assertEquals('moderator', $params[':qb_param_2']);
    }

    /**
     * Tests WHERE IN condition
     */
    public function testWhereIn(): void {
        $query = $this->createMySqlQuery();

        $query->select(['id'])
            ->from('users')
            ->whereIn('status', ['active', 'pending', 'review']);

        $sql    = $query->getSql();
        $params = $query->getParams();

        $this->assertStringContainsString('WHERE `status` IN (', $sql);
        $this->assertCount(3, $params);
    }

    /**
     * Tests WHERE NOT IN condition
     */
    public function testWhereNotIn(): void {
        $query = $this->createMySqlQuery();

        $query->select(['id'])
            ->from('users')
            ->whereNotIn('status', ['banned', 'deleted']);

        $sql = $query->getSql();

        $this->assertStringContainsString('WHERE `status` NOT IN (', $sql);
    }

    /**
     * Tests WHERE IS NULL condition
     */
    public function testWhereNull(): void {
        $query = $this->createMySqlQuery();

        $query->select(['id'])
            ->from('users')
            ->whereNull('deleted_at');

        $sql = $query->getSql();

        $this->assertStringContainsString('WHERE `deleted_at` IS NULL', $sql);
    }

    /**
     * Tests WHERE IS NOT NULL condition
     */
    public function testWhereNotNull(): void {
        $query = $this->createMySqlQuery();

        $query->select(['id'])
            ->from('users')
            ->whereNotNull('verified_at');

        $sql = $query->getSql();

        $this->assertStringContainsString('WHERE `verified_at` IS NOT NULL', $sql);
    }

    /**
     * Tests raw WHERE condition
     */
    public function testWhereRaw(): void {
        $query = $this->createMySqlQuery();

        $query->select(['id'])
            ->from('users')
            ->whereRaw('YEAR(created_at) = :year', [':year' => 2024]);

        $sql    = $query->getSql();
        $params = $query->getParams();

        $this->assertStringContainsString('WHERE YEAR(created_at) = :year', $sql);
        $this->assertEquals(2024, $params[':year']);
    }

    /**
     * Tests INNER JOIN
     */
    public function testInnerJoin(): void {
        $query = $this->createMySqlQuery();

        $query->select(['u.id', 'p.title'])
            ->from('users', 'u')
            ->innerJoin('posts', 'p', 'p.user_id', '=', 'u.id');

        $sql = $query->getSql();

        $this->assertStringContainsString(
            'INNER JOIN `posts` `p` ON `p`.`user_id` = `u`.`id`',
            $sql
        );
    }

    /**
     * Tests LEFT JOIN
     */
    public function testLeftJoin(): void {
        $query = $this->createMySqlQuery();

        $query->select(['u.id'])
            ->from('users', 'u')
            ->leftJoin('posts', 'p', 'p.user_id', '=', 'u.id');

        $sql = $query->getSql();

        $this->assertStringContainsString('LEFT JOIN `posts` `p`', $sql);
    }

    /**
     * Tests RIGHT JOIN
     */
    public function testRightJoin(): void {
        $query = $this->createMySqlQuery();

        $query->select(['u.id'])
            ->from('users', 'u')
            ->rightJoin('posts', 'p', 'p.user_id', '=', 'u.id');

        $sql = $query->getSql();

        $this->assertStringContainsString('RIGHT JOIN `posts` `p`', $sql);
    }

    /**
     * Tests multiple JOINs
     */
    public function testMultipleJoins(): void {
        $query = $this->createMySqlQuery();

        $query->select(['u.id'])
            ->from('users', 'u')
            ->leftJoin('posts', 'p', 'p.user_id', '=', 'u.id')
            ->leftJoin('profiles', 'pr', 'pr.user_id', '=', 'u.id');

        $sql = $query->getSql();

        $this->assertStringContainsString('LEFT JOIN `posts` `p`', $sql);
        $this->assertStringContainsString('LEFT JOIN `profiles` `pr`', $sql);
    }

    /**
     * Tests GROUP BY
     */
    public function testGroupBy(): void {
        $query = $this->createMySqlQuery();

        $query->select(['u.id', Query::raw('COUNT(p.id) AS post_count')])
            ->from('users', 'u')
            ->leftJoin('posts', 'p', 'p.user_id', '=', 'u.id')
            ->groupBy(['u.id']);

        $sql = $query->getSql();

        $this->assertStringContainsString('GROUP BY `u`.`id`', $sql);
    }

    /**
     * Tests HAVING
     */
    public function testHaving(): void {
        $query = $this->createMySqlQuery();

        $query->select(['u.id', Query::raw('COUNT(p.id) AS post_count')])
            ->from('users', 'u')
            ->leftJoin('posts', 'p', 'p.user_id', '=', 'u.id')
            ->groupBy(['u.id'])
            ->having('post_count', '>', 5);

        $sql    = $query->getSql();
        $params = $query->getParams();

        $this->assertStringContainsString('HAVING post_count >', $sql);
        $this->assertEquals(5, $params[':qb_param_0']);
    }

    /**
     * Tests multiple HAVING conditions
     */
    public function testMultipleHaving(): void {
        $query = $this->createMySqlQuery();

        $query->select(['u.id', Query::raw('COUNT(p.id) AS post_count')])
            ->from('users', 'u')
            ->leftJoin('posts', 'p', 'p.user_id', '=', 'u.id')
            ->groupBy(['u.id'])
            ->having('post_count', '>', 5)
            ->orHaving('post_count', '<', 2);

        $sql = $query->getSql();

        $this->assertStringContainsString('HAVING post_count >', $sql);
        $this->assertStringContainsString('OR post_count <', $sql);
    }

    /**
     * Tests ORDER BY
     */
    public function testOrderBy(): void {
        $query = $this->createMySqlQuery();

        $query->select(['id', 'name'])
            ->from('users')
            ->orderBy('created_at', 'DESC');

        $sql = $query->getSql();

        $this->assertStringContainsString('ORDER BY `created_at` DESC', $sql);
    }

    /**
     * Tests multiple ORDER BY
     */
    public function testMultipleOrderBy(): void {
        $query = $this->createMySqlQuery();

        $query->select(['id', 'name'])
            ->from('users')
            ->orderBy('status', 'ASC')
            ->orderBy('created_at', 'DESC');

        $sql = $query->getSql();

        $this->assertStringContainsString(
            'ORDER BY `status` ASC, `created_at` DESC',
            $sql
        );
    }

    /**
     * Tests LIMIT (MySQL style)
     */
    public function testLimitMySQL(): void {
        $query = $this->createMySqlQuery();

        $query->select(['id'])
            ->from('users')
            ->limit(10);

        $sql = $query->getSql();

        $this->assertStringContainsString('LIMIT 10', $sql);
    }

    /**
     * Tests LIMIT with OFFSET (MySQL style)
     */
    public function testLimitOffsetMySQL(): void {
        $query = $this->createMySqlQuery();

        $query->select(['id'])
            ->from('users')
            ->limit(10)
            ->offset(20);

        $sql = $query->getSql();

        $this->assertStringContainsString('LIMIT 20, 10', $sql);
    }

    /**
     * Tests LIMIT with OFFSET (PostgreSQL style)
     */
    public function testLimitOffsetPostgreSQL(): void {
        $query = $this->createPgSqlQuery();

        $query->select(['id'])
            ->from('users')
            ->limit(10)
            ->offset(20);

        $sql = $query->getSql();

        $this->assertStringContainsString('LIMIT 10 OFFSET 20', $sql);
    }

    /**
     * Tests PostgreSQL quoting
     */
    public function testPostgreSQLQuoting(): void {
        $query = $this->createPgSqlQuery();

        $query->select(['id', 'name'])
            ->from('users');

        $sql = $query->getSql();

        $this->assertStringContainsString('"id"', $sql);
        $this->assertStringContainsString('"name"', $sql);
        $this->assertStringContainsString('FROM "users"', $sql);
    }

    /**
     * Tests Raw SQL in SELECT
     */
    public function testRawInSelect(): void {
        $query = $this->createMySqlQuery();

        $query->select([
                'id',
                Query::raw('COUNT(*) AS total'),
                Query::raw('CONCAT(first_name, " ", last_name) AS full_name'),
            ])
            ->from('users');

        $sql = $query->getSql();

        $this->assertStringContainsString('COUNT(*) AS total', $sql);
        $this->assertStringContainsString(
            'CONCAT(first_name, " ", last_name) AS full_name',
            $sql
        );
    }

    /**
     * Tests exception for no SELECT columns
     */
    public function testExceptionNoSelect(): void {
        $query = $this->createMySqlQuery();
        $query->from('users');

        $this->expectException(\LogicException::class);
        $this->expectExceptionCode(Query::ERR_NO_SELECT);

        $query->getSql();
    }

    /**
     * Tests exception for no FROM table
     */
    public function testExceptionNoFrom(): void {
        $query = $this->createMySqlQuery();
        $query->select(['id']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionCode(Query::ERR_NO_FROM);

        $query->getSql();
    }

    /**
     * Tests exception for invalid operator
     */
    public function testExceptionInvalidOperator(): void {
        $query = $this->createMySqlQuery();

        $this->expectException(\LogicException::class);
        $this->expectExceptionCode(Query::ERR_INVALID_OPERATOR);

        $query->select(['id'])
            ->from('users')
            ->where('status', 'INVALID', 'active');
    }

    /**
     * Tests exception for invalid ORDER BY direction
     */
    public function testExceptionInvalidDirection(): void {
        $query = $this->createMySqlQuery();

        $this->expectException(\LogicException::class);
        $this->expectExceptionCode(Query::ERR_INVALID_DIRECTION);

        $query->select(['id'])
            ->from('users')
            ->orderBy('id', 'INVALID');
    }

    /**
     * Tests exception for invalid JOIN type
     */
    public function testExceptionInvalidJoinType(): void {
        $query = $this->createMySqlQuery();

        $this->expectException(\LogicException::class);
        $this->expectExceptionCode(Query::ERR_INVALID_JOIN_TYPE);

        $query->select(['id'])
            ->from('users', 'u')
            ->join('posts', 'p', 'p.user_id', '=', 'u.id', 'INVALID');
    }

    /**
     * Tests complex query with all features
     */
    public function testComplexQuery(): void {
        $query = $this->createMySqlQuery();

        $query->select([
                'u.id',
                'u.name',
                Query::raw('COUNT(p.id) AS post_count'),
                Query::raw('AVG(p.views) AS avg_views'),
            ])
            ->from('users', 'u')
            ->leftJoin('posts', 'p', 'p.user_id', '=', 'u.id')
            ->leftJoin('profiles', 'pr', 'pr.user_id', '=', 'u.id')
            ->where('u.status', '=', 'active')
            ->where('pr.verified', '=', true)
            ->groupBy(['u.id', 'u.name'])
            ->having('post_count', '>', 5)
            ->orderBy('post_count', 'DESC')
            ->limit(20)
            ->offset(40);

        $sql    = $query->getSql();
        $params = $query->getParams();

        $this->assertStringContainsString('SELECT', $sql);
        $this->assertStringContainsString('FROM `users` `u`', $sql);
        $this->assertStringContainsString('LEFT JOIN `posts` `p`', $sql);
        $this->assertStringContainsString('LEFT JOIN `profiles` `pr`', $sql);
        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('GROUP BY', $sql);
        $this->assertStringContainsString('HAVING', $sql);
        $this->assertStringContainsString('ORDER BY', $sql);
        $this->assertStringContainsString('LIMIT', $sql);

        $this->assertEquals('active', $params[':qb_param_0']);
        $this->assertEquals(true, $params[':qb_param_1']);
        $this->assertEquals(5, $params[':qb_param_2']);
    }

    /**
     * Tests that getSql() resets params on each call
     */
    public function testGetSqlResetsParams(): void {
        $query = $this->createMySqlQuery();

        $query->select(['id'])
            ->from('users')
            ->where('status', '=', 'active');

        $query->getSql();
        $params1 = $query->getParams();

        $query->getSql();
        $params2 = $query->getParams();

        $this->assertEquals($params1, $params2);
        $this->assertCount(1, $params2);
    }

    /**
     * Tests Raw class directly
     */
    public function testRawClass(): void {
        $raw = new Raw('COUNT(*)', [':foo' => 'bar']);

        $this->assertEquals('COUNT(*)', $raw->value);
        $this->assertEquals([':foo' => 'bar'], $raw->params);
    }

    /**
     * Tests static raw() factory method
     */
    public function testRawFactoryMethod(): void {
        $raw = Query::raw('SUM(amount)', [':min' => 100]);

        $this->assertInstanceOf(Raw::class, $raw);
        $this->assertEquals('SUM(amount)', $raw->value);
        $this->assertEquals([':min' => 100], $raw->params);
    }

    /**
     * Tests driver override in constructor
     */
    public function testDriverOverride(): void {
        $pdo   = new \DealNews\DB\PDO('sqlite::memory:');
        $query = new Query($pdo, 'pgsql');

        $query->select(['id'])
            ->from('users');

        $sql = $query->getSql();

        // Should use double quotes despite SQLite
        $this->assertStringContainsString('"id"', $sql);
        $this->assertStringContainsString('"users"', $sql);
    }

    /**
     * Tests qualified column names (table.column)
     */
    public function testQualifiedColumnNames(): void {
        $query = $this->createMySqlQuery();

        $query->select(['users.id', 'users.name'])
            ->from('users');

        $sql = $query->getSql();

        $this->assertStringContainsString('`users`.`id`', $sql);
        $this->assertStringContainsString('`users`.`name`', $sql);
    }
}
