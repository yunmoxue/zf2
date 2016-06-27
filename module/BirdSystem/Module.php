<?php

namespace BirdSystem;

use BirdSystem\Authentication\UnAuthenticatedException;
use BirdSystem\Controller\AbstractRestfulController;
use BirdSystem\Db\Adapter\Profiler\AutoLogProfiler;
use BirdSystem\Db\Adapter\Profiler\Profiler;
use BirdSystem\Logger\Formatter\WildfireFormatter;
use BirdSystem\ServiceManager\GeneralServiceFactory;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Zend\ModuleManager\Feature\ConfigProviderInterface;
use Zend\ModuleManager\Feature\ControllerProviderInterface;
use Zend\ModuleManager\Feature\ServiceProviderInterface;
use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\Session\Config\SessionConfig;
use Zend\Session\Container;
use Zend\Session\SessionManager;
use Zend\View\Model\JsonModel;
use Zend\View\Renderer\JsonRenderer;
use Zend\View\ViewEvent;

/**
 * Class Module
 *
 * @package BirdSystem
 * @codeCoverageIgnore
 */
class Module implements ConfigProviderInterface, ServiceProviderInterface,
                        ControllerProviderInterface
{
    const SESSION_LOCALE = 'LOCALE';


    public function onBootstrap(MvcEvent $event)
    {
        $eventManager        = $event->getApplication()->getEventManager();
        $moduleRouteListener = new ModuleRouteListener();
        $moduleRouteListener->attach($eventManager);

        $this->initSession($event);
        $this->initLocale($event);
        $this->initLogger($event);
        $this->initDbProfiler($event);
        $this->resetErrorHandling($event);

        $sharedEvents = $eventManager->getSharedManager();
        $sharedEvents->attach('Zend\View\View', ViewEvent::EVENT_RESPONSE, function (ViewEvent $event) {
            $renderer = $event->getRenderer();
            $result   = $event->getResult();
            if ($renderer instanceof JsonRenderer) {
                $response = $event->getResponse();
                $response->setContent(preg_replace('/("[a-z_-]*id[a-z_]*"):"([1-9]\d*)"/', '\1:\2', $result));
            }
        });
    }

    private function initSession(MvcEvent $event)
    {
        $config = $event->getApplication()
            ->getServiceManager()
            ->get('Configuration');

        if (getenv('MEMCACHED')) {
            ini_set('session.save_handler', 'memcached');
            ini_set('session.save_path', gethostbyname(getenv('MEMCACHED')) . ':11211');
        }

        $sessionConfig = new SessionConfig();
        $sessionConfig->setOptions($config['session']);
        $sessionManager = new SessionManager($sessionConfig);

        $sessionManager->start();

        /**
         * Optional: If you later want to use namespaces, you can already store the
         * Manager in the shared (static) Container (=namespace) field
         */
        Container::setDefaultManager($sessionManager);

    }

    private function initLocale(MvcEvent $event)
    {
        $Locale = new Container(self::SESSION_LOCALE);
        if ($Locale->{self::SESSION_LOCALE}) {
            $Transaltor = $event->getApplication()
                ->getServiceManager()
                ->get('translator');
            $Transaltor->setLocale($Locale->{self::SESSION_LOCALE});
            \Locale::setDefault($Locale->{self::SESSION_LOCALE});
        } else {
            $Locale->{self::SESSION_LOCALE} = 'en_GB';
        }
    }

    private function initLogger(MvcEvent $event)
    {

        $ServiceManager = $event->getApplication()->getServiceManager();
        /**
         * @var Logger     $logger
         * @var \Throwable $exception
         */
        $logger   = $ServiceManager->get('logger');
        $handlers = $logger->getHandlers();
        foreach ($handlers as &$handler) {
            if ($handler instanceof StreamHandler) {
                //Make sure we reference the class directly so no error will be poped during production environment
                $Formatter = new \Bramus\Monolog\Formatter\ColoredLineFormatter(null, '%message% %context% %extra%');
                $Formatter->allowInlineLineBreaks(true);
                $Formatter->ignoreEmptyContextAndExtra(true);
                $handler->setFormatter($Formatter);
            }
        }
    }

    private function initDbProfiler(MvcEvent $event)
    {
        if ('development' == APP_ENVIRONMENT) {
            $ServiceManager = $event->getApplication()->getServiceManager();
            /**
             * @var \Zend\Db\Adapter\Adapter $dbAdapter
             */
            $dbAdapter = $ServiceManager->get('db');
            if (defined('PHPUNIT_COMPOSER_INSTALL') || defined('__PHPUNIT_PHAR__')) {
                $profiler = new AutoLogProfiler();
                $profiler->setServiceLocator($ServiceManager);
                $dbAdapter->setProfiler($profiler);
            } else {
                $dbAdapter->setProfiler(new Profiler());
            }

            $EventManager = $event->getApplication()->getEventManager();
            $EventManager->attach(MvcEvent::EVENT_FINISH, [
                $this,
                'developmentEnvironmentDbProfilerLog',
            ]);
        }
    }

    private function resetErrorHandling(MvcEvent $event)
    {
        $EventManager = $event->getApplication()->getEventManager();

        $EventManager->attach([MvcEvent::EVENT_DISPATCH_ERROR, MvcEvent::EVENT_RENDER_ERROR],
            [$this, 'generalErrorHandler'], 99);

        if ('development' == APP_ENVIRONMENT) {
            $EventManager->attach([MvcEvent::EVENT_DISPATCH_ERROR, MvcEvent::EVENT_RENDER_ERROR],
                [$this, 'developmentEnvironmentErrorHandler'], 100);
        }
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    /**
     * For purformance purpose, avoid using closure in *.config.php files, as clo
     *
     * @return array
     */
    public function getServiceConfig()
    {
        return [
            'abstract_factories' => [
                'BirdSystem\ServiceManager\DbServiceAbstractFactory',
                'Zend\Cache\Service\StorageCacheAbstractServiceFactory',
            ],
            'factories'          => [
                'BirdSystem\PHPExcel\Cell'                          => function ($sm) {
                    return GeneralServiceFactory::createService('BirdSystem\PHPExcel\Cell\PHPExcel_Cell_ValueBinder',
                        $sm, true);
                },
                'BirdSystem\Utility\ImageResize'                    => function ($sm) {
                    return GeneralServiceFactory::createService('BirdSystem\Utility\ImageResize', $sm, true);
                },
                'BirdSystem\Utility\Measure'                        => function ($sm) {
                    return GeneralServiceFactory::createService('BirdSystem\Utility\Measure', $sm, true);
                },
                'BirdSystem\Utility\DeliveryReference'              => function ($sm) {
                    return GeneralServiceFactory::createService('BirdSystem\Utility\DeliveryReference', $sm, true);
                },
                'BirdSystem\Utility\Emailer'                        => function ($sm) {
                    return GeneralServiceFactory::createService('BirdSystem\Utility\Emailer', $sm, true);
                },
                'BirdSystem\Utility\TempConsignmentInfoProductInfo' => function ($sm) {
                    return GeneralServiceFactory::createService('BirdSystem\Utility\TempConsignmentInfoProductInfo',
                        $sm, true);
                },
                'BirdSystem\Utility\Uploader'                       => function ($sm) {
                    return GeneralServiceFactory::createService('BirdSystem\Utility\Uploader', $sm, true);
                },
                'BirdSystem\Utility\DbArchive'                      => function ($sm) {
                    return GeneralServiceFactory::createService('BirdSystem\Utility\DbArchive', $sm, true);
                },
                'BirdSystem\Barcode\Auspost'                        => function ($sm) {
                    return GeneralServiceFactory::createService('BirdSystem\Barcode\Auspost', $sm, true);
                },
                'BirdSystem\Barcode\Eparcel'                        => function ($sm) {
                    return GeneralServiceFactory::createService('BirdSystem\Barcode\Eparcel', $sm, true);
                },
                'BirdSystem\Service\Slack\Service'                  => function ($sm) {
                    return GeneralServiceFactory::createService('BirdSystem\Service\Slack\Service', $sm, true);
                },
                'BirdSystem\Service\Stamps\ClientManager'           => function ($sm) {
                    return GeneralServiceFactory::createService('BirdSystem\Service\Stamps\ClientManager', $sm, true);
                },
                'BirdSystem\Service\GLS\ClientManager'              => function ($sm) {
                    return GeneralServiceFactory::createService('BirdSystem\Service\GLS\ClientManager', $sm, true);
                },
                'BirdSystem\Service\Fedex\ClientManager'            => function ($sm) {
                    return GeneralServiceFactory::createService('BirdSystem\Service\Fedex\ClientManager', $sm, true);
                },
                'BirdSystem\Service\Parcelforce\ClientManager'      => function ($sm) {
                    return GeneralServiceFactory::createService('BirdSystem\Service\Parcelforce\ClientManager', $sm,
                        true);
                },
                'BirdSystem\Service\UPS\ClientManager'              => function ($sm) {
                    return GeneralServiceFactory::createService('BirdSystem\Service\UPS\ClientManager', $sm,
                        true);
                },
                'BirdSystem\Service\Colissimo\ClientManager'        => function ($sm) {
                    return GeneralServiceFactory::createService('BirdSystem\Service\Colissimo\ClientManager', $sm,
                        true);
                },
                'BirdSystem\Service\DHL\ClientManager'              => function ($sm) {
                    return GeneralServiceFactory::createService('BirdSystem\Service\DHL\ClientManager', $sm,
                        true);
                },
                'BirdSystem\Utility\MonthlyStockFee'                => function ($sm) {
                    return GeneralServiceFactory::createService('BirdSystem\Utility\MonthlyStockFee', $sm, true);
                },
                'BirdSystem\Service\UPSFreight\ClientManager'       => function ($sm) {
                    return GeneralServiceFactory::createService('BirdSystem\Service\UPSFreight\ClientManager', $sm,
                        true);
                },
                'translator'                                        => 'Zend\Mvc\Service\TranslatorServiceFactory',
                'cache'                                             => function ($sm) {
                    return \Zend\Cache\StorageFactory::factory([
                        'adapter' => [
                            'name' => 'filesystem',
                        ],
                    ]);
                },
            ],
            'aliases'            => [
                'Measure'             => 'BirdSystem\Utility\Measure',
                'DeliveryReference'   => 'BirdSystem\Utility\DeliveryReference',
                'stamps-service'      => 'BirdSystem\Service\Stamps\ClientManager',
                'fedex-service'       => 'BirdSystem\Service\Fedex\ClientManager',
                'parcelforce-service' => 'BirdSystem\Service\Parcelforce\ClientManager',
                'gls-service'         => 'BirdSystem\Service\GLS\ClientManager',
                'ups-service'         => 'BirdSystem\Service\UPS\ClientManager',
                'ups-freight-service' => 'BirdSystem\Service\UPSFreight\ClientManager',
                'colissimo-service'   => 'BirdSystem\Service\Colissimo\ClientManager',
                'dhl-service'         => 'BirdSystem\Service\DHL\ClientManager',
                'db-archive-service'  => 'BirdSystem\Utility\DbArchive',
                'slack-service'       => 'BirdSystem\Service\Slack\Service',
                'MonthlyStockFee'     => 'BirdSystem\Utility\MonthlyStockFee',
            ],
        ];
    }

    public function getControllerConfig()
    {
        return [
            'abstract_factories' => [
                'BirdSystem\ServiceManager\ControllerServiceFactory',
            ],
        ];
    }

    public function developmentEnvironmentDbProfilerLog(MvcEvent $event)
    {
        $ServiceManager = $event->getApplication()->getServiceManager();
        $dbAdapter      = $ServiceManager->get('db');
        $profiles       = $dbAdapter->getProfiler()->getProfiles();
        if ('cli' != php_sapi_name()) {
            // Our special formatter to add 'TABLE' format for logging SQL Queries in FirePHP
            $FirePHPHandler = $ServiceManager->get('logger')->popHandler();
            $FirePHPHandler->setFormatter(new WildfireFormatter());
            $ServiceManager->get('logger')->pushHandler($FirePHPHandler);

            $quries = [['Eslape', 'SQL Statement', 'Parameters']];
            foreach ($profiles as $profile) {
                $quries[] =
                    [
                        round($profile['elapse'], 4),
                        $profile['sql'],
                        $profile['parameters'] ? $profile['parameters']->getNamedArray() : null,
                    ];
            }

            $ServiceManager->get('logger')->info('Queries', ['table' => $quries]);
        } else {
            $ServiceManager->get('logger')->info('Total Number of Queries : ' . count($profiles));
        }
    }

    public function generalErrorHandler(MvcEvent $event)
    {
        if ($event->getController() instanceof AbstractRestfulController) {
            if ($event->getParam('exception') instanceof UnAuthenticatedException) {
                $event->getResponse()->setStatusCode(403);
            } else {
                $event->getResponse()->setStatusCode(500);
            }
            $view = new JsonModel([
                'success' => false,
                'message' => $event->getParam('error') ? $event->getParam('error') :
                    ($event->getParam('exception') ? $event->getParam('exception')->getMessage() :
                        null),
            ]);
            if (!$event->getRouteMatch()) {
                $event->getResponse()->setStatusCode(404);
                $view->message = 'Invalid Request: Cannot find method to deal request.';
            }
            $event->setViewModel($view);
//        $event->getResult()->setTerminal(true);
            $event->stopPropagation(true);
        }
    }

    public function developmentEnvironmentErrorHandler(MvcEvent $event)
    {
        $ServiceManager = $event->getApplication()->getServiceManager();
        /**
         * @var Logger     $logger
         * @var \Throwable $exception
         */
        $logger = $ServiceManager->get('logger');
        if ($exception = $event->getParam('exception')) {
            $logger->error(get_class($exception));
            $logger->error($exception->getMessage());
            $logger->debug($exception->getTraceAsString());
        }
    }
}
