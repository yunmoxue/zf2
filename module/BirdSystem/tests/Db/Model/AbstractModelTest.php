<?php
namespace BirdSystem\Tests\Db\Model;

use BirdSystem\Db\Model\AbstractModel;
use Faker\Factory;
use Faker\Generator;

abstract class AbstractModelTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string Model class name to be tested
     */
    protected $modelClass;

    /**
     * @var Generator
     */
    protected $faker;

    public function setUp()
    {
        parent::setUp();
        if (!$this->faker) {
            $this->faker = Factory::create();
        }
    }

    public function testExtraFieldOverridenProperty()
    {
        /** @var AbstractModel $Model */
        $Model      = new $this->modelClass;
        $Reflection = new \ReflectionObject($Model);
        foreach ($Model->getExtraFields() as $field) {
            $this->assertFalse($Reflection->hasProperty($field),
                "ExtraField [${field}] is already defined in Base Model.");
        }
    }
}
