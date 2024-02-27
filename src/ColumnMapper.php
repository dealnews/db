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

    /**
     * The table with the values
     */
    protected string $table = '';

    /**
     * The primary key for the table
     */
    protected string $primary_key = '';

    /**
     * The column that has the foreign object pk in it
     */
    protected string $foreign_column = '';

    /**
     * The column we want to return in the array
     */
    protected string $column = '';


    /**
     * Constructs a new instance.
     *
     * @param      string  $table           The table
     * @param      string  $primary_key     The primary key
     * @param      string  $foreign_column  The foreign column
     * @param      string  $column          The column
     * @param      CRUD    $crud            The crud
     */
    public function __construct(
        string $table,
        string $primary_key,
        string $foreign_column,
        string $column,
        CRUD $crud
    ) {

        $this->table          = $table;
        $this->primary_key    = $primary_key;
        $this->foreign_column = $foreign_column;
        $this->column         = $column;
        $this->crud           = $crud;
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
                $this->foreign_column => $id,
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
        $success = true;

        $already_in_transaction = $this->crud->pdo->inTransaction();

        if (!$already_in_transaction) {
            $this->crud->pdo->beginTransaction();
        }

        try {

            $existing = $this->crud->read(
                $this->table,
                [
                    $this->foreign_column => $id,
                ]
            );

            foreach ($data as $dk => $value) {
                foreach ($existing as $ek => $row) {
                    if ($row[$this->column] == $value) {
                        unset($data[$dk], $existing[$ek]);

                        break;
                    }
                }
            }

            foreach ($data as $value) {
                $success = $this->crud->create(
                    $this->table,
                    [
                        $this->column         => $value,
                        $this->foreign_column => $id,
                    ]
                );
                if (!$success) {
                    break;
                }
            }

            if ($success) {
                foreach ($existing as $ex) {
                    $success = $this->crud->delete(
                        $this->table,
                        [
                            $this->primary_key => $ex[$this->primary_key],
                        ]
                    );
                    if (!$success) {
                        break;
                    }
                }
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

        $data = $this->load($id);

        return $data;
    }
}
