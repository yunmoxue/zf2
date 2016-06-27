<?php

namespace BirdSystem\Tests\Db\TableGateway;

use BirdSystem\Db\Model\AbstractModel;
use BirdSystem\Db\TableGateway\AbstractTableGateway;
use BirdSystem\Tests\AbstractTestCase;
use Psr\Log\LoggerInterface;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Select;
use Zend\I18n\Translator\Translator;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorAwareTrait;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Class AbstractTableGatewayTest
 *
 * @package BirdSystem\Tests\Db\TableGateway
 * @method $this setServiceLocator(ServiceLocatorInterface $serviceLocator)
 */
abstract class AbstractTableGatewayTest extends AbstractTestCase implements ServiceLocatorAwareInterface
{
    use ServiceLocatorAwareTrait;
    /**
     * @var string Class name for TableGateway
     */
    protected $tableGatewayClass;

    /**
     * @var string Class name for model
     */
    protected $modelClass;

    protected $traceError = true;

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

    /**
     * @return $this
     */
    function setUp()
    {
        parent::setUp();

        $this->authenticate();

        return $this;
    }


    /**
     * @param array      $data
     * @param bool|false $forceCreate
     *
     * @return AbstractModel
     */
    public function getModelInstance(array $data = [], $forceCreate = false)
    {
        /**
         * @var ResultSet     $resultSet
         * @var AbstractModel $model
         */
        $criterias = $data;
        $result    = null;

        if (!$forceCreate) {
            $select      = $this->getTableGateway()->getSql()->select()->limit(1);
            $select      = $this->getTableGateway()->injectSelect($select, $data)->getInjectedSelect();
            $primaryKeys = $this->getTableGateway()->getPrimaryKeys();
            $columns     = $this->getTableGateway()->getColumns();
            $where       = [];

            foreach ($primaryKeys as $primaryKey) {
                $select->order([$this->getTableGateway()->getTable() . '.' . $primaryKey => Select::ORDER_DESCENDING]);
                if (isset($criterias[$primaryKey])) {
                    $where[$this->getTableGateway()->getTable() . '.' . $primaryKey . ' = ?'] = $criterias[$primaryKey];
                }
            }

            // First we try to find a matching record with primary keys
            if (count($where) == count($primaryKeys)) {
                $select->where($where);
                $resultSet = $this->getTableGateway()->selectWith($select);
                $result    = $resultSet->current();
            }

            if ($result) {
                //If matching record was found, we update this record to meet the criteria
                if (count($criterias) - count($primaryKeys) > 0) {
                    $this->getTableGateway()->update($data, $where);
                    $resultSet = $this->getTableGateway()->selectWith($select);
                    $result    = $resultSet->current();
                }
            } else {
                //If no find-by-primary-key record was found, we try to match a record by other criterias
                $select->reset(Select::WHERE);
                foreach ($criterias as $key => $value) {
                    if (in_array($key, $columns)
                        && isset($value)
                        && !$value instanceof Expression
                    ) {
                        $select->where([$this->getTableGateway()->getTable() . '.' . $key . ' = ?' => $value]);
                    }
                }
                $resultSet = $this->getTableGateway()->selectWith($select);
                $result    = $resultSet->current();
            }

            if ($result && !is_null($result->getId())) {
                return $result;
            } else {
                $forceCreate = true;
            }
        }

        if ($forceCreate) {
            $model = $this->getTableGateway()->get(
                $this->getTableGateway()->saveInsert($this->initModelInstance($data))
            );
        }

        return $model;
    }

    /**
     * @return AbstractTableGateway
     */
    public function getTableGateway()
    {
        return $this->getApplicationServiceLocator()->get($this->tableGatewayClass);
    }

    /**
     * Setup initial data for ModelInstance
     *
     * @param array      $data
     * @param bool|false $autoSave
     *
     * @return AbstractModel
     */
    public function initModelInstance(array $data = [], $autoSave = false)
    {
        $model = $this->getModel();
        $model->exchangeArray($data);
        unset($autoSave);

        return $model;
    }

    /**
     * @return AbstractModel
     */
    public function getModel()
    {
        return $this->getApplicationServiceLocator()->get($this->modelClass);
    }

    /**
     * This method is supposed to be extended by traits in correct modules.
     *
     * @return $this
     */
    protected function authenticate()
    {
    }

    public function getLogger()
    {
        if (!$this->logger) {
            $this->logger = $this->getApplication()->getServiceManager()->get('logger');
        }

        return $this->logger;
    }


    /**
     * This method insert batch records for test
     *
     * @param array $data
     * @param int   $count
     *
     * @return array
     */
    public function initBatchData($count, $data = [])
    {
        $insertedIds = [];
        for ($i = 0; $i < $count; $i++) {
            $model         = $this->getModelInstance($data, true);
            $insertedIds[] = $model->getId();
        }

        return $insertedIds;
    }

    /**
     * @param $message
     *
     * @return string
     */
    protected function t($message)
    {
        if (!static::$translator) {
            static::$translator = $this->getApplicationServiceLocator()->get('translator');
        }

        return static::$translator->translate($message);
    }

    // Empty test function to prvent 'No test is found in class' warning.
    public function testDummy()
    {
    }
}
