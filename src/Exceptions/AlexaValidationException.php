<?php


namespace Humps\AlexaRequest\Exceptions;


use Exception;

class AlexaValidationException extends Exception
{
    /**
     * AlexaValidationException constructor.
     * @param string $message
     * @param int $code
     * @param Exception|null $previous
     */
    public function __construct($message, $code = 400, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}