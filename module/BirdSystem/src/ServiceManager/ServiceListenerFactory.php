<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace BirdSystem\ServiceManager;

use Zend\Mvc\Service\ServiceListenerFactory as Base;

class ServiceListenerFactory extends Base
{

    /**
     * Default mvc-related service configuration -- can be overridden by modules.
     *
     * @var array
     */
    protected $defaultServiceConfig = [
        'invokables'         => [
            'DispatchListener'     => 'Zend\Mvc\DispatchListener',
            'RouteListener'        => 'Zend\Mvc\RouteListener',
            'SendResponseListener' => 'Zend\Mvc\SendResponseListener',
            'ViewJsonRenderer'     => 'Zend\View\Renderer\JsonRenderer',
            //            'ViewFeedRenderer'     => 'Zend\View\Renderer\FeedRenderer',
        ],
        'factories'          => [
            'Application'                    => 'Zend\Mvc\Service\ApplicationFactory',
            'Config'                         => 'Zend\Mvc\Service\ConfigFactory',
            'ControllerLoader'               => 'Zend\Mvc\Service\ControllerLoaderFactory',
            'ControllerPluginManager'        => 'Zend\Mvc\Service\ControllerPluginManagerFactory',
            'ConsoleAdapter'                 => 'Zend\Mvc\Service\ConsoleAdapterFactory',
            'ConsoleRouter'                  => 'Zend\Mvc\Service\RouterFactory',
            'ConsoleViewManager'             => 'Zend\Mvc\Service\ConsoleViewManagerFactory',
            'DependencyInjector'             => 'Zend\Mvc\Service\DiFactory',
            'DiAbstractServiceFactory'       => 'Zend\Mvc\Service\DiAbstractServiceFactoryFactory',
            'DiServiceInitializer'           => 'Zend\Mvc\Service\DiServiceInitializerFactory',
            'DiStrictAbstractServiceFactory' => 'Zend\Mvc\Service\DiStrictAbstractServiceFactoryFactory',
            //            'FilterManager'                  => 'Zend\Mvc\Service\FilterManagerFactory',
            //            'FormAnnotationBuilder'          => 'Zend\Mvc\Service\FormAnnotationBuilderFactory',
            //            'FormElementManager'             => 'Zend\Mvc\Service\FormElementManagerFactory',
            'HttpRouter'                     => 'Zend\Mvc\Service\RouterFactory',
            'HttpMethodListener'             => 'Zend\Mvc\Service\HttpMethodListenerFactory',
            'HttpViewManager'                => 'Zend\Mvc\Service\HttpViewManagerFactory',
            //            'HydratorManager'                => 'Zend\Mvc\Service\HydratorManagerFactory',
            'InjectTemplateListener'         => 'Zend\Mvc\Service\InjectTemplateListenerFactory',
            //            'InputFilterManager'             => 'Zend\Mvc\Service\InputFilterManagerFactory',
            //            'LogProcessorManager'            => 'Zend\Mvc\Service\LogProcessorManagerFactory',
            //            'LogWriterManager'               => 'Zend\Mvc\Service\LogWriterManagerFactory',
            //            'MvcTranslator'                  => 'Zend\Mvc\Service\TranslatorServiceFactory',
            //            'PaginatorPluginManager'         => 'Zend\Mvc\Service\PaginatorPluginManagerFactory',
            'Request'                        => 'Zend\Mvc\Service\RequestFactory',
            'Response'                       => 'Zend\Mvc\Service\ResponseFactory',
            'Router'                         => 'Zend\Mvc\Service\RouterFactory',
            'RoutePluginManager'             => 'Zend\Mvc\Service\RoutePluginManagerFactory',
            //            'SerializerAdapterManager'       => 'Zend\Mvc\Service\SerializerAdapterPluginManagerFactory',
            'TranslatorPluginManager'        => 'Zend\Mvc\Service\TranslatorPluginManagerFactory',
            //            'ValidatorManager'               => 'Zend\Mvc\Service\ValidatorManagerFactory',
            'ViewHelperManager'              => 'Zend\Mvc\Service\ViewHelperManagerFactory',
            //            'ViewFeedStrategy'               => 'Zend\Mvc\Service\ViewFeedStrategyFactory',
            'ViewJsonStrategy'               => 'Zend\Mvc\Service\ViewJsonStrategyFactory',
            'ViewManager'                    => 'Zend\Mvc\Service\ViewManagerFactory',
            'ViewResolver'                   => 'Zend\Mvc\Service\ViewResolverFactory',
            'ViewTemplateMapResolver'        => 'Zend\Mvc\Service\ViewTemplateMapResolverFactory',
            'ViewTemplatePathStack'          => 'Zend\Mvc\Service\ViewTemplatePathStackFactory',
            'ViewPrefixPathStackResolver'    => 'Zend\Mvc\Service\ViewPrefixPathStackResolverFactory',
        ],
        'aliases'            => [
            'Configuration'                              => 'Config',
            'Console'                                    => 'ConsoleAdapter',
            'Di'                                         => 'DependencyInjector',
            'Zend\Di\LocatorInterface'                   => 'DependencyInjector',
            'Zend\Form\Annotation\FormAnnotationBuilder' => 'FormAnnotationBuilder',
            'Zend\Mvc\Controller\PluginManager'          => 'ControllerPluginManager',
            'Zend\Mvc\View\Http\InjectTemplateListener'  => 'InjectTemplateListener',
            'Zend\View\Resolver\TemplateMapResolver'     => 'ViewTemplateMapResolver',
            'Zend\View\Resolver\TemplatePathStack'       => 'ViewTemplatePathStack',
            'Zend\View\Resolver\AggregateResolver'       => 'ViewResolver',
            'Zend\View\Resolver\ResolverInterface'       => 'ViewResolver',
            'ControllerManager'                          => 'ControllerLoader',
        ],
        'abstract_factories' => [
//            'Zend\Form\FormAbstractServiceFactory',
        ],
    ];
}
