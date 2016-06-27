<?php
namespace BirdSystem\ServiceManager;

use BirdSystem\Cache\CacheAwareInterface;
use BirdSystem\Db\TableGateway\AbstractTableGateway;
use BirdSystem\I18n\Translator\TranslatorAwareInterface;
use Psr\Log\LoggerAwareInterface;
use Zend\Db\ResultSet\ResultSet;
use Zend\ServiceManager\AbstractFactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Class DbServiceAbstractFactory
 *
 * @package BirdSystem\ServiceManager
 * @codeCoverageIgnore
 */
class DbServiceAbstractFactory implements AbstractFactoryInterface
{
    public function canCreateServiceWithName(ServiceLocatorInterface $locator, $name, $requestedName)
    {
        if (false !== strpos($requestedName, '\TableGateway\\') || false !== strpos($requestedName, '\Model\\')) {
            if (class_exists($requestedName, true)) {
                if (is_subclass_of($requestedName, 'BirdSystem\Db\TableGateway\AbstractTableGateway')
                    || is_subclass_of($requestedName, 'BirdSystem\Db\Model\AbstractModel')
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function createServiceWithName(ServiceLocatorInterface $locator, $name, $requestedName)
    {
        $service = null;

        if (is_subclass_of($requestedName, 'BirdSystem\Db\Model\AbstractModel')) {
            $service = new $requestedName();
        } elseif (is_subclass_of($requestedName, 'BirdSystem\Db\TableGateway\AbstractTableGateway')) {
            $dbAdapter          = $locator->get('Zend\Db\Adapter\Adapter');
            $resultSetPrototype = new ResultSet();
            $model              = $this->parseModelFromTableGateway($requestedName);
            /** @var \ArrayObject $prototype */
            $prototype = new $model();
            $resultSetPrototype->setArrayObjectPrototype($prototype);

            /**
             * @var AbstractTableGateway $service
             */
            try {

                $service = new $requestedName($dbAdapter);
            } catch (\Exception $e) {
                throw $e;
            }
        }

        if ($service instanceof LoggerAwareInterface) {
            $service->setLogger($locator->get('logger'));
        }
        if (method_exists($service, 'setServiceLocator')) {
            $service->setServiceLocator($locator);
        }
        if ($service instanceof CacheAwareInterface) {
            $service->setCache($locator->get('cache'));
        }
        if ($service instanceof TranslatorAwareInterface) {
            $service->setTranslator($locator->get('translator')->getTranslator());
        }


        return $service;
    }

    private function parseTableGatewayFromModel($model)
    {
        return str_replace('\\Db\\Model\\', '\\Db\\TableGateway\\', $model);
    }

    private function parseModelFromTableGateway($tableGateway)
    {
        return str_replace('\\Db\\TableGateway\\', '\\Db\\Model\\', $tableGateway);
    }
}