<?php
namespace yimaBase\Mvc;

use Poirot\Collection\Entity;
use yimaBase\Mvc\Application\DefaultConfig;
use Zend\EventManager\EventManager;
use Zend\Mvc\ApplicationInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\ServiceManager;
use Zend\Http\Response;
use Zend\ServiceManager\Config as ConfigureService;


class Application implements ApplicationInterface
{
    /**
     * @var self Application Instance
     */
    static protected $instance;

    /**
     * @var boolean Is Application Initialized
     */
    protected $isInitialize;

    /**
     * @var Entity Application Configuration
     */
    protected $configuration;

    /**
     * @var ServiceLocatorInterface ServiceManager
     */
    protected $servicemanager;

    /**
     * @var EventManager
     */
    protected $eventManager;

    /**
     * @var MvcEvent
     */
    protected $event;

    /**
     * Application is not instantiable bu construct
     *
     */
    final private function __construct(array $configuration)
    {
        $this->configuration = new DefaultConfig($configuration);
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

        // Add Default Listeners To Events
        $this->addDefaultListeners();

        // Load Modules
        $serviceManager = $this->getServiceManager();
        $serviceManager->get('ModuleManager')->loadModules();

        // Bootstrap Application
        $serviceManager->get('Application')->bootstrap();

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
        if (!$this->servicemanager || !$this->servicemanager instanceof ServiceLocatorInterface)
            $this->servicemanager = new ServiceManager(
                new ConfigureService(array_merge(
                    $this->config()->get('service_manager')
                    , [
                        'services' => ['Application' => self::$instance]
                    ]
                ))
            );

        return $this->servicemanager;
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
            return $this->getResponse();
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
     * Add default events listeners to EventManager
     *
     * @return void
     */
    protected function addDefaultListeners()
    {
        $events = $this->getEventManager();

        $listeners = $this->config()->get('listeners');
        foreach ($listeners as $listener) {
            if (!is_object($listener))
                if (class_exists($listener))
                    $listener = new $listener();
                else
                    $listener = $this->getServiceManager()->get($listener);

            // Attach Aggregate Listener
            $events->attach($listener);
        }
    }
}
