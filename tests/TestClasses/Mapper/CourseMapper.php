<?php

namespace DealNews\DB\Tests\TestClasses\Mapper;

/**
 * Test Course Mapper
 *
 * @author      Brian Moon <brianm@dealnews.com>
 * @copyright   1997-Present DealNews.com, Inc
 * @package     DataMapper
 */
class CourseMapper extends \DealNews\DB\AbstractMapper {

    /**
     * Database configuration name
     */
    public const DATABASE_NAME = 'testdb';

    /**
     * Table name
     */
    public const TABLE = 'courses';

    /**
     * Table primary key column name
     */
    public const PRIMARY_KEY = 'course_id';

    public const MAPPED_CLASS = "\DealNews\DB\Tests\TestClasses\Course";

    public const MAPPING = [
        'course_id' => [],
        'name'      => [],
    ];
}
