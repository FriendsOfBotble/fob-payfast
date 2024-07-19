<?php

namespace FriendsOfBotble\Payfast\Exceptions;

use Exception;

class InvalidEnvironmentException extends Exception
{
    public function __construct(string $message = 'Invalid Payfast environment. Supported: sandbox, live', int $code = 400)
    {
        parent::__construct($message, $code);
    }
}
