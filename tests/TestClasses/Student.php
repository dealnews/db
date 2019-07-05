<?php

namespace DealNews\DB\Tests\TestClasses;

/**
 * Test Student Class
 *
 * @author      Brian Moon <brianm@dealnews.com>
 * @copyright   1997-Present DealNews.com, Inc
 * @package     RBAC Web
 */

class Student {

    /**
     * The unique id
     *
     * @var integer
     */
    public $student_id = 0;

    /**
     * A short name
     *
     * @var string
     */
    public $name = "";

    /**
     * Test Course set
     * @var array
     */
    public $courses = [];
}
