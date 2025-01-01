<?php

namespace DealNews\DB;

/**
 * DB Repository
 *
 * Serves as a base repository for DB DataMappers
 *
 * @author      Brian Moon <brianm@dealnews.com>
 * @copyright   1997-Present DealNews.com, Inc
 * @package     DB
 *
 * @phan-suppress PhanUnreferencedClass
 */
class Repository extends \DealNews\DataMapper\Repository {

    /**
     * Returns objects matching the filters. This method does not check
     * the Repository storage for data. The data is returned from the Mapper.
     * The data is stored in the Repository storage however after it is
     * retrieved.
     *
     * @param  string $name    Mapped Object name
     * @param  array  $filters Array of filters. See \DealNews\DB\AbstractMapper::find()
     *
     * @return boolean|array
     */
    public function find(string $name, array $filters, ?int $limit = null, ?int $start = null, array $fields = ['*'], string $order = ''): bool|array {
        $mapper = $this->getMapper($name);
        $data   = $mapper->find($filters, $limit, $start, $fields, $order);
        if (!empty($data)) {
            $this->setMulti($name, $data);
        }

        return $data;
    }
}
