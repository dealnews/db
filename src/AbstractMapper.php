<?php

namespace DealNews\DB;

/**
 * Maps an object to a database accesible via PDO
 *
 * @author      Brian Moon <brianm@dealnews.com>
 * @copyright   1997-Present DealNews.com, Inc
 * @package     DataMapper
 */
abstract class AbstractMapper extends \DealNews\DataMapper\AbstractMapper {

    /**
     * Database configuration name
     */
    public const DATABASE_NAME = '';

    /**
     * Table name
     */
    public const TABLE = '';

    /**
     * Table primary key column name
     */
    public const PRIMARY_KEY = '';

    /**
     * Sequence name for DBMS that use sequences
     */
    public const SEQUENCE_NAME = null;

    /**
     * Value sent to get_column_name to indicate data is being read
     */
    public const MODE_READ  = 'READ';

    /**
     * Value sent to get_column_name to indicate data is being written
     */
    public const MODE_WRITE = 'WRITE';

    /**
     * Primititve types
     */
    public const PRIMITIVE_TYPES = [
        'array',
        'boolean',
        'double',
        'integer',
        'string',
    ];

    /**
     * Name of the class the mapper is mapping
     */
    public const MAPPED_CLASS = '';

    /**
     * Defines the properties that are mapped and any
     * additional information needed to map them.
     */
    public const MAPPING = [];

    /**
     * Table name including any prefix from the ini config
     *
     * @var string
     */
    public readonly string $table;

    /**
     * CRUD PDO helper object
     * @var \DealNews\DB\CRUD
     */
    protected CRUD $crud;

    /**
     * Creates a new mapper
     *
     * @param \DealNews\DB\CRUD|null $crud Optional CRUD object
     */
    public function __construct(?CRUD $crud = null) {
        if ($crud !== null) {
            $this->crud = $crud;
        } elseif (!empty($this::DATABASE_NAME)) {
            $this->crud = CRUD::factory($this::DATABASE_NAME);
        } else {
            throw new \LogicException('No database configuration for ' . get_class($this));
        }
        $prefix = Factory::getConfigValue($this::DATABASE_NAME, 'table_prefix');
        if ($prefix) {
            $this->table = "{$prefix}_" . $this::TABLE;
        } else {
            $this->table = $this::TABLE;
        }
    }

    /**
     * Loads an object from the database
     *
     * @param  int|string    $id Primay key id of the object to load
     *
     * @return ?object
     *
     * @throws \Error
     */
    public function load($id): ?object {
        $object = null;

        $data = $this->find([$this::PRIMARY_KEY => $id]);

        if (!empty($data)) {
            $object = reset($data);
        }

        return $object;
    }

    /**
     * Loads multiple objects from the database
     *
     * @param  array    $ids Array of primay key ids of the objects to load
     *
     * @return null|array
     *
     * @throws \Error
     */
    public function loadMulti(array $ids): ?array {
        return $this->find([$this::PRIMARY_KEY => $ids]);
    }

    /**
     * Finds multiple objects in the database
     *
     * @param      array       $filter  Array of filters where the keys are column
     *                                  names and the values are column values to
     *                                  filter upon.
     * @param      int|null    $limit   Number of matches to return
     * @param      int|null    $start   Start position
     * @param      string      $order   The order of returned matches
     *
     * @return     array|null
     */
    public function find(array $filter, ?int $limit = null, ?int $start = null, string $order = ''): ?array {
        $objects = null;

        $data = $this->crud->read($this->table, $filter, $limit, $start, order: $order);

        if (!empty($data)) {
            foreach ($data as $row) {
                $object = $this->setData($row);
                $object = $this->loadRelations($object);
                // @phan-suppress-next-line PhanTypeArraySuspicious, PhanTypeInvalidDimOffset
                $objects[$this->getValue($object, $this::PRIMARY_KEY, $this::MAPPING[$this::PRIMARY_KEY])] = $object;
            }
        }

        return $objects;
    }

