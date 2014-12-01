<?php
namespace yimaBase\Mvc\Application;

use Poirot\Collection\Entity;
use Zend\ServiceManager\ServiceManager;

class DefaultConfig extends Entity
{
    /** @var $serviceManager ServiceManager */
    protected $properties =
        [
            'service_manager' => [
                'invokables' => [
                    #'Application.Config' => $this,
                    'SharedEventManager'  => 'Zend\EventManager\SharedEventManager',
                ],
                'factories' => [
                    'EventManager'  => 'Zend\Mvc\Service\EventManagerFactory',
                    'ModuleManager' => 'Zend\Mvc\Service\ModuleManagerFactory',

                    'Request'       => 'Zend\Mvc\Service\RequestFactory',
                    'Response'      => 'Zend\Mvc\Service\ResponseFactory',
                    'Router'        => 'Zend\Mvc\Service\RouterFactory',
                ],
                'aliases' => [
                ],
                'shared' => [
                    'EventManager' => false,
                ],
                'initializers' => [
                    'yimaBase\Mvc\Application\DefaultServiceInitializer'
                ],
             ],
            /**
             * @var
             *  []ListenerAggregateInterface |
             *  Registered Service Manager Listener |
             *  Class Name
             */
            'listeners' => [

            ],
        ];

    function __construct($props)
    {
        $this->properties['service_manager']
            ['invokables']['Application.Config'] = $this;

        parent::__construct($props);
    }
}
