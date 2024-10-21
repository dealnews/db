<?php

namespace DealNews\DB;

use \DealNews\GetConfig\GetConfig;

/**
 * Database Object Factory
 *
 * This class uses GetConfig to create a PDO object. The ini file used
 * by GetConfig should specify the database type; one of mysql, pgsql, or pdo.
 * If the type is not defined in the ini file, mysql is assumed. The type
 * can be passed in when the ini file is not accurate.
 *
 * @author      Brian Moon <brianm@dealnews.com>
 * @copyright   1997-Present DealNews.com, Inc
 * @package     DB
 */
class Factory {

    const DEFAULT_CONFIG_PREFIX = 'db';

    /**
     * Creates a new PDO connection or returns one that already exists
     *
     * @param  string      $db      Name of the database configuration. This may
     *                              or may not be the same as the database name.
     * @param  array|null  $options Array of additional options to pass to the
     *                              PDO constructor
     * @param  string|null $type    Optional database type that will override the
     *                              type in the configuration file
     *
     * @return PDO
     *
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \LogicException
     */
    public static function init(string $db, ?array $options = null, ?string $type = null): PDO {
        static $objs = [];

        if (!empty($type)) {
            $key = $type . '.' . $db;
        } else {
            $key = $db;
        }
        if (!empty($options)) {
            $key .= '.' . md5(json_encode($options));
        }

        if (!isset($objs[$key])) {
            $objs[$key] = false;
            $obj        = self::build(self::loadConfig(self::getConfig($db), $options, $type));
            if ($obj !== false) {
                $objs[$key] = $obj;
            }
        }

        return $objs[$key];
    }

    /**
     * Creates a new PDO object
     *
     * @param  array $config Configuration array returned by load_config
     *
     * @return PDO
     *
     * @throws \PDOException
     */
    public static function build(array $config): PDO {
        $obj = new PDO(
            $config['dsn'],
            $config['user'],
            $config['pass'],
            $config['options']
        );

        return $obj;
    }

    /**
     * Loads the db config from the ini file.
     *
     * @param  array        $config Configuration array returned by get_config
     * @param  array|null   $options Array of additional options to pass to the
     *                               PDO constructor
     * @param  string|null  $type    Optional database type that will override the
     *
     * @return array
     * @throws \LogicException
     * @throws \UnexpectedValueException
     */
    public static function loadConfig(array $config, ?array $options = null, ?string $type = null): array {
        if (empty($config['server']) && empty($config['dsn'])) {
            throw new \LogicException('Either `server` or `dsn` is required', 3);
        } elseif (!empty($config['server'])) {
            $config['server'] = explode(',', $config['server']);
        }

        // set type to the passed in value, what is in the config, or mysql
        $config['type'] = $type ?? $config['type'] ?? 'mysql';

        if (!empty($config['options'])) {
            $config['options'] = json_decode($config['options'], true);
            $err               = json_last_error();
            if ($err !== JSON_ERROR_NONE) {
                throw new \UnexpectedValueException('Invalid value for options', 4);
            }
        } else {
            $config['options'] = [];
        }

        if (!empty($options)) {
            $config['options'] = $config['options'] + $options;
        }

        switch ($config['type']) {
            case 'mysql':
            case 'pgsql':
                if (empty($config['db'])) {
                    throw new \UnexpectedValueException("A database name is required for `{$config['type']}` connections", 5);
                }
                $servers = $config['server'];
                shuffle($servers);
                $server        = array_shift($servers);
                $config['dsn'] = "{$config['type']}:host={$server};" .
                            "port={$config['port']};" .
                            "dbname={$config['db']}";

                if (empty($config['charset']) && $config['type'] == 'mysql') {
                    $config['charset'] = 'utf8mb4';
                }

                if (!empty($config['charset'])) {
                    $config['dsn'] .= ";charset={$config['charset']}";
                }
                break;
            case 'pdo':
                if (empty($config['dsn'])) {
                    throw new \UnexpectedValueException('A DSN is required for PDO connections', 1);
                }
                break;
            default:
                throw new \UnexpectedValueException("Invalid database type `{$config['type']}`", 2);
        }

        return $config;
    }

    /**
     * Loads the db config from the ini file.
     *
     * @param  string         $db      Database config name
     * @param  GetConfig|null $cfg     Optional GetConfig object for testing
     *
     * @return array
     * @throws \LogicException
     */
    public static function getConfig(string $db, ?GetConfig $cfg = null): array {
        if (empty($cfg)) {
            $cfg = new GetConfig();
        }

        // Check for an altername environment for this db
        $prefix = $cfg->get('db.factory.prefix');

        if (empty($prefix)) {
            $prefix = static::DEFAULT_CONFIG_PREFIX;
        }

        if (!empty($prefix)) {
            $prefix .= '.';
        }

        $config = [
            'type'        => $cfg->get($prefix . "$db.type"),
            'db'          => $cfg->get($prefix . "$db.db"),
            'user'        => $cfg->get($prefix . "$db.user"),
            'pass'        => $cfg->get($prefix . "$db.pass"),
            // PDO only
            'dsn'         => $cfg->get($prefix . "$db.dsn"),
            'options'     => $cfg->get($prefix . "$db.options"),
            // pgsql and mysql only
            'server'      => $cfg->get($prefix . "$db.server"),
            'port'        => $cfg->get($prefix . "$db.port"),
            // mysql only
            'charset'     => $cfg->get($prefix . "$db.charset"),
        ];

        if (empty($config['db'])) {
            $config['db'] = $db;
        }

        return $config;
    }
}