    /**
     * Saves an object and returns it
     *
     * @param      object  $object  The object to save
     *
     * @return     object
     */
    public function save($object): object {
        $already_in_transaction = $this->crud->pdo->inTransaction();

        if (!$already_in_transaction) {
            $this->crud->pdo->beginTransaction();
        }

        try {
            $update_constraint = $this->getUpdateConstraint($object);
            $data              = $this->getData($object);

            $insert = false;

            // @phan-suppress-next-line PhanTypeArraySuspicious, PhanTypeInvalidDimOffset
            if ($this->getValue($object, $this::PRIMARY_KEY, $this::MAPPING[$this::PRIMARY_KEY]) == 0) {
                $insert = true;
                if (array_key_exists($this::PRIMARY_KEY, $data)) {
                    unset($data[$this::PRIMARY_KEY]);
                }
            } else {
                $existing = $this->crud->read(
                    $this->table,
                    [
                        // @phan-suppress-next-line PhanTypeArraySuspicious, PhanTypeInvalidDimOffset
                        $this::PRIMARY_KEY => $this->getValue($object, $this::PRIMARY_KEY, $this::MAPPING[$this::PRIMARY_KEY]),
                    ]
                );
                if (empty($existing)) {
                    $insert = true;
                }
            }

            if ($insert) {
                $success = $this->crud->create($this->table, $data);
                if ($success) {
                    $this->setValue(
                        $object,
                        $this::PRIMARY_KEY,
                        [$this::PRIMARY_KEY => $this->crud->pdo->lastInsertId($this::SEQUENCE_NAME)],
                        // @phan-suppress-next-line PhanTypeArraySuspicious, PhanTypeInvalidDimOffset
                        $this::MAPPING[$this::PRIMARY_KEY]
                    );
                }
            } else {
                $success = $this->crud->update(
                    $this->table,
                    $data,
                    $update_constraint
                );
            }

            if ($success) {
                $object = $this->saveRelations($object);
                // @phan-suppress-next-line PhanTypeArraySuspicious, PhanTypeInvalidDimOffset
                $object = $this->load($this->getValue($object, $this::PRIMARY_KEY, $this::MAPPING[$this::PRIMARY_KEY]));
            }
        } catch (\PDOException $e) {
            if (!$already_in_transaction) {
                $this->crud->pdo->rollBack();
            }
            throw $e;
        }

        if (!$already_in_transaction) {
            if ($success) {
                $this->crud->pdo->commit();
            } else {
                $this->crud->pdo->rollBack();
            }
        }

        return $object;
    }

    /**
     * Deletes an object
     *
     * @param      int|string  $id     The unique id
     *
     * @return     bool
     */
    public function delete($id): bool {
        $success = $this->crud->delete(
            $this->table,
            [
                $this::PRIMARY_KEY => $id,
            ]
        );

        return $success;
    }

    /**
     * Loads the relational objects
     *
     * @param      object  $object  The object
     *
     * @return     object
     */
    protected function loadRelations(object $object): object {
        foreach ($this::MAPPING as $property => $mapping) {
            if (!empty($mapping['mapper'])) {
                if (
                    is_a($mapping['mapper'], ColumnMapper::class, true) ||
                    is_subclass_of($mapping['mapper'], ColumnMapper::class, true)
                ) {
                    $object = $this->loadColumnMapper(
                        $object,
                        $property,
                        $mapping
                    );
                } elseif (!empty($mapping['type']) && $mapping['type'] == 'lookup') {
                    $object = $this->loadLookupObjects(
                        $object,
                        $property,
                        $mapping
                    );
                } else {
                    $object = $this->loadRelatedObjects(
                        $object,
                        $property,
                        $mapping
                    );
                }
            }
        }

        return $object;
    }

