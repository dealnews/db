# AGENTS.md - DealNews Database Library

> **AI Agent Context Document**  
> This file provides comprehensive context for AI agents working with the DealNews Database Library.

## Library Overview

**Name**: `dealnews/db`  
**Type**: PHP Library  
**License**: BSD-3-Clause  
**PHP Version**: ^8.2  
**Purpose**: Database abstraction library providing PDO connection factory, CRUD operations, and data mapper pattern implementation

This library simplifies database operations by providing:
- Factory pattern for creating PDO connections from configuration
- CRUD helper wrapping common PDO operations
- Data mapper pattern implementation for separating objects from persistence
- Automatic retry logic for transient database errors
- Support for MySQL, PostgreSQL, and generic PDO connections

## Core Architecture

### Component Hierarchy

```
DealNews\DB\
├── Factory              # Creates PDO connections from config
├── PDO                  # Wrapper adding retry/reconnect logic
├── PDOStatement         # Wrapper adding retry logic to statements
├── CRUD                 # Helper for basic CRUD operations
├── AbstractMapper       # Base class for data mappers
├── ColumnMapper         # Maps array columns to/from tables
└── Util\
    ├── Query            # Fluent SQL query builder for complex SELECTs
    ├── Raw              # Value object for raw SQL fragments
    └── Search\Text      # Creates SQL LIKE clauses from search strings
```

### Key Design Patterns

1. **Factory Pattern**: `Factory::init()` creates database connections
2. **Singleton Pattern**: CRUD instances cached per database name
3. **Data Mapper Pattern**: Separates domain objects from database persistence
4. **Decorator Pattern**: PDO/PDOStatement wrappers add resilience
5. **Template Method Pattern**: AbstractMapper defines save/load flow

## Configuration

### Database Configuration (config.ini)

Located in `[app home]/etc/config.ini`:

```ini
db.mydb.type   = mysql              # mysql, pgsql, or pdo
db.mydb.server = 127.0.0.1          # comma-separated list
db.mydb.port   = 3306               # optional, defaults vary
db.mydb.db     = database_name      # database name
db.mydb.user   = username           # optional for some drivers
db.mydb.pass   = password           # optional for some drivers
db.mydb.charset = utf8mb4           # mysql only, defaults to utf8mb4
db.mydb.options = {"key": "value"}  # JSON-encoded PDO options
db.mydb.table_prefix = prefix       # optional table prefix
```

Alternative prefix:
```ini
db.factory.prefix = custom_prefix
custom_prefix.mydb.type = mysql
```

## Core Components

### 1. Factory (`DealNews\DB\Factory`)

**Purpose**: Creates PDO connection objects from configuration.

**Key Methods**:
- `init(string $db, ?array $options = null, ?string $type = null): PDO`
- `build(array $config): PDO`
- `loadConfig(array $config, ?array $options = null, ?string $type = null): array`
- `getConfig(string $db, ?GetConfig $cfg = null): array`

**Usage**:
```php
$pdo = \DealNews\DB\Factory::init("mydb");
```

**Important Behaviors**:
- Returns singleton instances (cached per db+type+options)
- Shuffles multiple servers for load balancing
- Auto-detects database type from config (defaults to mysql)
- Merges options from config and parameters

### 2. PDO (`DealNews\DB\PDO`)

**Purpose**: Wraps \PDO with automatic retry and reconnection logic.

**Key Features**:
- Automatic retry for deadlocks and transient errors (up to 3 attempts)
- Automatic reconnection for connection failures
- Default fetch mode: `PDO::FETCH_ASSOC`
- Default error mode: `PDO::ERRMODE_EXCEPTION`
- Emulated prepares for retry compatibility
- Default timeout: 10 seconds

**Retry Error Codes**:
- MySQL: 1422 (commit in trigger), 1213 (deadlock), 1205 (lock timeout)
- PostgreSQL: 40000-40003, 40P01 (serialization/deadlock errors)

