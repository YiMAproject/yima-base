<?php
namespace yimaBase\Mvc\Exception;

class RouteNotFoundException extends \Exception
{
    const EXCEPTION_NOT_FOUND_DEF_MESSAGE = 'Page Not Found';

    public function __construct(
        $message = self::EXCEPTION_NOT_FOUND_DEF_MESSAGE,
        $code = 404,
        \Exception $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }
}
