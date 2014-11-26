<?php
namespace yimaBase\Mvc\View\Listener;

use yimaBase\Mvc\MvcEvent;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventManagerInterface;
use Zend\Feed\PubSubHubbub\HttpResponse;
use Zend\View\Model\ModelInterface;
use Zend\XmlRpc\Response;

class ExceptionMvcStrategyListener extends AbstractListenerAggregate
{
    /**
     * Name of exception template
     * @var string
     */
    protected $exceptionTemplate = 'spec/error';

    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_ERROR,
            array($this, 'prepareExceptionViewModel'),
            -10000
        );
    }

    /**
     * Set the exception template
     *
     * @param  string $exceptionTemplate

     * @return $this
     */
    public function setExceptionTemplate($exceptionTemplate)
    {
        $this->exceptionTemplate = (string) $exceptionTemplate;

        return $this;
    }

    /**
     * Retrieve the exception template
     *
     * @return string
     */
    public function getExceptionTemplate()
    {
        return $this->exceptionTemplate;
    }

    /**
     * Create an exception view model, and set the HTTP status code
     *
     * @param  MvcEvent $e
     *
     * @return void|true
     */
    public function prepareExceptionViewModel($e)
    {
        // Do nothing if no error in the event
        $error = $e->getError();

        switch (1) {
            case $error instanceof \Exception:
            case $error instanceof \ErrorException:
                /** @var \Zend\Http\PhpEnvironment\Response $response */
                $response = $e->getResponse();
                if (!$response) {
                    $response = new HttpResponse();
                    $response->setStatusCode(500);
                    $e->setResponse($response);
                } else
                    if ($response->getStatusCode() === 200)
                        $response->setStatusCode(500);

                // -----

                $result = $e->getResult();
                if ($result instanceof \Zend\Http\Response)
                    // Already Have A Response As Result
                    return true;

                $model =  ($result instanceof ModelInterface)
                    ? $result
                    : $e->getApplication()
                        ->getServiceManager()
                        ->get('ViewManager')->getViewModel();

                $model->setVariable('content'/*'exception'*/
                    , new \Exception('An error occurred during execution; please try again later.', null, $error)
                );

                $model->setTemplate($this->getExceptionTemplate());

                $e->setResult($model);

                $e->stopPropagation();

                break;
        }
    }
}
 