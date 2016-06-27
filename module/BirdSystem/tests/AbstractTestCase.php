<?php
/**
 * User: Allan Sun (allan.sun@bricre.com)
 * Date: 28/12/2015
 * Time: 16:49
 */

namespace BirdSystem\Tests;


use BirdSystem\Traits\LoggerAwareTrait;
use Faker\Factory as FackerFactory;
use Faker\Generator;
use Zend\Http\Request;
use Zend\Json\Json;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorAwareTrait;
use Zend\Test\PHPUnit\Controller\AbstractControllerTestCase;

/**
 * Class AbstractTestCase
 *
 * @package BirdSystem\Tests
 * @method Request getRequest()
 */
abstract class AbstractTestCase extends AbstractControllerTestCase implements ServiceLocatorAwareInterface
{
    use LoggerAwareTrait, ServiceLocatorAwareTrait;

    /**
     * @see https://github.com/fzaninotto/Faker
     * @var Generator $faker
     */
    private static $faker;

    protected $traceError = false;

    function setUp()
    {
        parent::setUp();
        $this->setApplicationConfig(
            include TEST_ROOT . '/config/application.config.php'
        );
        if ($this->getName()) {
            $this->getApplicationServiceLocator()
                ->get('logger')
                ->notice('============ [' . get_class($this) . '::' . $this->getName() . '] ===========');
        }
        if (!$this->getLogger()) {
            $this->setLogger($this->getApplicationServiceLocator()->get('logger'));
        }
    }

    function tearDown()
    {
        parent::tearDown();
        $this->reset();
    }

    /**
     * @return Generator
     */
    public function getFaker()
    {
        if (!self::$faker) {
            self::$faker = FackerFactory::create('en_GB');
        }

        return self::$faker;
    }

    /**
     * @return \stdClass
     */
    protected function getJsonResponseContent()
    {
        return Json::decode($this->getResponse()->getContent());

    }


    /**
     * @return \Zend\ServiceManager\ServiceManager
     */
    public function getApplicationServiceLocator()
    {
        if ($this->serviceLocator) {
            return $this->serviceLocator;
        } else {
            return parent::getApplicationServiceLocator();
        }
    }
}