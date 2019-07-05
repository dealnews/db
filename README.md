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

```
$mydb = \DealNews\DB\Factory::init("mydb");
```

## CRUD

The `CRUD` class is a helper that wraps up common PDO logic for CRUD operations.

### Basic Usage

```
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

```
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

## Testing

By default, only unit tests are run. To run the functional tests the host
machine will need to be a docker host. Also, the pdo_pgsql, pdo_mysql, and
pdo_sqlite extensions must be installed on the host machine. PHPUnit will
start and stop docker containers to test the MySQL and Postgres connections.
Use `--group functional` when running PHPUnit to run these tests.
