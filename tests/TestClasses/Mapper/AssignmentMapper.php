<?php

namespace DealNews\DB\Tests\TestClasses\Mapper;

/**
 * Test Assignment Mapper
 *
 * @author      Brian Moon <brianm@dealnews.com>
 * @copyright   1997-Present DealNews.com, Inc
 * @package     DataMapper
 */
class AssignmentMapper extends \DealNews\DB\AbstractMapper {

    /**
     * Database configuration name
     */
    public const DATABASE_NAME = 'testdb';

    /**
     * Table name
     */
    public const TABLE = 'assignments';

    /**
     * Table primary key column name
     */
    public const PRIMARY_KEY = 'assignment_id';

    public const MAPPED_CLASS = '\\DealNews\\DB\\Tests\\TestClasses\\Assignment';

    public const MAPPING = [
        'assignment_id' => [],
        'student_id'    => [],
        'name'          => [],
    ];
}
