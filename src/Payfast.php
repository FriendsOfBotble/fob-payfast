<?php

namespace FriendsOfBotble\Payfast;

use FriendsOfBotble\Payfast\Contracts\Payfast as PayfastContract;
use FriendsOfBotble\Payfast\Exceptions\InvalidEnvironmentException;
use FriendsOfBotble\Payfast\ObjectValues\PayfastToken;
use FriendsOfBotble\Payfast\Providers\PayfastServiceProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Payfast implements PayfastContract
{
    protected array $endpoint = [
        'live' => 'https://www.payfast.co.za/eng',
        'sandbox' => 'https://sandbox.payfast.co.za/eng',
    ];

    public function __construct(protected PayfastToken $payfastToken)
    {
    }

    public function renderCheckoutForm(array $data): void
    {
        echo view('plugins/payfast::form', [
            'data' => $this->getDataForCheckoutForm($data),
            'action' => $this->getEndpointUrl('/process'),
        ]);

        exit();
    }

    public function transactionId(): string
    {
        return Str::random(10);
    }

    public function formatAmount(mixed $amount): float
    {
        return (float) number_format((float)sprintf('%.2f', $amount), 2, '.', '');
    }

    public function validIpAddress(): bool
    {
        if (app()->environment('local')) {
            return true;
        }

        $validHosts = [
            'www.payfast.co.za',
            'sandbox.payfast.co.za',
            'w1w.payfast.co.za',
            'w2w.payfast.co.za',
        ];

        $validIps = [];

        foreach ($validHosts as $hostname) {
            $ips = gethostbynamel($hostname);

            if ($ips === false) {
                continue;
            }

            $validIps = array_merge($validIps, $ips);
        }

        $referrerIp = request()->server('REMOTE_ADDR');

        if (! in_array($referrerIp, array_unique($validIps), true)) {
            return false;
        }

        return true;
    }

    public function validPaymentData(float $amount, array $data): bool
    {
        return ! (abs($amount - (float) $data['amount_gross']) > 0.01);
    }

    public function validSignature(array $data): bool
    {
        $signature = $this->generateSignature($data, $this->payfastToken->getMerchantPassphrase());

        return $data['signature'] === $signature;
    }

    public function validServerConfirmation(array $data): bool
    {
        $response = $this->request()->post('/query/validate', $data)->body();

        return $response === 'VALID';
    }

    protected function request(): PendingRequest
    {
        return Http::baseUrl($this->getEndpointUrl());
    }

    protected function getEndpointUrl(string $uri = null): string
    {
        $environment = get_payment_setting('environment', PayfastServiceProvider::MODULE_NAME);

        if (! isset($this->endpoint[$environment])) {
            throw new InvalidEnvironmentException();
        }

        return $this->endpoint[$environment] . $uri;
    }

    protected function generateSignature($data, $passPhrase = null): string
    {
        $pfOutput = '';

        foreach ($data as $key => $val) {
            if ($key === 'signature') {
                continue;
            }

            if ($val !== '') {
                $pfOutput .= $key . '=' . urlencode(trim($val)) . '&';
            }
        }

        $getString = substr($pfOutput, 0, -1);

        if ($passPhrase !== null) {
            $getString .= '&passphrase=' . urlencode(trim($passPhrase));
        }

        return md5($getString);
    }

    protected function getDataForCheckoutForm(array $data): array
    {
        if (isset($data['cell_number']) && ! $this->validateCellNumber($data['cell_number'])) {
            unset($data['cell_number']);
        }

        $data = array_merge([
            'merchant_id' => $this->payfastToken->getMerchantId(),
            'merchant_key' => $this->payfastToken->getMerchantKey(),
        ], $data);

        $data['signature'] = $this->generateSignature($data, $this->payfastToken->getMerchantPassphrase());

        return $data;
    }

    protected function validateCellNumber(string $cellNumber): bool
    {
        if (! str_starts_with($cellNumber, '08')) {
            return false;
        }

        if (strlen($cellNumber) !== 10) {
            return false;
        }

        return true;
    }
}
