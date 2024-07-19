<?php

namespace FriendsOfBotble\Payfast\Services;

class PayfastPaymentService extends PaymentServiceAbstract
{
    public function isSupportRefundOnline(): bool
    {
        return true;
    }

    public function getSupportedCurrencies(): array
    {
        return [
            'ZAR',
        ];
    }

    public function refund(string $chargeId, float $amount): array
    {
        return [];
    }
}
