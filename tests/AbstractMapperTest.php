<?php

namespace DealNews\DB\Tests;

use \DealNews\DB\Tests\TestClasses\Course;
use \DealNews\DB\Tests\TestClasses\Mapper\CourseMapper;
use \DealNews\DB\Tests\TestClasses\Student;
use \DealNews\DB\Tests\TestClasses\Mapper\StudentMapper;
use \DealNews\DB\Tests\TestClasses\BadClass;
use \DealNews\DB\Tests\TestClasses\Mapper\BadCourseMapper;

class AbstractMapperTest extends \PHPUnit\Framework\TestCase {
    public function testSimpleMapping() {
        $obj1 = $this->create(1);
        // create two because sqlite will always return
        // the last auto increment id even if an insert
        // was not performed which causes the update to
        // appear to work even when it does not
        $obj2 = $this->create(2);
        $this->load($obj1);

        $mapper  = new CourseMapper();
        $courses = $mapper->loadMulti([1, 2]);
        $this->assertEquals(2, count($courses));

        $this->update($obj1->course_id);
        $this->delete($obj1->course_id);
    }

    public function testFind() {
        $obj1 = $this->create(3);
        $obj2 = $this->create(4);

        $mapper = new CourseMapper();
        $objs   = $mapper->find([
            'name' => [
                $obj1->name,
                $obj2->name,
            ],
        ]);

        $this->assertEquals(
            [$obj1->course_id, $obj2->course_id],
            array_keys($objs)
        );
    }

    public function testBadMapper() {
        $this->expectException("\PDOException");
        $course            = new BadClass();
        $course->course_id = 'some string';
        $course->name      = 'Bad Test';

        $mapper = new BadCourseMapper();
        $course = $mapper->save($course);
    }

    protected function create($number) {
        // Test Creation
        $name = "Test $number " . time();

        $course       = new Course();
        $course->name = $name;

        $mapper = new CourseMapper();
        $course = $mapper->save($course);

        $id = $course->course_id;

        $this->assertNotEquals(
            0,
            $course->course_id
        );

        return $course;
    }

    protected function load($obj) {
        // Test loading what was created

        $mapper = new CourseMapper();
        $course = $mapper->load($obj->course_id);

        $this->assertEquals(
            $obj->course_id,
            $course->course_id
        );

        $this->assertEquals(
            $obj->name,
            $course->name
        );
    }

    protected function update($id) {
        $course = new Course();
        $mapper = new CourseMapper();

        // check that updating works
        $name              = 'Test Update ' . time();
        $course->course_id = $id;
        $course->name      = $name;

        $course = $mapper->save($course);

        $this->assertEquals(
            $id,
            $course->course_id
        );

        $this->assertEquals(
            $name,
            $course->name
        );
    }

    protected function delete($id) {
        $mapper  = new CourseMapper();
        $success = $mapper->delete($id);

        $this->assertTrue($success);

        $course = $mapper->load($id);
        $this->assertEmpty($course);
    }

    public function testRelationCreation() {
        $student = $this->createRelation();

        $this->assertNotEquals(
            0,
            $student->student_id
        );

        $this->assertNotEquals(
            0,
            $student->courses[0]->course_id
        );

        $this->assertNotEquals(
            0,
            $student->courses[1]->course_id
        );
    }

    public function testRelationModification() {
        $student = $this->createRelation();

        $student->courses[0]->name = 'Course 1a';

        unset($student->courses[1]);

        $mapper  = new StudentMapper();
        $student = $mapper->save($student);

        $this->assertEquals(
            'Course 1a',
            $student->courses[0]->name
        );
    }

    protected function createRelation() {
        static $student;

        if (!$student) {
            $course1       = new Course();
            $course1->name = 'Course 1';

            $course2       = new Course();
            $course2->name = 'Course 2';

            $student          = new Student();
            $student->name    = 'Student 1';
            $student->courses = [
                $course1,
                $course2,
            ];

            $mapper = new StudentMapper();

            $student = $mapper->save($student);
        }

        return $student;
    }
}
