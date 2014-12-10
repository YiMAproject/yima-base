<?php
namespace yimaBase\Mvc\Listener;

use yimaBase\Mvc\Exception\RouteNotFoundException;
use yimaBase\Mvc\MvcEvent;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\Router\RouteMatch;

/**
 * Detect Route Match
 * - throw exception if route not match,
 *   so put this routing listener on last
 *   as routing strategy
 */
class RouteListener implements ListenerAggregateInterface
{
    /**
     * @var \Zend\Stdlib\CallbackHandler[]
     */
    protected $listeners = array();

    /**
     * Attach to an event manager
     *
     * @param  EventManagerInterface $events
     * @return void
     */
    public function attach(EventManagerInterface $events)
    {
        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_ROUTE
            , array($this, 'onRoute')
            , 0
        );
    }

    /**
     * Detach all our listeners from the event manager
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

    /**
     * Listen to the "route" event and attempt to route the request
     *
     * If no matches are returned, triggers "dispatch.error" in order to
     * create a 404 response.
     *
     * Seeds the event with the route match on completion.
     *
     * @param  MvcEvent $e
     * @throws RouteNotFoundException
     *
     * @return null|RouteMatch
     */
    public function onRoute($e)
    {
        $request    = $e->getRequest();
        $router     = $e->getRouter();
        $routeMatch = $router->match($request);

        if (!$routeMatch instanceof RouteMatch)
            throw new RouteNotFoundException();

        $e->setRouteMatch($routeMatch);

        return $routeMatch;
    }
}
