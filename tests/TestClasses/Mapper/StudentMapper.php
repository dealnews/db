<?php

namespace DealNews\DB\Tests\TestClasses\Mapper;

/**
 * Test Student Class
 *
 * @author      Brian Moon <brianm@dealnews.com>
 * @copyright   1997-Present DealNews.com, Inc
 * @package     DataMapper
 */
class StudentMapper extends \DealNews\DB\AbstractMapper {

    /**
     * Database configuration name
     */
    public const DATABASE_NAME = 'testdb';

    /**
     * Table name
     */
    public const TABLE = 'students';

    /**
     * Table primary key column name
     */
    public const PRIMARY_KEY = 'student_id';

    public const MAPPED_CLASS = '\\DealNews\\DB\\Tests\\TestClasses\\Student';

    /**
     * The constraints for the properties
     *
     * @var array
     */
    public const MAPPING = [
        'student_id'  => [],
        'name'        => [],
        'courses'     => [
            'mapper'         => 'DealNews\\DB\\Tests\\TestClasses\\Mapper\\CourseMapper',
            'type'           => 'lookup',
            'table'          => 'student_course_xref',
            'primary_key'    => 'student_course_xref_id',
            'foreign_column' => 'student_id',
            'mapper_column'  => 'course_id',
        ],
        'assignments' => [
            'mapper'         => 'DealNews\\DB\\Tests\\TestClasses\\Mapper\\AssignmentMapper',
            'table'          => 'assignments',
            'primary_key'    => 'assignment_id',
            'foreign_column' => 'student_id',
        ],
        'nicknames'   => [
            'mapper'         => 'DealNews\\DB\\ColumnMapper',
            'table'          => 'student_nicknames',
            'primary_key'    => 'student_nickname_id',
            'foreign_column' => 'student_id',
            'column'         => 'name'
        ],
    ];
}
