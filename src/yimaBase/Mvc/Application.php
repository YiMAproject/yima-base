<?php
namespace yimaBase\Mvc;

use Poirot\Core\Entity;
use Poirot\Core\BuilderSetterTrait;
use yimaBase\Mvc\Application\DefaultConfig;
use Zend\EventManager\EventManager;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\ModuleManager\ModuleManager;
use Zend\Mvc\ApplicationInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\ServiceManager;
use Zend\Http\Response;
use Zend\ServiceManager\Config as ConfigureService;
use Poirot\Core;
use Zend\Stdlib\ResponseInterface;

class Application implements ApplicationInterface
{
    use BuilderSetterTrait;

    /**
     * @var self Application Instance
     */
    static protected $instance;

    /**
     * Listeners Attached To EventManager
     * @see initialize()
     *
     * @var array [ListenerAggregateInterface]
     */
    protected $listeners = array(
        'RouteListener',
        'DispatchListener',
        'SendResponseListener',

        'Zend\Mvc\ModuleRouteListener',

        /* @TODO Attach Some From During System Bootstrap */
        // ViewManager Strategies
        'ViewManager',
        'ExceptionMvcStrategyListener',
    );

    // ...

    /**
     * @var array Default Service Manager Config
     */
    protected $def_sm_config = array(
        'invokables' => [
            'SharedEventManager'  => 'Zend\EventManager\SharedEventManager',

            // Set Some Default Listeners
            'ExceptionMvcStrategyListener' => 'yimaBase\Mvc\View\Listener\ExceptionMvcStrategyListener',
        ],
        'factories'  => [
            'EventManager'  => 'Zend\Mvc\Service\EventManagerFactory',

            'Request'       => 'Zend\Mvc\Service\RequestFactory',
            'Response'      => 'Zend\Mvc\Service\ResponseFactory',

            // you can replace application startup or default ServiceManagerConfig with your own services
            'ModuleManager' => 'Zend\Mvc\Service\ModuleManagerFactory', // default

            /* used by default Module Manager
             *
             * > by default containing all services for serviceManager and
             *   using during running app. Controllers, view, and more more ...
             *
             * > serviceListener listen for Module.php and load some config file
             *   by execute related method and get some config.
             *   you can register some by "service_listener_options" key
             *
             * > attached to eventManager by default
             *   setDefaultConfig catched from within modules
             *   for services that present to listener
             */
            'ServiceListener' => 'yimaBase\Mvc\Service\ServiceListenerFactory', // default
        ],
        'shared' => [
            'EventManager' => false,
        ],
        'initializers' => [
            'yimaBase\Mvc\Application\DefaultServiceInitializer'
        ],
    );

    // ...

    /**
     * @var ServiceLocatorInterface ServiceManager
     */
    protected $sm;

    /**
     * @var EventManager
     */
    protected $eventManager;

    /**
     * Default Application MvcEvent
     * used during dispatching
     *
     * @var MvcEvent
     */
    protected $event;

    /**
     * Set As Application.Config alias ApplicationConfig
     * on ServiceManager and can be retrieved by additional
     * code features.
     *
     * @var Entity Application Configuration
     */
    protected $configuration;

    // ...

    /**
     * @var boolean Is Application Initialized
     */
    protected $__isInitialize;

    /**
     * @var array BuilderSetterTrait
     */
    protected $__setup_array_priority = array(
        'service_manager_config', // run service manager config first
    );

    /**
     * Application is not instantiable by construct
     *
     * @param array $setterSetup Application Setter Factory
     */
    final private function __construct(array $setterSetup)
    {
        $this->setupFromArray($setterSetup, false);
    }

    /**
     * Application Factory
     *
     * @param array $setterOpts Application Builder Setup Setter
     *
     * @throws \Exception
     * @return Application
     */
    static function instance(array $setterOpts = [])
    {
        if (self::$instance and self::$instance instanceof self) {
            if (!empty($setterOpts))
                throw new \Exception('Application can only build once with setter options.');

            return self::$instance;
        }

        $Application = new self($setterOpts);
        self::$instance = $Application;

        return self::$instance;
    }

