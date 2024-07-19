<?php

namespace FriendsOfBotble\Payfast\Contracts;

interface Payfast
{
    public function renderCheckoutForm(array $data): void;

    public function transactionId(): string;

    public function formatAmount(mixed $amount): float;

    public function validIpAddress(): bool;

    public function validPaymentData(float $amount, array $data): bool;

    public function validSignature(array $data): bool;

    public function validServerConfirmation(array $data): bool;
}
