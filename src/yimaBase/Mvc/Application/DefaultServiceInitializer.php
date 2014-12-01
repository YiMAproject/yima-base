<?php
namespace yimaBase\Mvc\Application;

use Zend\EventManager\EventManagerAwareInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\ServiceManager\InitializerInterface;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\ServiceManagerAwareInterface;

class DefaultServiceInitializer implements InitializerInterface
{

    /**
     * Initialize
     *
     * @param $instance
     * @param ServiceLocatorInterface $serviceLocator
     *
     * @return mixed
     */
    public function initialize($instance, ServiceLocatorInterface $serviceLocator)
    {
        if ($instance instanceof EventManagerAwareInterface)
            if ($instance->getEventManager() instanceof EventManagerInterface) {
                $instance->getEventManager()->setSharedManager(
                    $serviceLocator->get('SharedEventManager')
                );
            } else {
                $instance->setEventManager($serviceLocator->get('EventManager'));
            }

        if ($instance instanceof ServiceManagerAwareInterface)
            $instance->setServiceManager($serviceLocator);

        if ($instance instanceof ServiceLocatorAwareInterface)
            $instance->setServiceLocator($serviceLocator);
    }
}
