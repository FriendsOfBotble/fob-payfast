<?php

namespace FriendsOfBotble\Payfast\ObjectValues;

use FriendsOfBotble\Payfast\Exceptions\MerchantConfigurationException;

class PayfastToken
{
    public function __construct(
        protected string $merchantId,
        protected string $merchantKey,
        protected string $merchantPassphrase,
    ) {
        if (empty($this->merchantId) || empty($this->merchantKey) || empty($this->merchantPassphrase)) {
            throw new MerchantConfigurationException();
        }
    }

    public function getMerchantId(): string
    {
        return $this->merchantId;
    }

    public function getMerchantKey(): string
    {
        return $this->merchantKey;
    }

    public function getMerchantPassphrase(): string
    {
        return $this->merchantPassphrase;
    }
}
