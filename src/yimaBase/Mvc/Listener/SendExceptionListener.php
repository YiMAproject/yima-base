<?php
namespace yimaBase\Mvc\Listener;

use yimaBase\Mvc\MvcEvent;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\Mvc\ResponseSender\SendResponseEvent;

class SendExceptionListener implements
    ListenerAggregateInterface
{

    /**
     * @var \Zend\Stdlib\CallbackHandler[]
     */
    protected $listeners = array();

    /**
     * @var SendResponseEvent
     */
    protected $event;

    /**
     * @var EventManagerInterface
     */
    protected $eventManager;

    /**
     * Attach the aggregate to the specified event manager
     *
     * @param  EventManagerInterface $events
     * @return void
     */
    public function attach(EventManagerInterface $events)
    {
        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_ERROR
            , array($this, 'throwException')
            , -100000
        );
    }

    /**
     * Finally Throw Exception Catched By App.
     *
     * @param  MvcEvent $e
     *
     * @throws \Exception
     */
    public function throwException($e)
    {
        if ($e->getError()
            && $e->getError() instanceof \Exception
            && $e->getParam('throwException', true)
        )
            throw $e->getError();
    }

    /**
     * Detach aggregate listeners from the specified event manager
     *
     * @param  EventManagerInterface $events
     * @return void
     */
    public function detach(EventManagerInterface $events)
    {
        foreach ($this->listeners as $index => $listener) {
            if ($events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }
}