    /**
     * Set Application Listeners
     *
     * Listeners can be:
     * - object instance of AggregateListener
     * - class name
     * - registered service name
     *
     * ! Attached To Event Manager On self::initialize()
     *
     * @param array $listeners [ListenerAggregateInterface] $listeners
     *
     * @throws \Exception
     * @return $this
     */
    function setListeners(array $listeners)
    {
        if ($this->isInitialize())
            throw new \Exception('The Listeners can\'t attached after Application Initialized.');

        foreach ($listeners as $listener)
            $this->listeners[] = $listener;

        return $this;
    }

    /**
     * Set Service Manager Configs
     * ! Setup Method
     *
     * @param array $smConfig Service Manager Config
     *
     * @throws \Exception
     * @return $this
     */
    function setServiceManagerConfig(array $smConfig)
    {
        if ($this->sm instanceof ServiceLocatorInterface)
            throw new \Exception(
                'Service Config is used on first time instancing, '
                .'Service Manager is configured for now you must use "getServiceManager()"'
            );

        $this->def_sm_config = Core\array_merge(
            $this->def_sm_config
            , $smConfig
        );

        return $this;
    }

    /**
     * Set Application Config
     * ! Setup Method
     *
     * @param array $AppConf Application Config
     *
     * @return $this
     */
    function setApplicationConfig(array $AppConf)
    {
        $this->config()->setFrom(new Entity($AppConf));

        return $this;
    }

    /**
     * Initialize Application
     *
     * ! Just Before Run() we must initialize app
     *
     * - Add Config Initializer(s)
     *
     *
     * @throws \Exception
     * @return $this
     */
    function initialize()
    {
        if ($this->isInitialize())
            return $this;

        // ) Load Modules ------------------------------------------------------\
        $serviceManager = $this->getServiceManager();
        /** @var ModuleManager $moduleManager */
        $moduleManager  = $serviceManager->get('ModuleManager');
        $moduleManager->loadModules();

        // ) Attach Listeners To Events ----------------------------------------\
        $events = $this->getEventManager();
        foreach ($this->listeners as $listener) {
            if (!is_object($listener)) {
                if (class_exists($listener))
                    $listener = new $listener();
                else
                    $listener = $this->getServiceManager()->get($listener);
            }

            if (!$listener instanceof ListenerAggregateInterface)
                throw new \Exception(sprintf(
                    'Listener must instance of "ListenerAggregateInterface" but "%s" given.'
                    , is_object($listener) ? get_class($listener) : gettype($listener)
                ));

            // Attach Aggregate Listener
            $events->attach($listener);
        }

        // Ci) Bootstrap Application --------------------------------------------\
        $this->bootstrap();

        $this->__isInitialize = true;

        return $this;
    }

    /**
     * Bootstrap Application
     *
     * - Trigger Bootstrap Event
     *
     */
    protected function bootstrap()
    {
        $serviceManager = $this->getServiceManager();

        $application = $this->getServiceManager()
            ->get('Application');

        // Setup MVC Event
        $this->event = $event  = new MvcEvent();
        $event->setTarget($application);
        $event->setApplication($application)
            ->setRequest($this->getRequest())
            ->setResponse($this->getResponse())
            ->setRouter($serviceManager->get('Router'));

        // Trigger bootstrap events
        $this->getEventManager()
            ->trigger(MvcEvent::EVENT_BOOTSTRAP, $event);
    }

    /**
     * Is Application Initialized?
     *
     * @return bool
     */
    function isInitialize()
    {
        return $this->__isInitialize;
    }

    /**
     * Get Application Config
     *
     * @return DefaultConfig
     */
    function config()
    {
        if (!$this->configuration || !$this->configuration instanceof Entity)
            $this->configuration = new Entity();

        return $this->configuration;
    }

