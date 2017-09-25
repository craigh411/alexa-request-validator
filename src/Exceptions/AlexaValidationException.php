<?php


namespace Humps\AlexaRequest\Exceptions;


use Exception;

class AlexaValidationException extends Exception
{
    /**
     * AlexaValidationException constructor.
     * @param string $message
     * @param int $code - response code, by default this is 400 (invalid request)
     * @param Exception|null $previous
     */
    public function __construct($message, $code = 400, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}