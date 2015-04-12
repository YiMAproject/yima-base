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
     *
     */
    function attach(EventManagerInterface $events)
    {
        /** @var \Zend\EventManager\EventManager $events */
        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_ERROR,
            array($this, 'onMvcErrorInjectResponse'),
            10000
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
    }

    /**
     * Inject Status Response Code related on Exception
     *
     * @param  MvcEvent $e
     *
     * @return void
     */
    function onMvcErrorInjectResponse($e)
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
                $this->__getResponseCodeFromException($error)
            );
        // <<<< }

        $this->error = $error;
    }

        protected function __getResponseCodeFromException(\Exception $e)
        {
            $resCode = $e->getCode();

            $const = 'Zend\Http\Response' . '::STATUS_CODE_' . $resCode;
            if (!is_numeric($resCode) || !defined($const))
                // If Error Code Is Not Valid Response Code Choose 500
                $resCode = 500;

            return $resCode;
        }

    /**
     * @param  MvcEvent $e
     *
     * @return void
     */
    function onMvcErrorInjectViewTemplate($e)
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
            $this->__getExceptionTemplate($this->error)
        );

        $e->setResult($result);
    }

        /**
         * Retrieve the exception template
         *
         * @return string
         */
        protected function __getExceptionTemplate($e)
        {
            $config = $this->sm->get('Config');
            $config = isset($config['view_manager'])
            && (
                is_array($config['view_manager'])
                || $config['view_manager'] instanceof \ArrayAccess
            )
                ? $config['view_manager']
                : array();

            // TODO get default layout by static method or via config
            $exceptionTemplate = 'spec/error'; //default

            $exClass = get_class($e);
            while($exClass) {
                if (isset($config['layout_exception']) && isset($config['layout_exception'][$exClass])) {
                    $exceptionTemplate = $config['layout_exception'][$exClass];
                    break;
                }

                $exClass = get_parent_class($exClass);
            }

            return $exceptionTemplate;
        }

    /**
     * @param  MvcEvent $e
     *
     * @return void
     */
    function onMvcErrorInjectViewModel($e)
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