**Reconnect Error Codes**:
- MySQL: Connection errors (1040, 2002, 2003, 2006, 2013, etc.)
- PostgreSQL: Connection errors (08000, 08003, 08006, etc.)

**Usage**:
```php
$pdo->connect();           // Lazy connect
$pdo->connect(true);       // Force reconnect
$pdo->ping();             // Test connection
$pdo->close();            // Close connection
```

### 3. CRUD (`DealNews\DB\CRUD`)

**Purpose**: Simplifies common database operations with prepared statements.

**Key Methods**:
- `create(string $table, array $data): bool`
- `read(string $table, array $data = [], ?int $limit = null, ?int $start = null, array $fields = ['*'], string $order = ''): array`
- `update(string $table, array $data, array $where): bool`
- `delete(string $table, array $data): bool`
- `run(string $query, array $params = []): PDOStatement`
- `runFetch(string $query, array $params = []): array`

**Factory Method**:
```php
$crud = \DealNews\DB\CRUD::factory('mydb');
```

**Query Building**:
- Field names automatically quoted (backticks for MySQL, double-quotes otherwise)
- Parameters use named placeholders (`:field_name`)
- WHERE clauses support AND/OR logic with nested arrays
- Array values generate OR clauses: `['id' => [1, 2, 3]]` → `(id = :id0 OR id = :id1 OR id = :id2)`

**Advanced Filters**:
```php
// AND logic (default)
['status' => 'active', 'age' => 25]
// (status = :status0 AND age = :age0)

// OR logic
['OR' => ['status' => 'active', 'age' => 25]]
// (status = :status1 OR age = :age1)

// Mixed logic with nesting
[
    'status' => 'active',
    ['OR' => ['age' => 25, 'name' => 'John']]
]
// (status = :status0 AND (age = :age1 OR name = :name1))
```

### 4. AbstractMapper (`DealNews\DB\AbstractMapper`)

**Purpose**: Base class for implementing the data mapper pattern.

**Required Constants**:
```php
public const DATABASE_NAME = 'mydb';          # Config name
public const TABLE = 'books';                 # Table name
public const PRIMARY_KEY = 'id';              # Primary key column
public const SEQUENCE_NAME = null;            # For PostgreSQL sequences
public const MAPPED_CLASS = Book::class;      # Value object class
public const MAPPING = [                      # Property-to-column mapping
    'id' => [],
    'title' => [],
    'author' => [],
];
```

**Key Methods**:
- `load($id): ?object` - Load single object by primary key
- `loadMulti(array $ids): ?array` - Load multiple objects
- `find(array $filter, ?int $limit = null, ?int $start = null, string $order = ''): ?array`
- `save($object): object` - Insert or update (returns reloaded object)
- `delete($id): bool` - Delete by primary key

**Mapping Configuration**:

Basic property mapping:
```php
public const MAPPING = [
    'property_name' => [],  # Auto-maps to column 'property_name'
];
```

Column name override:
```php
'property_name' => ['column' => 'different_column_name']
```

Type casting:
```php
'created_at' => ['type' => 'datetime']  # Converts to DateTime
'price' => ['type' => 'float']
'active' => ['type' => 'boolean']
```

**Relational Mapping**:

One-to-Many (foreign key in related table):
```php
'comments' => [
    'mapper' => CommentMapper::class,
    'foreign_column' => 'post_id',  # Column in comments table
]
```

Many-to-Many (lookup/xref table):
```php
'tags' => [
    'type' => 'lookup',
    'mapper' => TagMapper::class,
    'table' => 'post_tags',          # Lookup table
    'primary_key' => 'id',           # Lookup table PK
    'foreign_column' => 'post_id',   # Foreign key to this object
    'mapper_column' => 'tag_id',     # Foreign key to related object
]
```

**Transaction Behavior**:
- `save()` creates transaction if not already in one
- Nested `save()` calls reuse existing transaction
- Automatic rollback on exceptions
- Commits only at outermost transaction level

