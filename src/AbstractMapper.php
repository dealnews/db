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
    protected const MAPPING = [];

    /**
     * CRUD PDO helper object
     * @var \DealNews\DB\CRUD
     */
    protected $crud;

    /**
     * Creates a new mapper
     * @param \DealNews\DB\CRUD|null $crud Optional CRUD object
     */
    public function __construct(CRUD $crud = null) {
        if ($crud !== null) {
            $this->crud = $crud;
        } elseif (!empty($this::DATABASE_NAME)) {
            $this->crud = new CRUD(\DealNews\DB\Factory::init($this::DATABASE_NAME));
        } else {
            throw new \LogicException('No database configuration for ' . get_class($this));
        }
    }

    /**
     * Loads an object from the database
     * @param  int|string    $id Primay key id of the object to load
     * @return boolean|object
     * @throws \Error
     */
    public function load($id) {
        $object = false;

        $data = $this->find([$this::PRIMARY_KEY => $id]);

        if (!empty($data)) {
            $object = reset($data);
        }

        return $object;
    }

    /**
     * Loads multiple objects from the database
     * @param  array    $ids Array of primay key ids of the objects to load
     * @return boolean|array
     * @throws \Error
     */
    public function loadMulti(array $ids) {
        return $this->find([$this::PRIMARY_KEY => $ids]);
    }

    /**
     * Finds multiple objects in the database
     * @param  array    $filter Array of filters where the keys are column
     *                          names and the values are column values to
     *                          filter upon.
     * @return boolean|array
     * @throws \Error
     */
    public function find(array $filter) {
        $objects = false;

        $data = $this->crud->read($this::TABLE, $filter);

        if (!empty($data)) {
            foreach ($data as $row) {
                $object = $this->setData($row);
                $object = $this->loadRelations($object);

                $objects[$this->getValue($object, $this::PRIMARY_KEY, $this::MAPPING[$this::PRIMARY_KEY])] = $object;
            }
        }

        return $objects;
    }

    /**
     * Saves the object to the database
     * @return boolean
     * @throws \Error
     */
    public function save($object) {
        $data = $this->getData($object);

        if (array_key_exists($this::PRIMARY_KEY, $data)) {
            unset($data[$this::PRIMARY_KEY]);
        }

        $already_in_transaction = $this->crud->pdo->inTransaction();

        if (!$already_in_transaction) {
            $this->crud->pdo->beginTransaction();
        }

        try {
            if ($this->getValue($object, $this::PRIMARY_KEY, $this::MAPPING[$this::PRIMARY_KEY]) == 0) {
                $success = $this->crud->create($this::TABLE, $data);
                if ($success) {
                    $this->setValue(
                        $object,
                        $this::PRIMARY_KEY,
                        [$this::PRIMARY_KEY => $this->crud->pdo->lastInsertId($this::SEQUENCE_NAME)],
                        $this::MAPPING[$this::PRIMARY_KEY]
                    );
                }
            } else {
                $success = $this->crud->update(
                    $this::TABLE,
                    $data,
                    [
                        $this::PRIMARY_KEY => $object->{$this::PRIMARY_KEY},
                    ]
                );
            }

            if ($success) {
                $object = $this->saveRelations($object);
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
     * @return boolean
     * @throws \Error
     */
    public function delete($id) {
        $success = $this->crud->delete(
            $this::TABLE,
            [
                $this::PRIMARY_KEY => $id,
            ]
        );

        return $success;
    }

    /**
     * Loads the relational objects
     * @param  object $object Object to which to add the relations
     * @return object
     */
    protected function loadRelations($object) {
        foreach ($this::MAPPING as $property => $mapping) {
            if (!empty($mapping['mapper']) && !empty($mapping['lookup'])) {
                $objects = [];
                $rows    = $this->crud->read(
                    $mapping['lookup']['table'],
                    [
                        $mapping['lookup']['foreign_column'] => $object->{$this::PRIMARY_KEY},
                    ]
                );

                if (!empty($rows)) {
                    $mapper = new $mapping['mapper']();
                    foreach ($rows as $row) {
                        $objects[] = $mapper->load($row[$mapping['lookup']['mapper_column']]);
                    }
                }
                $this->setValue(
                    $object,
                    $property,
                    [$property => $objects],
                    $mapping
                );
            }
        }

        return $object;
    }

    /**
     * Saves relations
     *
     * @param  object $object Object containing the relations
     * @return object
     */
    protected function saveRelations($object) {
        foreach ($this::MAPPING as $property => $mapping) {
            if (!empty($mapping['mapper']) && !empty($mapping['lookup'])) {

                // load values in the lookup table
                $rows = $this->crud->read(
                    $mapping['lookup']['table'],
                    [
                        $mapping['lookup']['foreign_column'] => $this->getValue($object, $this::PRIMARY_KEY, $this::MAPPING[$this::PRIMARY_KEY]),
                    ]
                );

                // save current object values
                $mapper = new $mapping['mapper']();

                $objects = $this->getValue($object, $property, $mapping);

                foreach ($objects as $key => $obj) {
                    $objects[$key] = $mapper->save($obj);
                }

                $this->setValue(
                    $object,
                    $property,
                    [
                        $property => $objects,
                    ],
                    $mapping
                );

                // determine how to alter the lookup table
                $add = [];
                foreach ($objects as $obj) {
                    $found = false;
                    foreach ($rows as $key => $row) {
                        if ($row[$mapping['lookup']['mapper_column']] == $obj->{$mapper::PRIMARY_KEY}) {
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
                        $pk_values[] = $row[$mapping['lookup']['primary_key']];
                    }
                    $this->crud->delete(
                        $mapping['lookup']['table'],
                        [
                            $mapping['lookup']['primary_key'] => $pk_values,
                        ]
                    );
                }

                if (!empty($add)) {
                    // add new relational data
                    foreach ($add as $obj) {
                        $this->crud->create(
                            $mapping['lookup']['table'],
                            [
                                $mapping['lookup']['mapper_column']  => $obj->{$mapper::PRIMARY_KEY},
                                $mapping['lookup']['foreign_column'] => $this->getValue($object, $this::PRIMARY_KEY, $this::MAPPING[$this::PRIMARY_KEY]),
                            ]
                        );
                    }
                }
            }
        }

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
    protected function getData($object) {
        $data = [];
        foreach ($this::MAPPING as $property => $mapping) {
            if (empty($mapping['mapper'])) {
                $data[$property] = $this->getValue($object, $property, $mapping);
            }
        }

        return $data;
    }
}