    /**
     * Loads related objects.
     *
     * @param      object  $object    The object
     * @param      string  $property  The property
     * @param      array   $mapping   The mapping
     *
     * @return     object
     */
    protected function loadRelatedObjects(object $object, string $property, array $mapping): object {
        $mapper  = new $mapping['mapper']();
        $objects = $mapper->find(
            [
                // @phan-suppress-next-line PhanTypeArraySuspicious, PhanTypeInvalidDimOffset
                $mapping['foreign_column'] => $this->getValue($object, $this::PRIMARY_KEY, $this::MAPPING[$this::PRIMARY_KEY]),
            ]
        );

        if (!empty($objects)) {
            $this->setValue(
                $object,
                $property,
                [$property => array_values($objects)],
                $mapping
            );
        }

        return $object;
    }

    /**
     * Loads relational objects that are related via a lookup (aka xref) table
     *
     * @param      object  $object    The object
     * @param      string  $property  The property
     * @param      array   $mapping   The mapping
     *
     * @return     object
     */
    protected function loadLookupObjects(object $object, string $property, array $mapping): object {
        $objects = [];
        $rows    = $this->crud->read(
            $mapping['table'],
            [
                // @phan-suppress-next-line PhanTypeArraySuspicious, PhanTypeInvalidDimOffset
                $mapping['foreign_column'] => $this->getValue($object, $this::PRIMARY_KEY, $this::MAPPING[$this::PRIMARY_KEY]),
            ]
        );

        if (!empty($rows)) {
            $mapper = new $mapping['mapper']();
            foreach ($rows as $row) {
                $objects[] = $mapper->load($row[$mapping['mapper_column']]);
            }
        }
        $this->setValue(
            $object,
            $property,
            [$property => $objects],
            $mapping
        );

        return $object;
    }

    /**
     * Loads a column mapper.
     *
     * @param      object    $object    The object
     * @param      string    $property  The property
     * @param      array     $mapping   The mapping
     *
     * @return     object
     */
    protected function loadColumnMapper(object $object, string $property, array $mapping): object {
        $mapper = new ($mapping['mapper'])(
            $mapping['table'],
            $mapping['primary_key'],
            $mapping['foreign_column'],
            $mapping['column'],
            $this->crud
        );

        // @phan-suppress-next-line PhanTypeArraySuspicious, PhanTypeInvalidDimOffset
        $data = $mapper->load($this->getValue($object, $this::PRIMARY_KEY, $this::MAPPING[$this::PRIMARY_KEY]));

        $this->setValue(
            $object,
            $property,
            [$property => $data],
            $mapping
        );

        return $object;
    }

    /**
     * Saves relations
     *
     * @param  object $object Object containing the relations
     *
     * @return object
     */
    protected function saveRelations(object $object): object {
        foreach ($this::MAPPING as $property => $mapping) {
            if (!empty($mapping['mapper'])) {

                if (
                    is_a($mapping['mapper'], ColumnMapper::class, true) ||
                    is_subclass_of($mapping['mapper'], ColumnMapper::class, true)
                ) {
                    $object = $this->saveColumnMapper($object, $property, $mapping);
                } else {
                    if (!empty($mapping['type'])) {
                        if ($mapping['type'] == 'lookup') {
                            $object = $this->saveLookupRelations($object, $property, $mapping);
                        }
                    } else {
                        $object = $this->saveRelationalObjects($object, $property, $mapping);
                    }
                }
            }
        }

        return $object;
    }

