# DealNews Datbase Library


## Factory

The factory creates PDO objects using \DealNews\GetConfigto
read database settings and create a PDO database connection.

### Supported Settings

| Setting | Description                                                                          |
|---------|--------------------------------------------------------------------------------------|
| type    | This may be one of `pdo`, `mysql`, or `pgsql`. All types return PDO connections.     |
| dsn     | A valid PDO DSN. See each driver for specifics                                       |
| db      | The name of the database. For PDO, this is usually in the DSN.                       |
| server  | One of more comma separated servers names. Not used by the `pdo` type.               |
| port    | Server port. Not used by the `pdo` type.                                             |
| user    | Database user name. Not all PDO drivers require one.                                 |
| pass    | Database password. Not all PDO drivers require one.                                  |
| charset | Character set to use for `mysql` connections. The default is `utf8mb4`.              |
| options | A JSON encoded array of options to pass to the PDO constructor. These vary by driver |

### Usage

Example:

```php
$mydb = \DealNews\DB\Factory::init("mydb");
```

## CRUD

The `CRUD` class is a helper that wraps up common PDO logic for CRUD operations.

### Basic Usage

```php
$mydb = \DealNews\DB\Factory::init("mydb");
$crud = new \DealNews\DB\CRUD($mydb);

// Create
$result = $crud->create(
    // table name
    "test",
    // data to add
    [
        "name"        => $name,
        "description" => $description,
    ]
);

// Read
$rows = $crud->read(
    // table name
    "test",
    // where clause data
    ["id" => $id]
);

// Update
$result = $crud->update(
    // table name
    "test",
    // data to update
    ["name" => "Test"],
    // where clause data
    ["id" => $id]
);

// Delete
$result = $crud->delete(
    // table name
    "test",
    // where clause data
    ["id" => $row["id"]]
);
```

### Advanced Usage

The class also exposes a `run` method which is used internally by the other
methods. Complex queries can be run using this method by providing an SQL
query and a parameter array which will be mapped to the prepared query. It
returns a PDOStatement object.

```php
// Run a select with no parameters
$stmt = $crud->run("select * from table limit 10");

// Run a select query with paramters
$stmt = $crud->run(
    "select * from table where foo = :foo"
    [
        ":foo" => $foo
    ]
);
```

## Data Mapper Pattern

This library includes an abstract mapper class for creating [data mapper](https://en.wikipedia.org/wiki/Data_mapper_pattern) 
classes. The data mapper pattern separates the object from how it is stored. A a [value object](https://en.wikipedia.org/wiki/Value_object)
is created and a data mapper that is responsible for CRUD operations to and from a datastore.

```php
// value object
class Book {
    public string $title = '';
    public string $author = '';
    public string $isbn = '';
}
```
```php
// mapper
class BookMapper extends \DealNews\DB\AbstractMapper {
    
    public const DATABASE_NAME = 'example';
    
    public const TABLE = 'books';
    
    public const PRIMARY_KEY = 'isbn';
    
    public const MAPPED_CLASS = Book::class;
    
    public const MAPPING = [
        'title'  => [],
        'author' => [],
        'isbn'   => []    
    ];
}
```
To load, save, delete, etc. the mapper is used like so.
```php

$book = new Book();
$book->title = 'Professional PHP Programming';
$book->author = 'Jesus Castagnetto';
$book->isbn = '1-861002-96-3';

$mapper = new BookMapper();
$book = $mapper->save($book); // the entity is returned from save, reloaded from the database

// load a book
$book = $mapper->load('1-861002-96-3');

// delete a book
$mapper->delete('1-861002-96-3');

// find books based on author
$books = $mapper->find(['author' => 'Rasmus Lerdorf'], limit: 10, start: 0, order: 'title');
```
### Generating Value Objects and Mappers

This library includes a script for generating value objects and mappers. It has been tested and
works with MySQL and PostgreSQL. Once installed in your project, the script can be run from the
`vendor/bin` directory.

```shell
$ ./bin/create_objects.php -h

This script builds data objects and mappers.
USAGE:
  create_objects.php  -h | --db DBNAME | --namespace NAMESPACE | --table TABLE [--base-class CLASS] [--dir DIR] [--ini-file FILE] [-q] [--schema SCHEMA] [-v]

OPTIONS:
  --base-class  CLASS      Optional base class for value objects. See README
                           for recommendations.
  --db          DBNAME     Name of the databse configuration in config.ini
  --dir         DIR        Directory to write objects to. Defaults to `src`.
   -h                      Shows this help
  --ini-file    FILE       Alternate ini file to use. Defaults to
                           etc/config.ini.
  --namespace   NAMESPACE  Base namespace for objects.
   -q                      Be quiet. Will override -v
  --schema      SCHEMA     Name of the databse schema if different from the
                           database configuration name.
  --table       TABLE      Name of the databse table to create objects for.
   -v                      Be verbose. Additional v will increase verbosity.
                           e.g. -vvv

Copyright DealNews.com, Inc.  1997-2025

```

### Base Class for Value Objects

This library will work with plan PHP classes that do not extend any other class. However, using
a base class such as [Moonspot\ValueObjects](https://github.com/brianlmoon/value-objects) can
make working with the value objects easier.

## Nested Mappers, Relational Data, and More

Documentation coming

## Testing

By default, only unit tests are run. To run the functional tests the host
machine will need to be a docker host. Also, the pdo_pgsql, pdo_mysql, and
pdo_sqlite extensions must be installed on the host machine. PHPUnit will
start and stop docker containers to test the MySQL and Postgres connections.
Use `--group functional` when running PHPUnit to run these tests.
