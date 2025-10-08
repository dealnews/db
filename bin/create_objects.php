#!/usr/bin/env php
<?php

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../../../autoload.php')) {
    require __DIR__ . '/../../../autoload.php';
}

use DealNews\Console\Console;
use DealNews\DB\CRUD;

$console = new Console(
    [
        "copyright" => [
            "owner" => "DealNews.com, Inc.",
            "year"  => "1997-" . date("Y")
        ],
        "help"      => [
            "header" => "This script builds data objects and mappers."
        ]
    ],
    [
        'db' => [
            "description" => "Name of the databse configuration in config.ini",
            "param"       => "DBNAME",
            "optional"    => Console::REQUIRED,
        ],
        'schema' => [
            "description" => "Name of the databse schema if different from the database configuration name.",
            "param"       => "SCHEMA",
            "optional"    => Console::OPTIONAL,
        ],
        'table' => [
            "description" => "Name of the databse table to create objects for.",
            "param"       => "TABLE",
            "optional"    => Console::REQUIRED,
        ],
        'dir' => [
            "description" => "Directory to write objects to. Defaults to `src`.",
            "param"       => "DIR",
            "optional"    => Console::OPTIONAL,
        ],
        'ini-file' => [
            "description" => "Alternate ini file to use. Defaults to etc/config.ini.",
            "param"       => "FILE",
            "optional"    => Console::OPTIONAL,
        ],
        'namespace' => [
            "description" => "Base namespace for objects.",
            "param"       => "NAMESPACE",
            "optional"    => Console::REQUIRED,
        ],
        'base-class' => [
                "description" => "Base class for value objects.",
                "param"       => "CLASS",
                "optional"    => Console::OPTIONAL,
        ],
    ]
);

$console->run();

$opts = [
    'db'         => $console->getOpt('db'),
    'schema'     => $console->getOpt('schema') ?? $console->getOpt('db'),
    'table'      => $console->getOpt('table'),
    'dir'        => $console->getOpt('dir') ?? 'src',
    'ini-file'   => $console->getOpt('ini-file'),
    'namespace'  => $console->getOpt('namespace'),
    'base-class' => $console->getOpt('base-class'),
];

if (!empty($opts['ini-file'])) {
    putenv('DN_INI_FILE=' . $opts['ini-file']);
}

$db = CRUD::factory($opts['db']);

$driver = $db->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

$sql = "select
            table_catalog as table_catalog,
            table_schema as table_schema,
            table_name as table_name,
            column_name as column_name,
            ordinal_position as ordinal_position,
            column_default as column_default,
            is_nullable as is_nullable,
            data_type as data_type,
            character_maximum_length as character_maximum_length,
            character_octet_length as character_octet_length,
            numeric_precision as numeric_precision,
            numeric_scale as numeric_scale,
            datetime_precision as datetime_precision,
            character_set_name as character_set_name,
            collation_name as collation_name
        from 
            information_schema.columns
        where 
            table_schema='{$opts['schema']}' and 
            table_name='{$opts['table']}'";

$schema = $db->runFetch($sql);

$sql = "select
            constraint_name as constraint_name,
            column_name as column_name
        from
            information_schema.key_column_usage
        where
            table_schema='{$opts['schema']}' and
            table_name='{$opts['table']}'
        order by
            constraint_name,
            ordinal_position";

$keys = $db->runFetch($sql);

$primary_key = '';

foreach ($keys as $key) {
    if ($driver === 'mysql' && $key['constraint_name'] == 'PRIMARY') {
        $primary_key = $key['column_name'];
        break;
    } elseif ($driver === 'pgsql' && preg_match('/_pkey$/', $key['constraint_name'])) {
        $primary_key = $key['column_name'];
        break;
    }
}

$properties = [];

foreach ($schema as $column) {

    switch ($column['data_type']) {

        case 'int':
        case 'integer':
        case 'smallint':
        case 'bigint':
        case 'tinyint':
        case 'year':
            $type        = 'int';
            $def_default = 0;
            break;

        case 'boolean':
            $type        = 'bool';
            $def_default = true;
            break;

        case 'float':
        case 'double precision':
        case 'real':
        case 'decimal':
        case 'double':
            $type        = 'float';
            $def_default = 0.00;
            break;

        default:
            $type        = 'string';
            $def_default = '';
    }

    if (strtoupper($column['is_nullable']) === 'YES') {
        $type    = "?$type";
        $default = null;
    } else {
        $default = $def_default;
    }


    if (!empty($column['column_default'])) {
        switch ($driver) {
            case 'pgsql':
                if (preg_match('/^\'(.*?)\'::/', $column['column_default'], $match)) {
                    $default = $match[1];
                }
                break;
            case 'mysql':
                if (strpos($column['column_default'], 'CURRENT_TIMESTAMP') !== 0) {
                    $default = $column['column_default'];
                }
        }
    }

    $properties[$column['column_name']] = [
            'type'    => $type,
            'default' => $default,
    ];
}

