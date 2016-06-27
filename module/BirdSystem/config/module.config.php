<?php
namespace BirdSystem;

return [
    'router'       => [
        'routes' => [
            'barcode' => [
                'type'    => 'Segment',
                'options' => [
                    'route'    => '/barcode/:id',
                    'defaults' => [
                        '__NAMESPACE__' => 'BirdSystem\\Controller',
                        'controller'    => 'Barcode',
                        'action'        => 'index',
                        'id'            => '000'
                    ],
                ]
            ]
        ]
    ],
    'view_manager' => [
        'strategies'               => ['ViewJsonStrategy'],
        'display_not_found_reason' => true,
        'display_exceptions'       => true,
        'doctype'                  => 'HTML5',
        'not_found_template'       => 'error/404',
        'exception_template'       => 'error/index',
        'template_map'             => array(
            'layout/layout'           => __DIR__ . '/../view/layout/layout.phtml',
            'application/index/index' => __DIR__ . '/../view/application/index/index.phtml',
            'error/404'               => __DIR__ . '/../view/error/404.phtml',
            'error/index'             => __DIR__ . '/../view/error/index.phtml',
        ),
        'template_path_stack'      => array(
            __DIR__ . '/../view',
        ),
    ],
];
