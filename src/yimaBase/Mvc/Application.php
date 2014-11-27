<?php
namespace yimaBase\Mvc;

class Application extends \Zend\Mvc\Application
{
    /**
     * @inheritdoc
     */
    public function run()
    {
        try
        {
            $result = parent::run();
        }
        catch(\Exception $e)
        {
            // Set Accrued Exception as MVC Error
            $this->getMvcEvent()->setError($e);
            $this->getEventManager()->trigger(MvcEvent::EVENT_ERROR, $this->getMvcEvent());
            // with default SendExceptionListener
            // Throw accrued exception so we may don't reach this lines below
            // ...
            $this->run();

            $result = false;
        }

        return $result;
    }
}