### 5. ColumnMapper (`DealNews\DB\ColumnMapper`)

**Purpose**: Maps arrays to/from a single column in a related table.

**Use Case**: When you need to store multiple simple values (not objects) in a separate table.

**Example**:
```php
// Map array of email addresses to email_addresses table
'emails' => [
    'mapper' => ColumnMapper::class,
    'table' => 'user_emails',
    'primary_key' => 'id',
    'foreign_column' => 'user_id',
    'column' => 'email',
]
```

**Behavior**:
- `load()` returns array of column values
- `save()` diffs existing vs new, adds/removes as needed
- Participates in parent transaction

### 6. Util\Search\Text (`DealNews\DB\Util\Search\Text`)

**Purpose**: Converts user search strings to SQL LIKE clauses.

**Features**:
- Quoted strings for exact matches: `"exact phrase"`
- Boolean AND (space): `term1 term2`
- Boolean OR (comma): `term1, term2`
- NOT modifier: `-unwanted`
- Grouping: `(term1, term2) term3`
- Anchors: `^start` (starts with), `end$` (ends with)

**Usage**:
```php
$search = \DealNews\DB\Util\Search\Text::init();
$like_clause = $search->createLikeString(['title', 'description'], 'foo bar');
// Returns: ((title LIKE '%foo%' OR description LIKE '%foo%') AND (title LIKE '%bar%' OR description LIKE '%bar%'))
```

### 7. Util\Query (`DealNews\DB\Util\Query`)

**Purpose**: Fluent SQL query builder for complex SELECT queries.

**Features**:
- Fluent method chaining API
- SELECT with column aliases and raw expressions
- JOINs: INNER, LEFT, RIGHT
- WHERE conditions: AND, OR, IN, NOT IN, NULL, nested groups
- GROUP BY and HAVING
- ORDER BY with multiple columns
- LIMIT/OFFSET with driver-aware syntax (MySQL vs PostgreSQL)
- Raw SQL fragments via `Query::raw()`
- Automatic parameter binding

**Usage**:
```php
$crud = \DealNews\DB\CRUD::factory('mydb');
$query = new \DealNews\DB\Util\Query($crud);

$query->select([
        'u.id',
        'u.name',
        Query::raw('COUNT(p.id) AS post_count'),
    ])
    ->from('users', 'u')
    ->leftJoin('posts', 'p', 'p.user_id', '=', 'u.id')
    ->where('u.status', '=', 'active')
    ->where(function ($q) {
        $q->where('u.role', '=', 'admin')
          ->orWhere('u.role', '=', 'moderator');
    })
    ->groupBy(['u.id', 'u.name'])
    ->having('post_count', '>', 5)
    ->orderBy('post_count', 'DESC')
    ->limit(20)
    ->offset(40);

$rows = $crud->runFetch($query->getSql(), $query->getParams());
```

**Key Methods**:
- `select(array $columns)` - Set columns (strings, `Query::raw()`, or subqueries)
- `from(string $table, ?string $alias)` - Set FROM table
- `join()`, `innerJoin()`, `leftJoin()`, `rightJoin()` - Add JOIN clauses
- `where()`, `orWhere()` - Add WHERE (supports callable for nesting)
- `whereIn()`, `whereNotIn()`, `whereNull()`, `whereNotNull()`, `whereRaw()`
- `groupBy(array $columns)` - GROUP BY
- `having()`, `orHaving()` - HAVING conditions
- `orderBy(string $column, string $direction)` - ORDER BY
- `limit(int)`, `offset(int)` - Pagination
- `getSql()` - Build and return SQL string
- `getParams()` - Get bound parameters array
- `Query::raw(string $value, array $params)` - Create raw SQL fragment

**Error Handling**:
- Throws `\LogicException` with unique codes for invalid operators, directions, JOIN types
- Validates required SELECT and FROM before building

### 8. Util\Raw (`DealNews\DB\Util\Raw`)

**Purpose**: Value object for raw SQL fragments in Query builder.

