<?php
namespace yimaBase\Mvc\Exception;

class MvcException extends \Exception
{
    const EXCEPTION_MVC_DEF_MESSAGE = 'Page Not Available Now.';

    public function __construct(
        $message = self::EXCEPTION_MVC_DEF_MESSAGE,
        $code = 500,
        \Exception $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }
}