    /**
     * Saves relational data that is stored in a lookup (aka xref) table.
     *
     * @param      object  $object    The object
     * @param      string  $property  The property
     * @param      array   $mapping   The mapping
     *
     * @return     object
     */
    protected function saveLookupRelations(object $object, string $property, array $mapping): object {
        $mapper = new $mapping['mapper']();

        $objects = $this->getValue($object, $property, $mapping);

        // load values in the lookup table
        $rows = $this->crud->read(
            $mapping['table'],
            [
                // @phan-suppress-next-line PhanTypeArraySuspicious, PhanTypeInvalidDimOffset
                $mapping['foreign_column'] => $this->getValue($object, $this::PRIMARY_KEY, $this::MAPPING[$this::PRIMARY_KEY]),
            ]
        );

        // determine how to alter the lookup table
        $add = [];
        foreach ($objects as $obj) {
            $found = false;
            foreach ($rows as $key => $row) {
                if ($row[$mapping['mapper_column']] == $obj->{$mapper::PRIMARY_KEY}) {
                    // found a match. remove it from the db rows
                    $found = true;
                    unset($rows[$key]);
                    break;
                }
            }
            if (!$found) {
                // if we don't find a match, add the object
                $add[] = $obj;
            }
        }

        if (!empty($rows)) {
            // delete lookup rows that are no longer needed
            $pk_values = [];
            foreach ($rows as $row) {
                $pk_values[] = $row[$mapping['primary_key']];
            }
            $this->crud->delete(
                $mapping['table'],
                [
                    $mapping['primary_key'] => $pk_values,
                ]
            );
        }

        if (!empty($add)) {

            // add new relational data
            foreach ($add as $obj) {
                $this->crud->create(
                    $mapping['table'],
                    [
                        $mapping['mapper_column']  => $obj->{$mapper::PRIMARY_KEY},
                        // @phan-suppress-next-line PhanTypeArraySuspicious, PhanTypeInvalidDimOffset
                        $mapping['foreign_column'] => $this->getValue($object, $this::PRIMARY_KEY, $this::MAPPING[$this::PRIMARY_KEY]),
                    ]
                );
            }
        }

        return $object;
    }

    /**
     * Saves relational objects.
     *
     * @param      object  $object    The object
     * @param      string  $property  The property
     * @param      array   $mapping   The mapping
     *
     * @return     object
     */
    protected function saveRelationalObjects(object $object, string $property, array $mapping): object {

        // save current object values
        $mapper = new $mapping['mapper']();

        foreach ($object->$property as $key => $obj) {
            $prop = $mapping['foreign_property'] ?? $mapping['foreign_column'] ?? null;
            if (property_exists($obj, $prop)) {
                // @phan-suppress-next-line PhanTypeArraySuspicious, PhanTypeInvalidDimOffset
                $obj->{$prop} = $this->getValue($object, $this::PRIMARY_KEY, $this::MAPPING[$this::PRIMARY_KEY]);
            }

            ($object->$property)[$key] = $mapper->save($obj);
        }

        $this->setValue(
            $object,
            $property,
            [
                $property => $object->$property,
            ],
            $mapping
        );

        return $object;
    }

    protected function saveColumnMapper(object $object, string $property, array $mapping): object {
        $mapper = new ($mapping['mapper'])(
            $mapping['table'],
            $mapping['primary_key'],
            $mapping['foreign_column'],
            $mapping['column'],
            $this->crud
        );

        $data = $this->getValue($object, $property, $mapping);

        $data = $mapper->save(
            // @phan-suppress-next-line PhanTypeArraySuspicious, PhanTypeInvalidDimOffset
            $this->getValue($object, $this::PRIMARY_KEY, $this::MAPPING[$this::PRIMARY_KEY]),
            $data
        );

        $this->setValue(
            $object,
            $property,
            [$property => $data],
            $mapping
        );

        return $object;
    }

    /**
     * Builds a data array for insertion into the database using the object
     * properties. This can be overridden by a child class when more complex
     * work needs to be done.
     *
     * @param object $object
     *
     * @return array
     */
    protected function getData($object): array {
        $data = [];
        foreach ($this::MAPPING as $property => $mapping) {
            if (empty($mapping['mapper'])) {
                $data[$property] = $this->getValue($object, $property, $mapping);
            }
        }

        return $data;
    }

    /**
     * Returns the "where" data to use when updating a row
     *
     * @param      object  $object  The object
     *
     * @return     array   The update constraint.
     */
    protected function getUpdateConstraint(object $object): array {
        return [
            $this::PRIMARY_KEY => $object->{$this::PRIMARY_KEY},
        ];
    }
}
