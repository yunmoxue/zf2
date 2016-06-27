<?php

namespace BirdSystem\Tests\Controller;

use BirdSystem\Tests\Db\TableGateway\AbstractTableGatewayTest;
use BirdSystem\Traits\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Zend\I18n\Translator\Translator;
use Zend\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;

/**
 * Class AbstractBirdsystemHttpControllerTestCase
 *
 * @package BirdSystem\Tests\Controller
 */
abstract class AbstractBirdsystemHttpControllerTestCase extends AbstractHttpControllerTestCase
{
    use LoggerAwareTrait;

    protected $backupGlobals = false;
    protected $traceError = false;

    /**
     * @var string TestCase class for TableGateway used in this controller
     */
    protected $tableGatewayTestClass;

    /**
     * @var Translator
     */
    protected static $translator;

    /**
     * @var LoggerInterface
     */
    protected $logger;


    static public function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
    }

    protected function setUp()
    {
        $this->setApplicationConfig(
            include TEST_ROOT . '/config/application.config.php'
        );
        $this->setLogger($this->getApplicationServiceLocator()->get('logger'));
        if ($this->getName()) {
            $this->getApplicationServiceLocator()
                ->get('logger')
                ->notice('============ [' . get_class($this) . '::' . $this->getName() . '] ===========');
        }
        $this->authenticate();
    }


    /**
     * @return AbstractTableGatewayTest
     */
    public function getTableGatewayTest($class = null)
    {
        /**
         * @var AbstractTableGatewayTest $tableGatewayTest
         */
        if ($class) {
            $tableGatewayTest = new $class;
            $tableGatewayTest->setServiceLocator($this->getApplicationServiceLocator());
        } else {
            $tableGatewayTest = new $this->tableGatewayTestClass;
            $tableGatewayTest->setUp();
        }

        return $tableGatewayTest;
    }

    /**
     * This method is supposed to be extended by traits in correct modules.
     *
     * @param bool $forceAuthenticate
     *
     * @return $this
     */
    abstract function authenticate($forceAuthenticate = false);

    public function getLogger()
    {
        if (!$this->logger) {
            $this->logger = $this->getApplication()->getServiceManager()->get('logger');
        }

        return $this->logger;
    }


    protected function checkJsonResponse()
    {
        $json = json_decode($this->getResponse()->getContent(), true);
        $this->assertNotFalse($json);
        $this->assertArrayHasKey('total', $json);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('success', $json);

        $this->assertTrue(is_numeric($json['total']));
        $this->assertTrue($json['success']);
        $this->assertTrue(is_array($json['data']));

        return $json;
    }

    protected function t($message)
    {
        if (!static::$translator) {
            static::$translator = $this->getApplicationServiceLocator()->get('translator');
        }

        return static::$translator->translate($message);
    }

    public function assertApplicationException($type, $message = null)
    {
        parent::assertApplicationException($type, $message ?: '');
    }
}
