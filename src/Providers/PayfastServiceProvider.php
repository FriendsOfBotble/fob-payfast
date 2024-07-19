<?php

namespace FriendsOfBotble\Payfast\Providers;

use FriendsOfBotble\Payfast\Contracts\Payfast as PayfastContract;
use FriendsOfBotble\Payfast\ObjectValues\PayfastToken;
use FriendsOfBotble\Payfast\Payfast;
use Botble\Base\Traits\LoadAndPublishDataTrait;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class PayfastServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public const MODULE_NAME = 'payfast';

    public function register(): void
    {
        if (! is_plugin_active('payment')) {
            return;
        }

        $this->app->singleton(
            PayfastContract::class,
            fn (Application $app) => new Payfast(
                new PayfastToken(
                    get_payment_setting('merchant_id', self::MODULE_NAME, ''),
                    get_payment_setting('merchant_key', self::MODULE_NAME, ''),
                    get_payment_setting('merchant_passphrase', self::MODULE_NAME, '')
                )
            )
        );
    }

    public function boot(): void
    {
        if (! is_plugin_active('payment')) {
            return;
        }

        $this->setNamespace('plugins/payfast')
            ->loadAndPublishTranslations()
            ->loadAndPublishViews()
            ->publishAssets()
            ->loadRoutes();

        $this->app->booted(function () {
            $this->app->register(HookServiceProvider::class);
        });
    }
}