**Usage**:
```php
// Static factory (preferred)
$raw = \DealNews\DB\Util\Query::raw('COUNT(*)', [':min' => 100]);

// Direct instantiation
$raw = new \DealNews\DB\Util\Raw('YEAR(created_at) = ?', [2024]);

// Use in Query
$query->select([
    'id',
    Query::raw('CONCAT(first, " ", last) AS full_name'),
]);
```

## Code Generation Tool

### bin/create_objects.php

**Purpose**: Generates value objects and mapper classes from database schema.

**Usage**:
```bash
./vendor/bin/create_objects.php \
  --db mydb \
  --namespace MyApp\\Data \
  --table users \
  --dir src/Data
```

**Options**:
- `--db DBNAME` - Database config name (required)
- `--schema SCHEMA` - Schema name if different from db name
- `--table TABLE` - Table to generate from (required)
- `--namespace NAMESPACE` - Base namespace (required)
- `--dir DIR` - Output directory (default: src)
- `--ini-file FILE` - Config file (default: etc/config.ini)
- `--base-class CLASS` - Optional base class for value objects
- `-v, -vv, -vvv` - Verbosity levels
- `-q` - Quiet mode

**Generated Files**:
- Value object class with typed properties
- Mapper class extending AbstractMapper
- PHPDoc blocks with type information

