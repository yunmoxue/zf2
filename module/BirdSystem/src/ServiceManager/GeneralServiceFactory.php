<?php
/**
 * User: Allan Sun (allan.sun@bricre.com)
 * Date: 28/12/2015
 * Time: 15:36
 */

namespace BirdSystem\ServiceManager;

use BirdSystem\Authentication\AuthenticationAwareInterface;
use BirdSystem\Cache\CacheAwareInterface;
use Psr\Log\LoggerAwareInterface;
use Zend\I18n\Translator\TranslatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

abstract class GeneralServiceFactory
{
    static $instances = [];

    public static function createService($requestedName, ServiceLocatorInterface $locator, $forceCreate = false)
    {
        if (array_key_exists($requestedName, static::$instances) && !$forceCreate) {
            return static::$instances[$requestedName];
        }

        $service = new $requestedName();
        if (method_exists($service, 'setServiceLocator')) {
            $service->setServiceLocator($locator);
        }
        if ($service instanceof LoggerAwareInterface) {
            $service->setLogger($locator->get('logger'));
        }
        if ($service instanceof CacheAwareInterface) {
            $service->setCache($locator->get('cache'));
        }
        if ($service instanceof TranslatorAwareInterface) {
            $service->setTranslator($locator->get('translator')->getTranslator());
        }
        if ($service instanceof AuthenticationAwareInterface) {
            $service->getAuthenticationService();
        }

        if (!$forceCreate) {
            static::$instances[$requestedName] = $service;
        }

        if (method_exists($service, 'initialize')) {
            $service->initialize();
        }

        if (method_exists($service, 'init')) {
            $service->init();
        }

        return $service;
    }

}