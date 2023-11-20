<?php

require __DIR__ . '/../vendor/autoload.php';

$opts = getopt('', ['group::']);

if(isset($opts['group']) && strlen($opts['group']) > 0) {
    $opts['group'] = explode(',', $opts['group']);
}

define('INTEGRATION_TESTS', empty($opts['group']) || in_array('integration', $opts['group']));

/**
 * Function for helping debug tests since modern PHP Unit
 * does not allow var_dump to send output to STDOUT.
 */
function _debug() {
    fwrite(STDERR, "\nSTART DEBUG\n");
    fwrite(STDERR, "###########\n");
    $args = func_get_args();
    foreach ($args as $arg) {
        fwrite(STDERR, trim(var_export($arg, true)) . "\n");
    }
    fwrite(STDERR, "###########\n");
    fwrite(STDERR, "END DEBUG\n\n");
}

define('TEST_DB_FILE', __DIR__ . '/fixtures/test_copy.db');

function _startup() {
    copy(__DIR__ . '/fixtures/test.db', TEST_DB_FILE);
    register_shutdown_function('_shutdown');
}

function _shutdown() {
    // Create a fresh copy of the sqlite database
    if (file_exists(TEST_DB_FILE)) {
        unlink(TEST_DB_FILE);
    }
}

_startup();

// Check if we are running inside a docker container already
// If so, set the env vars correctly and don't run setup/teardown
if (trim(`which docker`) === '') {
    $mysql_host = 'db-mysql-sandbox';
    $mysql_port = 3306;
    $pgsql_host = 'db-pgsql-sandbox';
    $pgsql_port = 5432;
} else {
    /* Start daemons for testing. */
    passthru(__DIR__ . '/setup.sh');

    register_shutdown_function(function () {
        if (empty(getenv('KEEPCONTAINERS'))) {
            passthru(__DIR__ . '/teardown.sh');
        }
    });

    $mysql_host = '127.0.0.1';
    $mysql_port = 43306;
    $pgsql_host = '127.0.0.1';
    $pgsql_port = 55432;
}

// Setup config variables
putenv('db.factory.prefix=dealnews.db');

putenv('DEALNEWS_DB_CHINOOK_TYPE=pdo');
putenv('DEALNEWS_DB_CHINOOK_DSN=sqlite:tests/chinook.db');

putenv('DEALNEWS_DB_TESTDB_TYPE=pdo');
putenv('DEALNEWS_DB_TESTDB_DSN=sqlite:tests/fixtures/test_copy.db');

putenv('DEALNEWS_DB_MYPDOTESTDB_TYPE=pdo');
putenv("DEALNEWS_DB_MYPDOTESTDB_DSN=mysql:host={$mysql_host};port={$mysql_port};dbname=mytestdb");
putenv('DEALNEWS_DB_MYPDOTESTDB_USER=test');
putenv('DEALNEWS_DB_MYPDOTESTDB_PASS=test');

putenv('DEALNEWS_DB_MYTESTDB_TYPE=mysql');
putenv("DEALNEWS_DB_MYTESTDB_SERVER={$mysql_host}");
putenv("DEALNEWS_DB_MYTESTDB_PORT={$mysql_port}");
putenv('DEALNEWS_DB_MYTESTDB_USER=test');
putenv('DEALNEWS_DB_MYTESTDB_PASS=test');

putenv('DEALNEWS_DB_PGPDOTESTDB_TYPE=pdo');
putenv("DEALNEWS_DB_PGPDOTESTDB_DSN=pgsql:host={$pgsql_host};port={$pgsql_port};dbname=pgtestdb");
putenv('DEALNEWS_DB_PGPDOTESTDB_USER=test');
putenv('DEALNEWS_DB_PGPDOTESTDB_PASS=test');

putenv('DEALNEWS_DB_PGTESTDB_TYPE=pgsql');
putenv("DEALNEWS_DB_PGTESTDB_SERVER={$pgsql_host}");
putenv("DEALNEWS_DB_PGTESTDB_PORT={$pgsql_port}");
putenv('DEALNEWS_DB_PGTESTDB_USER=test');
putenv('DEALNEWS_DB_PGTESTDB_PASS=test');
