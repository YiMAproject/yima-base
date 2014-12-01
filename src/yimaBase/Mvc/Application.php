<?php
namespace yimaBase\Mvc;

use Poirot\Collection\Entity;
use Poirot\Core\SetterSetup;
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
    use SetterSetup;

    /**
     * @var self Application Instance
     */
    static protected $instance;

    /**
     * @var boolean Is Application Initialized
     */
    protected $isInitialize;

    /**
     * Listeners Attached To EventManager
     *
     * @var array [ListenerAggregateInterface]
     */
    protected $listeners = array();

    // ...

    /**
     * @var ServiceLocatorInterface ServiceManager
     */
    protected $sm;

    /**
     * @var array Default Service Manager Config
     */
    protected $def_sm_config = array();

    // ...

    /**
     * @var Entity Application Configuration
     */
    protected $configuration;

    /**
     * @var EventManager
     */
    protected $eventManager;

    /**
     * @var MvcEvent
     */
    protected $event;

    // ...

    protected $__setup_array_priority = array(
        'service_manager_config',
    );

    /**
     * Application is not instantiable by construct
     *
     * @param array $setterSetup Application Setter Factory
     */
    final private function __construct(array $setterSetup)
    {
        $this->setupFromArray($setterSetup, true);
    }

    /**
     * Application Factory
     *
     * @param array $configuration Application Configuration
     *
     * @return Application
     */
    static function instance(array $configuration)
    {
        if (self::$instance and self::$instance instanceof self)
            return self::$instance;

        $Application = new self($configuration);
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
     * @param array $listeners [ListenerAggregateInterface] $listeners
     *
     * @throws \Exception
     * @return $this
     */
    public function setListeners(array $listeners)
    {
        if ($this->isInitialize())
            throw new \Exception('The Listeners can\'t attached when Application is Initialized.');

        foreach ($listeners as $listener) {
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

            $this->listeners[] = $listener;
        }

        return $this;
    }

    /**
     * Set Service Manager Configs
     *
     * @param array $smConfig Service Manager Config
     *
     * @throws \Exception
     * @return $this
     */
    public function setServiceManagerConfig(array $smConfig)
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
     * Initialize Application
     *
     * ! Just Before Run() we must initialize app
     *
     * - Add Config Initializer(s)
     *
     *
     * @return $this
     */
    function initialize()
    {
        if ($this->isInitialize())
            return $this;

        // Ai) Attach Listeners To Events ----------------------------------------\
        $events = $this->getEventManager();
        foreach ($this->listeners as $listener) {
            // Attach Aggregate Listener
            $events->attach($listener);
        }

        // Bi) Load Modules ------------------------------------------------------\
        $serviceManager = $this->getServiceManager();
        /** @var ModuleManager $moduleManager */
        $moduleManager  = $serviceManager->get('ModuleManager');
        $moduleManager->loadModules();

        // Ci) Bootstrap Application --------------------------------------------\
        $this->bootstrap();

        $this->isInitialize = true;

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

        // Setup MVC Event
        $this->event = $event  = new MvcEvent();
        $event->setTarget($this);
        $event->setApplication($this)
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
        return $this->isInitialize;
    }

    /**
     * Get Application Config
     *
     * @return DefaultConfig
     */
    function config()
    {
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
    public function getServiceManager()
    {
        if (!$this->sm || !$this->sm instanceof ServiceLocatorInterface)
            $this->sm = new ServiceManager
            (
                new ConfigureService($this->def_sm_config)
            );

        if (!$this->sm->has('Application'))
            $this->sm->setService('Application', self::$instance);

        return $this->sm;
    }

    /**
     * Get the request object
     *
     * @return \Zend\Stdlib\RequestInterface
     */
    public function getRequest()
    {
        return $this->getServiceManager()
            ->get('Request');
    }

    /**
     * Get the response object
     *
     * @return \Zend\Stdlib\ResponseInterface
     */
    public function getResponse()
    {
        return $this->getServiceManager()
            ->get('Response');
    }

    /**
     * Run the application
     *
     * @return Response
     */
    public function run()
    {
        if (!$this->isInitialize())
            $this->initialize();

        try
        {
            $events = $this->getEventManager();
            $event  = $this->event;

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
            $result = $events->trigger(MvcEvent::EVENT_ROUTE, $event, $shortCircuit);
            if ($result->stopped()) {
                $response = $result->last();
                if ($response instanceof ResponseInterface) {
                    $event->setTarget($this);
                    $event->setResponse($response);
                    $events->trigger(MvcEvent::EVENT_FINISH, $event);
                    return $response;
                }
                if ($event->getError()) {
                    return $this->completeRequest($event);
                }
                return $event->getResponse();
            }
            if ($event->getError()) {
                return $this->completeRequest($event);
            }

            // Trigger dispatch event
            $result = $events->trigger(MvcEvent::EVENT_DISPATCH, $event, $shortCircuit);

            // Complete response
            $response = $result->last();
            if ($response instanceof ResponseInterface) {
                $event->setTarget($this);
                $event->setResponse($response);
                $events->trigger(MvcEvent::EVENT_FINISH, $event);
                return $response;
            }

            $response = $this->getResponse();
            $event->setResponse($response);
            $this->completeRequest($event);

            return $this;
        }
        catch(\Exception $e)
        {
            $this->getEventManager()->trigger('error');
            // with default SendExceptionListener
            // Throw accrued exception so we may don't reach this lines below
            // ...
            $this->run();
        }
    }

    /**
     * Retrieve the event manager
     *
     * Lazy-loads an EventManager instance if none registered.
     *
     * @return EventManagerInterface
     */
    public function getEventManager()
    {
        if ($this->eventManager)
            return $this->eventManager;

        $eventManager = $this->getServiceManager()
            ->get('EventManager')
                ->setIdentifiers(array(
                    __CLASS__,
                    get_class($this),
                ));

        $this->eventManager = $eventManager;

        return $this->eventManager;
    }

    /**
     * Complete the request
     *
     * Triggers "render" and "finish" events, and returns response from
     * event object.
     *
     * @param  MvcEvent $event
     * @return Application
     */
    protected function completeRequest(MvcEvent $event)
    {
        $events = $this->getEventManager();
        $event->setTarget($this);
        $events->trigger(MvcEvent::EVENT_RENDER, $event);
        $events->trigger(MvcEvent::EVENT_FINISH, $event);
        return $this;
    }
}
