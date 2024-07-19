<?php

namespace FriendsOfBotble\Payfast\Exceptions;

use Exception;

class MerchantConfigurationException extends Exception
{
    public function __construct(string $message = 'Payfast merchant id, key or passphrase is not set.', int $code = 400)
    {
        parent::__construct($message, $code);
    }
}
