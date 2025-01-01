<?php

namespace DealNews\DB\Tests;

use \DealNews\DB\Repository;
use \DealNews\DB\Tests\TestClasses\Course;
use \DealNews\DB\Tests\TestClasses\Mapper\CourseMapper;

class RepositoryTest extends \PHPUnit\Framework\TestCase {

    public function testFind() {
        $id = $this->save('TestFind');

        $repo = new Repository(
            [
                'Course' => new CourseMapper,
            ]
        );

        $courses = $repo->find('Course', ['name' => 'TestFind']);

        $this->assertNotEmpty(
            $courses
        );

        $this->assertEquals(
            $id,
            current($courses)->course_id
        );
    }

    public function testBadFind() {
        $this->expectException('\\LogicException');
        $this->expectExceptionCode('1');
        $repo = $this->getRepo();
        $obj  = $repo->find('Foo', []);
    }

    protected function save($name = 'Test') {
        $repo = $this->getRepo();
        $repo->addMapper('Course', new CourseMapper);

        $course       = new Course();
        $course->name = $name;

        $course = $repo->save('Course', $course);

        $this->assertEquals(
            $name,
            $course->name
        );

        $this->assertIsInt(key($repo->storage['Course']));

        return $course->course_id;
    }

    protected function getRepo() {
        return new class extends Repository {
            public array $storage = [];
        };
    }
}