$object_name = rtrim(str_replace(' ', '', ucwords(str_replace('_', ' ', $opts['table']))), 's');


create_value_object($properties, $opts['namespace'], $object_name, $opts['base-class'], $opts['schema'], $opts['table'], $opts['dir']);
create_mapper($properties, $opts['namespace'], $object_name, $opts['base-class'], $opts['schema'], $opts['table'], $opts['dir'], $primary_key, $opts['db']);

function create_value_object($properties, $namespace, $object_name, $base_class, $schema, $table, $dir): void {

    if (!empty($base_class)) {
        $base_class = " extends $base_class";
    }

    $file = "<?php\n\n";

    $file .= "namespace $namespace\\Data;\n\n";

    $file .= "/**\n";
    $file .= " * Value object for $schema.$table\n";
    $file .= " *\n";
    $file .= " * @package $namespace\n";
    $file .= " */\n";

    $file .= "class $object_name{$base_class} {\n\n";

    $has_datetime = false;

    foreach ($properties as $name => $settings) {

        if ($settings['default'] === null) {
            $default = 'null';
        } elseif ($settings['type'] === 'string' || $settings['type'] === '?string') {
            $default = "'{$settings['default']}'";
        } else {
            $default = $settings['default'];
        }

        $file .= "    /**\n";
        $file .= "     * @var {$settings['type']}\n";
        $file .= "     */\n";
        if (strpos($settings['type'], 'DateTime') !== false) {
            $has_datetime = true;
            $file         .= "    public {$settings['type']} \$$name;\n\n";
        } else {
            $file .= "    public {$settings['type']} \$$name = $default;\n\n";
        }
    }

    if ($has_datetime) {

        $file .= "    /**\n";
        $file .= "     * Initialize properties that are objects\n";
        $file .= "     */\n";
        $file .= "    public function __construct() {\n";
        foreach ($properties as $name => $settings) {
            if (strpos($settings['type'], 'DateTime') !== false) {
                $file .= "        \$this->$name = new \\DateTime();\n";
            }
        }
        $file .= "    }\n";

    }

    $file .= "}\n";

    if (!file_exists("$dir/Data")) {
        mkdir("$dir/Data", recursive: true);
    }

    file_put_contents("$dir/Data/$object_name.php", $file);
}

function create_mapper($properties, $namespace, $object_name, $base_class, $schema, $table, $dir, $primary_key, $db): void {

    $file = "<?php\n";
    $file .= "\n";
    $file .= "namespace $namespace\\Mapper;\n";
    $file .= "\n";
    $file .= "class $object_name extends \\DealNews\\DB\\AbstractMapper {\n";
    $file .= "\n";
    $file .= "    /**\n";
    $file .= "     * Database name\n";
    $file .= "     */\n";
    $file .= "    public const DATABASE_NAME = '$db';\n";
    $file .= "\n";
    $file .= "    /**\n";
    $file .= "     * Table name\n";
    $file .= "     */\n";
    $file .= "    public const TABLE = '$table';\n";
    $file .= "\n";
    $file .= "    /**\n";
    $file .= "     * Table primary key column name\n";
    $file .= "     */\n";
    $file .= "    public const PRIMARY_KEY = '$primary_key';\n";
    $file .= "\n";
    $file .= "    /**\n";
    $file .= "     * Name of the class the mapper is mapping\n";
    $file .= "     */\n";
    $file .= "    public const MAPPED_CLASS = \\$namespace\\Data\\$object_name::class;\n";
    $file .= "\n";
    $file .= "    /**\n";
    $file .= "     * Defines the properties that are mapped and any\n";
    $file .= "     * additional information needed to map them.\n";
    $file .= "     */\n";
    $file .= "    protected const MAPPING = [\n";
    foreach (array_keys($properties) as $name) {
        $file .= "        '$name' => [],\n";
    }
    $file .= "    ];\n";
    $file .= "}\n";

    if (!file_exists("$dir/Mapper")) {
        mkdir("$dir/Mapper", recursive: true);
    }

    file_put_contents("$dir/Mapper/$object_name.php", $file);
}
