<?php

namespace FriendsOfBotble\Payfast\Providers;

use Botble\Payment\Facades\PaymentMethods;
use FriendsOfBotble\Payfast\Contracts\Payfast as PayfastServiceContract;
use FriendsOfBotble\Payfast\Services\PayfastPaymentService;
use Botble\Ecommerce\Models\Currency as CurrencyEcommerce;
use Botble\JobBoard\Models\Currency as CurrencyJobBoard;
use Botble\Payment\Enums\PaymentMethodEnum;
use Botble\Payment\Supports\PaymentHelper;
use Collective\Html\HtmlFacade as Html;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class HookServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        add_filter(PAYMENT_METHODS_SETTINGS_PAGE, function (string|null $settings) {
            $name = 'Payfast';
            $description = trans('plugins/payfast::payfast.description');
            $link = 'https://www.payfast.com/';
            $image = asset('vendor/core/plugins/payfast/images/payfast.png');
            $moduleName = PayfastServiceProvider::MODULE_NAME;
            $status = (bool)get_payment_setting('status', $moduleName);

            return $settings . view(
                'plugins/payfast::settings',
                compact('name', 'description', 'link', 'image', 'moduleName', 'status')
            )->render();
        }, 999);

        add_filter(BASE_FILTER_ENUM_ARRAY, function (array $values, string $class): array {
            if ($class === PaymentMethodEnum::class) {
                $values['PAYFAST'] = PayfastServiceProvider::MODULE_NAME;
            }

            return $values;
        }, 999, 2);

        add_filter(BASE_FILTER_ENUM_LABEL, function ($value, $class): string {
            if ($class === PaymentMethodEnum::class && $value === PayfastServiceProvider::MODULE_NAME) {
                $value = 'Payfast';
            }

            return $value;
        }, 999, 2);

        add_filter(BASE_FILTER_ENUM_HTML, function (string $value, string $class): string {
            if ($class === PaymentMethodEnum::class && $value === PayfastServiceProvider::MODULE_NAME) {
                $value = Html::tag(
                    'span',
                    PaymentMethodEnum::getLabel($value),
                    ['class' => 'label-success status-label']
                )->toHtml();
            }

            return $value;
        }, 999, 2);

        add_filter(PAYMENT_FILTER_ADDITIONAL_PAYMENT_METHODS, function (string|null $html, array $data): string|null {
            if (get_payment_setting('status', PayfastServiceProvider::MODULE_NAME)) {
                $supportedCurrencies = $this->app->make(PayfastPaymentService::class)->getSupportedCurrencies();
                $currencies = get_all_currencies()
                    ->filter(fn ($currency) => in_array($currency->title, $supportedCurrencies));

                PaymentMethods::method(PayfastServiceProvider::MODULE_NAME, [
                    'html' => view(
                        'plugins/payfast::method',
                        array_merge($data, [
                            'moduleName' => PayfastServiceProvider::MODULE_NAME,
                            'supportedCurrencies' => $supportedCurrencies,
                            'currencies' => $currencies,
                        ]),
                    )->render(),
                ]);
            }

            return $html;
        }, 999, 2);

        add_filter(PAYMENT_FILTER_GET_SERVICE_CLASS, function (string|null $data, string $value): string|null {
            if ($value === PayfastServiceProvider::MODULE_NAME) {
                $data = PayfastPaymentService::class;
            }

            return $data;
        }, 20, 2);

        add_filter(PAYMENT_FILTER_AFTER_POST_CHECKOUT, function (array $data, Request $request): array {
            if ($data['type'] !== PayfastServiceProvider::MODULE_NAME) {
                return $data;
            }

            $currentCurrency = get_application_currency();

            $paymentData = apply_filters(PAYMENT_FILTER_PAYMENT_DATA, [], $request);

            if (strtoupper($currentCurrency->title) !== 'ZAR') {
                $currency = is_plugin_active('ecommerce') ? CurrencyEcommerce::class : CurrencyJobBoard::class;
                $supportedCurrency = $currency::query()->where('title', 'ZAR')->first();

                if ($supportedCurrency) {
                    $paymentData['currency'] = strtoupper($supportedCurrency->title);
                    if ($currentCurrency->is_default) {
                        $paymentData['amount'] = $paymentData['amount'] * $supportedCurrency->exchange_rate;
                    } else {
                        $paymentData['amount'] = format_price(
                            $paymentData['amount'] / $currentCurrency->exchange_rate,
                            $currentCurrency,
                            true
                        );
                    }
                }
            }

            $supportedCurrencies = $this->app->make(PayfastPaymentService::class)->getSupportedCurrencies();

            if (! in_array($paymentData['currency'], $supportedCurrencies)) {
                $data['error'] = true;
                $data['message'] = __(":name doesn't support :currency. List of currencies supported by :name: :currencies.", ['name' => 'Payfast', 'currency' => $data['currency'], 'currencies' => implode(', ', $supportedCurrencies)]);

                return $data;
            }

            $orderIds = $paymentData['order_id'];

            try {
                $payfast = $this->app->make(PayfastServiceContract::class);
                $chargeId = $payfast->transactionId();

                $returnUrl = PaymentHelper::getRedirectURL($paymentData['checkout_token']);

                if (is_plugin_active('job-board')) {
                    $returnUrl = $returnUrl . '?charge_id=' . $chargeId;
                }

                $payfast->renderCheckoutForm([
                    'return_url' => $returnUrl,
                    'notify_url' => route('payment.payfast.webhook'),
                    'name_first' => Str::of($paymentData['address']['name'])->before(' ')->toString(),
                    'name_last' => Str::of($paymentData['address']['name'])->after(' ')->toString(),
                    'email_address' => $paymentData['address']['email'],
                    'cell_number' => $paymentData['address']['phone'],
                    'm_payment_id' => $chargeId,
                    'amount' => $payfast->formatAmount($paymentData['amount']),
                    'item_name' => $paymentData['description'],
                    'custom_str1' => $orderIds[0],
                    'custom_str2' => $paymentData['customer_id'],
                    'custom_str3' => addslashes($paymentData['customer_type']),
                    'custom_str4' => $paymentData['checkout_token'],
                    'custom_str5' => $paymentData['currency'],
                ]);
            } catch (Exception $exception) {
                $data['error'] = true;
                $data['message'] = json_encode($exception->getMessage());
            }

            return $data;
        }, 999, 2);
    }
}
