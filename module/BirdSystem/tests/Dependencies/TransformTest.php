<?php

namespace BirdSystem\Tests\Dependencies;

use Camel\CaseTransformer;
use Camel\Format;

class TransformTest extends \PHPUnit_Framework_TestCase
{
    /** @var CaseTransformer */
    public $transformerSnakeToCamel;
    /** @var CaseTransformer */
    public $transformerSnakeToStudly;

    public function setUp()
    {
        $this->transformerSnakeToCamel  = new  CaseTransformer(new Format\SnakeCase(), new Format\CamelCase());
        $this->transformerSnakeToStudly = new  CaseTransformer(new Format\SnakeCase(), new Format\StudlyCaps());
        parent::setUp();
    }

    public function testAddressEdgeCase()
    {
        $this->assertEquals('addressLine1', $this->transformerSnakeToCamel->transform('address_line_1'));
        $this->assertEquals('AddressLine1', $this->transformerSnakeToStudly->transform('address_line_1'));
    }
}