    /**
     * Get the locator object
     *
     * - Set self()   as "Application" Service
     * - Set config() as "Application.Config" Service
     * - Set "Listeners" config used on Initialize Application
     *
     * ! Service Manager Configured with Configs on first Construct
     *
     * @return \Zend\ServiceManager\ServiceLocatorInterface
     */
    function getServiceManager()
    {
        if (!$this->sm || !$this->sm instanceof ServiceLocatorInterface)
            $this->sm = new ServiceManager
            (
                new ConfigureService($this->def_sm_config)
            );

        if (!$this->sm->has('ServiceManager'))
            $this->sm->setService('ServiceManager', $this->sm);

        if (!$this->sm->has('Application'))
            $this->sm->setService('Application', self::$instance);

        if (!$this->sm->has('Application.Config')) {
            $this->sm->setService('Application.Config', $this->config()->borrow());
            $this->sm->setAlias('ApplicationConfig', 'Application.Config');
        }

        return $this->sm;
    }

    /**
     * Get the request object
     *
     * @return \Zend\Stdlib\RequestInterface
     */
    function getRequest()
    {
        return $this->getServiceManager()
            ->get('Request');
    }

    /**
     * Get the response object
     *
     * @return \Zend\Stdlib\ResponseInterface
     */
    function getResponse()
    {
        return $this->getServiceManager()
            ->get('Response');
    }

    /**
     * Run the application
     *
     * @throws \Exception
     * @return Response
     */
    function run()
    {
        if (!$this->isInitialize())
            $this->initialize();

        $events = $this->getEventManager();
        $event  = $this->event;

        // Complete response
        $self = $this;
        $COMPLETE = function($results) use ($event, $events, $self) {
            $response = $results->last();
            if ($response instanceof ResponseInterface) {
                $event->setTarget($self);
                $event->setResponse($response);
                $events->trigger(MvcEvent::EVENT_FINISH, $event);
                return $response;
            }

            $response = $self->getResponse();

            $event->setResponse($response);
            $self->__completeRequest($event);

            return $event->getResponse();
        };

        try
        {
            // Define callback used to determine whether or not to short-circuit
            $shortCircuit = function ($r) use ($event) {
                if ($r instanceof ResponseInterface) {
                    return true;
                }
                if ($event->getError()) {
                    return true;
                }
                return false;
            };

            // Trigger route event
            $results = $events->trigger(MvcEvent::EVENT_ROUTE, $event, $shortCircuit);
            if ($results->stopped()) {
                return $COMPLETE($results);
            }

            // Trigger dispatch event
            $results = $events->trigger(MvcEvent::EVENT_DISPATCH, $event, $shortCircuit);
        }
        catch(\Exception $e)
        {
            $results = $events->trigger(MvcEvent::EVENT_ERROR, $event->setError($e));
        }

        return $COMPLETE($results);
    }

    /**
     * Retrieve the event manager
     *
     * Lazy-loads an EventManager instance if none registered.
     *
     * @return EventManagerInterface
     */
    function getEventManager()
    {
        if ($this->eventManager)
            return $this->eventManager;

        $eventManager = $this->getServiceManager()
            ->get('EventManager')
                ->setIdentifiers(array(
                    __CLASS__,
                    get_class($this),
                    // Default Identifier
                    'Zend\Mvc\Application',
                ));

        $this->eventManager = $eventManager;

        return $this->eventManager;
    }

    /**
     * Get MvcEvent Object
     *
     * @return MvcEvent
     */
    function getMvcEvent()
    {
       return $this->event;
    }

    /**
     * Complete the request
     *
     * Triggers "render" and "finish" events, and returns response from
     * event object.
     *
     * @param  MvcEvent $event
     */
    protected function __completeRequest(MvcEvent $event)
    {
        $event->setTarget($this);

        $events = $this->getEventManager();
        $events->trigger(MvcEvent::EVENT_RENDER, $event);
        $events->trigger(MvcEvent::EVENT_FINISH, $event);
    }
}
