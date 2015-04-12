<?php
namespace yimaBase\Mvc\Exception;

class ControllerNotFoundException extends RouteNotFoundException
{
    const EXCEPTION_NOT_CONTROLLER_DEF_MESSAGE = 'Controller Not Found';

    public function __construct(
        $message = self::EXCEPTION_NOT_CONTROLLER_DEF_MESSAGE,
        $code = 404,
        \Exception $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }
}
