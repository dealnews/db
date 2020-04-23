<?php

namespace DealNews\DB\Tests\TestClasses\Mapper;

class BadCourseMapper extends \DealNews\DB\AbstractMapper {

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

    public const MAPPED_CLASS = "\DealNews\DB\Tests\TestClasses\BadClass";

    public const MAPPING = [
        'course_id' => [],
        'name'      => [],
        'bad_field' => [],
    ];
}
