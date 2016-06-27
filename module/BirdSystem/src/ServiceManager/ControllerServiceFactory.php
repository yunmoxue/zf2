<?php
namespace BirdSystem\ServiceManager;

use BirdSystem\Cache\CacheAwareInterface;
use BirdSystem\I18n\Translator\TranslatorAwareInterface;
use Psr\Log\LoggerAwareInterface;
use Zend\Mvc\Controller\AbstractController;
use Zend\ServiceManager\AbstractFactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Class ControllerServiceFactory
 *
 * @package BirdSystem\ServiceManager
 * @codeCoverageIgnore
 */
class ControllerServiceFactory implements AbstractFactoryInterface
{
    public function canCreateServiceWithName(ServiceLocatorInterface $locator, $name, $requestedName)
    {
        if (class_exists($requestedName . 'Controller', true)) {
            return true;
        }

        return false;
    }

    public function createServiceWithName(ServiceLocatorInterface $locator, $name, $requestedName)
    {

        $className = $requestedName . 'Controller';
        /**
         * @var AbstractController
         */
        $service = new $className();


        if (method_exists($service, 'setServiceLocator')) {
            $service->setServiceLocator($locator->getServiceLocator());
        }
        if ($service instanceof LoggerAwareInterface) {
            $service->setLogger($locator->getServiceLocator()->get('logger'));
        }
        if ($service instanceof CacheAwareInterface) {
            $service->setCache($locator->getServiceLocator()->get('cache'));
        }
        if ($service instanceof TranslatorAwareInterface) {
            $service->setTranslator($locator->getServiceLocator()->get('translator')->getTranslator());
        }

        if (method_exists($service, 'initialize')) {
            $service->initialize();
        }

        return $service;
    }

}