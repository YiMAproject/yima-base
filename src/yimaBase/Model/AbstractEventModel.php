<?php
namespace yimaBase\Model;

use Zend\EventManager\EventManager;
use Zend\EventManager\EventManagerInterface;

abstract class AbstractEventModel
{
    /*
     * EVENTS
     */
    const EVENT_CONSTRUCT  = 'event.construct';
    const EVENT_INITIALIZE = 'event.initialize';

    /**
     * @var EventManagerInterface
     */
    protected $events;

    /**
     * @var bool Is Initialized
     */
    protected $isInitialize = false;

    /**
     * Construct
     *
     */
    public function __construct()
    {
        $this->getEventManager()->trigger(self::EVENT_CONSTRUCT, $this);
    }

    /**
     * Initialize
     * : Initialize Model and Prepare Before First Transaction (Store And Retrieve Data)
     */
    public function initialize()
    {
        if ($this->isInitialize) {
            return ;
        }

        $this->isInitialize = true;

        $this->getEventManager()->trigger(self::EVENT_INITIALIZE, $this);
    }

    /**
     * Get Event Manager
     *
     * @return EventManagerInterface
     */
    public function getEventManager()
    {
        if (!$this->events) {
            $events = new EventManager();
            $this->setEventManager($events);
        }

        return $this->events;
    }

    /**
     * Set Event Manager
     *
     * @param EventManagerInterface $events Event Manager Object
     */
    public function setEventManager(EventManagerInterface $events)
    {
        $this->events = $events;
    }
}
