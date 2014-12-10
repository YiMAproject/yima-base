<?php
namespace yimaBase\Mvc\View\Listener;

use yimaBase\Mvc\Exception\RouteNotFoundException;
use yimaBase\Mvc\MvcEvent;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventManagerInterface;
use Zend\Http\PhpEnvironment\Response;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\ServiceManager;
use Zend\View\Model\ClearableModelInterface;
use Zend\View\Model\ModelInterface;
use Zend\View\Model\ViewModel;

/**
 * @TODO Move to ViewManager
 */
class ExceptionMvcStrategyListener extends AbstractListenerAggregate
    implements
    ServiceLocatorAwareInterface // to use ViewManager Merged Config
{
    /**
     * Name of exception template
     * @var string
     */
    protected $exceptionTemplate;

    /**
     * @var \Exception Error Detected
     */
    protected $error;

    /**
     * @var ServiceManager
     */
    protected $sm;

    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events)
    {
        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_ERROR,
            array($this, 'onMvcErrorInjectResponse'),
            -10000
        );

        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_ERROR,
            array($this, 'onMvcErrorInjectViewTemplate'),
            -10000
        );

        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_ERROR,
            array($this, 'onMvcErrorInjectViewModel'),
            -10000
        );

        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_ERROR,
            array($this, 'onMvcErrorRenderOutput'),
            -100000
        );
    }

    /**
     * Retrieve the exception template
     *
     * @return string
     */
    public function getExceptionTemplate($e)
    {
        $config = $this->sm->get('Config');
        $config = isset($config['view_manager']) && (is_array($config['view_manager']) || $config['view_manager'] instanceof ArrayAccess)
            ? $config['view_manager']
            : array();

        $exceptionTemplate = 'spec/error'; //default

        $exClass = get_class($e);
        if (isset($config['layout_exception']) && isset($config['layout_exception'][$exClass])) {
            $exceptionTemplate = $config['layout_exception'][$exClass];
        }

        return $exceptionTemplate;
    }

    /**
     * @param  MvcEvent $e
     *
     * @return void
     */
    public function onMvcErrorInjectResponse($e)
    {
        // Do nothing if no error in the event
        $error = $e->getError();
        if(empty($error) || !$error instanceof \Exception)
            // We do nothing without Exception
            return;

        // Set Response Status Code >>>> {
        /** @var \Zend\Http\PhpEnvironment\Response $response */
        $response = $e->getResponse();
        if (!$response) {
            $response = new Response();
            $e->setResponse($response);
        }

        if ($response->getStatusCode() === 200)
            // Change Response Code only if is Success
            $response->setStatusCode(
                $this->getResponseCodeFromException($error)
            );
        // <<<< }

        $this->error = $error;
    }

    /**
     * @param  MvcEvent $e
     *
     * @return void
     */
    public function onMvcErrorInjectViewTemplate($e)
    {
        if (!$this->error)
            // We have no error detected on prev. event
            return;

        $result = $e->getResult();
        if ($result instanceof Response)
            // Already Have A Response As Result
            return;

        $result = ($result instanceof ModelInterface)
            ? $result
            : new ViewModel();

        $result->setVariable('exception'
            , new \Exception(
                'An error occurred during execution; please try again later.'
                , null
                , $e->getError()
            )
        );

        $result->setVariable('original_exception', $e->getError());

        $result->setVariable('display_exceptions', (error_reporting() != 0));

        $result->setTemplate(
            $this->getExceptionTemplate($this->error)
        );

        $e->setResult($result);
    }

    /**
     * @param  MvcEvent $e
     *
     * @return void
     */
    public function onMvcErrorInjectViewModel($e)
    {
        $result = $e->getResult();
        if (!$result instanceof ModelInterface)
            return;

        $model = $e->getViewModel();

        if ($result->terminate()) {
            $e->setViewModel($result);
            return;
        }

        if ($e->getError() && $model instanceof ClearableModelInterface) {
            $model->clearChildren();
        }

        $model->addChild($result);
    }

    /**
     * @param  MvcEvent $e
     *
     * @return void
     */
    public function onMvcErrorRenderOutput($e)
    {
        // Don't throw exception on last
        // Used Within ThrowExceptionListener
        $e->setParam('throwException', false);

        // Trigger Events To Have Response Result
        $events = $e->getApplication()
            ->getEventManager();
        $events->trigger(MvcEvent::EVENT_RENDER, $e);
        $events->trigger(MvcEvent::EVENT_FINISH, $e);
    }

    /**
     * Get Response Code For Exception
     *
     * @param $exception
     *
     * @return int
     */
    protected function getResponseCodeFromException($exception)
    {
        switch($exception) {
            case $exception instanceof RouteNotFoundException:
                $code = 404;
                break;
            case $exception instanceof \Exception:
            default:
                $code = 500;
        }

        return $code;
    }

    /**
     * Set service locator
     *
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->sm = $serviceLocator;
    }

    /**
     * Get service locator
     *
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->sm;
    }
}