**Recommended Base Class**: [Moonspot\ValueObjects](https://github.com/brianlmoon/value-objects) for easier manipulation.

## Testing

### Test Structure

```
tests/
├── bootstrap.php           # Test setup
├── RequireDatabase.php     # Trait for functional tests
├── setup.sh / teardown.sh  # Container management
├── containers/             # Docker configs for MySQL/PostgreSQL
├── fixtures/               # Test data
└── chinook.db             # SQLite test database
```

### Running Tests

**Unit tests only** (default):
```bash
composer test
```

**Functional tests** (requires Docker):
```bash
phpunit --group functional
```

**Requirements for functional tests**:
- Docker host machine
- PHP extensions: pdo_pgsql, pdo_mysql, pdo_sqlite

### Test Groups
- `unit` - Fast, no database required
- `functional` - Requires database containers

## Coding Standards

### General Principles

1. **Brace Style**: 1TBS (opening brace on same line)
2. **Visibility**: Use `protected` over `private` for testability
3. **Type Hints**: Always declare parameter and return types
4. **Return Values**: Single return point (except early validation)
5. **Naming**: `snake_case` for variables/properties, `PascalCase` for classes
6. **Arrays**: Short syntax `[]`, trailing commas in multi-line
7. **Line Length**: 80 characters preferred
8. **Whitespace**: Unix `\n`, no trailing whitespace

### PHP-Specific Rules

**Property Types**:
```php
// Good
public string $name = '';
public ?DateTime $created_at = null;

// Avoid
public $name;
```

**Functions/Methods**:
```php
// Good
public function doSomething(int $foo, int $bar): ?string {
    $result = null;
    if ($condition) {
        $result = 'value';
    }
    return $result;
}

// Avoid multiple returns
public function doSomething(int $foo, int $bar) {
    if ($condition) {
        return 'value';
    }
    return null;
}
```

**No Pass-by-Reference**:
```php
// Good
public function modify(object $obj): object {
    return $obj;
}

// Avoid
public function modify(object &$obj) {}
```

**Constants over Static Variables**:
```php
// Good
public const CONFIG = ['key' => 'value'];

// Avoid
public static $config = ['key' => 'value'];
```

### PHPDoc Requirements

All public classes, methods, and functions require PHPDoc:

```php
/**
 * Brief description of what this does
 *
 * @param  int    $id      The primary key
 * @param  array  $options Additional options
 *
 * @return object|null
 *
 * @throws \PDOException
 * @throws \LogicException
 */
public function load(int $id, array $options = []): ?object {
    // implementation
}
```

## Common Patterns and Best Practices

### Pattern: Loading Related Data

```php
class PostMapper extends AbstractMapper {
    // ... constants ...
    
    public const MAPPING = [
        'id' => [],
        'title' => [],
        'content' => [],
        // One-to-many: load all comments for this post
        'comments' => [
            'mapper' => CommentMapper::class,
            'foreign_column' => 'post_id',
        ],
        // Many-to-many: load tags via lookup table
        'tags' => [
            'type' => 'lookup',
            'mapper' => TagMapper::class,
            'table' => 'post_tags',
            'primary_key' => 'id',
            'foreign_column' => 'post_id',
            'mapper_column' => 'tag_id',
        ],
        // Array column: simple values in related table
        'keywords' => [
            'mapper' => ColumnMapper::class,
            'table' => 'post_keywords',
            'primary_key' => 'id',
            'foreign_column' => 'post_id',
            'column' => 'keyword',
        ],
    ];
}
```

### Pattern: Custom Mapper Logic

Override `getData()` or `setData()` for complex transformations:

```php
class UserMapper extends AbstractMapper {
    protected function getData($object): array {
        $data = parent::getData($object);
        // Custom serialization
        if (isset($data['preferences'])) {
            $data['preferences'] = json_encode($data['preferences']);
        }
        return $data;
    }
    
    protected function setData(array $data): object {
        // Custom deserialization
        if (isset($data['preferences'])) {
            $data['preferences'] = json_decode($data['preferences'], true);
        }
        return parent::setData($data);
    }
}
```

### Pattern: Complex Queries

For queries beyond simple CRUD:

```php
$crud = CRUD::factory('mydb');

// Raw query with parameters
$stmt = $crud->run(
    "SELECT u.*, COUNT(p.id) as post_count
     FROM users u
     LEFT JOIN posts p ON u.id = p.user_id
     WHERE u.status = :status
     GROUP BY u.id
     HAVING post_count > :min_posts",
    [
        ':status' => 'active',
        ':min_posts' => 10,
    ]
);
$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
```

### Pattern: Transaction Management

```php
$crud = CRUD::factory('mydb');
$crud->pdo->beginTransaction();

try {
    $crud->create('users', ['name' => 'John']);
    $user_id = $crud->pdo->lastInsertId();
    
    $crud->create('profiles', [
        'user_id' => $user_id,
        'bio' => 'Developer',
    ]);
    
    $crud->pdo->commit();
} catch (\Throwable $e) {
    $crud->pdo->rollBack();
    throw $e;
}
```

### Pattern: Custom Update Constraints

Override `getUpdateConstraint()` for composite keys or custom logic:

```php
protected function getUpdateConstraint(object $object): array {
    return [
        'tenant_id' => $object->tenant_id,
        'user_id' => $object->user_id,
    ];
}
```

## Dependencies

### Required
- `php: ^8.2`
- `dealnews/console: ^0.2.1` - CLI option parsing
- `dealnews/data-mapper: ^3.4` - Base mapper functionality
- `dealnews/get-config: ^2.2` - Configuration management

### Dev Dependencies
- `friendsofphp/php-cs-fixer: ^3.89` - Code formatting
- `php-parallel-lint/php-parallel-lint: ^1.4` - Syntax checking
- `phpunit/phpunit: ^11.5` - Testing framework

## Scripts and Tooling

**Composer Scripts**:
```bash
composer test      # Lint + unit tests
composer lint      # PHP syntax check
composer fix       # Auto-fix code style
composer phan      # Static analysis (if configured)
```

**Manual Commands**:
```bash
vendor/bin/phpunit --colors=never
vendor/bin/phpunit --group functional
vendor/bin/php-cs-fixer fix --config .php-cs-fixer.dist.php src tests
vendor/bin/parallel-lint src/ tests/
```

## Error Handling

### Exception Types

- `\PDOException` - Database errors (automatically retried if transient)
- `\LogicException` - Configuration or usage errors
- `\UnexpectedValueException` - Invalid config values
- `\InvalidArgumentException` - Invalid method arguments

### Retry Logic

The PDO and PDOStatement wrappers automatically retry:
1. Deadlocks and serialization failures (up to 3 times)
2. Connection failures (reconnects then retries up to 3 times)
3. Other transient errors based on error code

**Not retried**:
- Syntax errors
- Constraint violations
- Permission errors
- After 3 failed attempts

### Custom Error Handling

```php
try {
    $object = $mapper->save($object);
} catch (\PDOException $e) {
    // Already retried 3 times, handle permanent failure
    error_log("Failed to save object: " . $e->getMessage());
    throw $e;
}
```

## Performance Considerations

1. **Connection Pooling**: Factory returns singletons, reuse them
2. **Prepared Statements**: CRUD automatically uses prepared statements
3. **Batch Operations**: Load multiple objects with `loadMulti()`
4. **Lazy Loading**: Relations loaded only when `find()` or `load()` called
5. **Transaction Grouping**: Wrap multiple saves in one transaction
6. **Emulated Prepares**: Enabled for retry compatibility (slight overhead)

## Troubleshooting

### Common Issues

**"No database configuration for X"**:
- Check `etc/config.ini` has `db.X.type` defined
- Verify `DATABASE_NAME` constant matches config key

**"Either `server` or `dsn` is required"**:
- For mysql/pgsql types, specify `server` in config
- For pdo type, specify `dsn` in config

**"Lock wait timeout" or "Deadlock found"**:
- Already retried 3 times automatically
- Consider increasing MySQL `innodb_lock_wait_timeout`
- Review transaction scope and ordering

**Relations not loading**:
- Verify mapper class is imported and autoloadable
- Check `foreign_column` matches actual database column
- For lookup tables, verify all column names in mapping

**"Too many connections"**:
- Reuse CRUD/Factory instances (they're singletons)
- Call `$crud->pdo->close()` when done with connections
- Increase database max_connections setting

## Migration from Older Versions

### From direct PDO usage:

```php
// Old
$pdo = new \PDO($dsn, $user, $pass);
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(\PDO::FETCH_ASSOC);

// New
$crud = \DealNews\DB\CRUD::factory('mydb');
$rows = $crud->read('users', ['id' => $id]);
$row = $rows[0] ?? null;
```

### From array-based data:

```php
// Old
$user = [
    'id' => 1,
    'name' => 'John',
    'email' => 'john@example.com'
];

// New - create value object
class User {
    public int $id = 0;
    public string $name = '';
    public string $email = '';
}

class UserMapper extends \DealNews\DB\AbstractMapper {
    public const DATABASE_NAME = 'mydb';
    public const TABLE = 'users';
    public const PRIMARY_KEY = 'id';
    public const MAPPED_CLASS = User::class;
    public const MAPPING = [
        'id' => [],
        'name' => [],
        'email' => [],
    ];
}

$mapper = new UserMapper();
$user = $mapper->load(1);
```

## Quick Reference

### Create Connection
```php
$pdo = \DealNews\DB\Factory::init("mydb");
$crud = \DealNews\DB\CRUD::factory("mydb");
```

### Basic CRUD
```php
$crud->create('users', ['name' => 'John']);
$rows = $crud->read('users', ['status' => 'active'], limit: 10);
$crud->update('users', ['status' => 'inactive'], ['id' => 5]);
$crud->delete('users', ['id' => 5]);
```

### Data Mapper
```php
$mapper = new UserMapper();
$user = $mapper->load(1);
$users = $mapper->find(['status' => 'active'], limit: 10);
$user = $mapper->save($user);
$mapper->delete(1);
```

### Transactions
```php
$crud->pdo->beginTransaction();
try {
    // operations
    $crud->pdo->commit();
} catch (\Throwable $e) {
    $crud->pdo->rollBack();
    throw $e;
}
```

---

**Last Updated**: 2025-12-31  
**Maintained By**: DealNews.com, Inc.  
**Copyright**: 1997-Present DealNews.com, Inc.
