<?php

namespace DealNews\DB;

/**
 * Loads/saves a single column to/from an external table into an array
 *
 * @author      Brian Moon <brianm@dealnews.com>
 * @copyright   1997-Present DealNews.com, Inc
 * @package     DealNews\DB
 */
class ColumnMapper {

    /**
     * CRUD PDO helper object
     * @var \DealNews\DB\CRUD
     */
    protected CRUD $crud;

    protected string $table = '';
    protected string $primary_key = '';
    protected string $foreign_column = '';
    protected string $column = '';


    /**
     * Creates a new mapper
     *
     * @param \DealNews\DB\CRUD|null $crud Optional CRUD object
     */
    public function __construct(
        string $table,
        string $primary_key,
        string $foreign_column,
        string $column,
        CRUD $crud = null
    ) {

        $this->table          = $table;
        $this->primary_key    = $primary_key;
        $this->foreign_column = $foreign_column;
        $this->column         = $column;

        if ($crud !== null) {
            $this->crud = $crud;
        } elseif (!empty($this::DATABASE_NAME)) {
            $this->crud = new CRUD(\DealNews\DB\Factory::init($this::DATABASE_NAME));
        } else {
            throw new \LogicException('No database configuration for ' . get_class($this));
        }
    }

    /**
     * Loads the data from the database
     *
     * @param  int|string    $id Primay key id of the object to look up
     *
     * @return array
     */
    public function load(int|string $id): array {
        $data = [];

        $rows = $this->crud->read(
            $this->table,
            [
                $this->foreign_column => $id
            ],
            order: $this->column
        );

        foreach ($rows as $row) {
            $data[] = $row[$this->column];
        }

        return $data;
    }

    /**
     * Saves the data
     *
     * @param  int|string    $id    Primay key id of the object to look up
     * @param  array         $data  Values to save
     *
     * @return array
     */
    public function save(int|string $id, array $data): array {
        $already_in_transaction = $this->crud->pdo->inTransaction();

        if (!$already_in_transaction) {
            $this->crud->pdo->beginTransaction();
        }

        try {

            $existing = $this->crud->read(
                $this->table,
                [
                    $this->foreign_column => $id
                ]
            );

            foreach ($data as $dk => $value) {
                foreach ($existing as $ek => $row) {
                    if ($row[$this->column] == $value) {
                        unset($data[$dk]);
                        unset($existing[$ek]);
                        break;
                    }
                }
            }

            foreach ($data as $value) {
                $this->crud->create(
                    $this->table,
                    [
                        $this->column         => $value,
                        $this->foreign_column => $id,
                    ]
                );
            }

            foreach ($existing as $ex) {
                $this->crud->delete(
                    $this->table,
                    [
                        $this->primary_key => $ex[$this->primary_key],
                    ]
                );
            }

            $data = $this->load($id);

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

        return $data;
    }
}
