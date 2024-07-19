<?php

namespace FriendsOfBotble\Payfast\Http\Controllers;

use FriendsOfBotble\Payfast\Contracts\Payfast;
use FriendsOfBotble\Payfast\Providers\PayfastServiceProvider;
use Botble\Base\Http\Controllers\BaseController;
use Botble\Payment\Enums\PaymentStatusEnum;
use Illuminate\Http\Request;

class PayfastController extends BaseController
{
    public function webhook(Request $request, Payfast $payfast): void
    {
        if (
            ! $payfast->validServerConfirmation($request->input()) ||
            ! $payfast->validSignature($request->input()) ||
            ! $payfast->validIpAddress()
        ) {
            return;
        }

        $status = match ($request->input('payment_status')) {
            'COMPLETE' => PaymentStatusEnum::COMPLETED,
            'CANCELLED' => PaymentStatusEnum::FAILED,
            default => PaymentStatusEnum::PENDING,
        };

        do_action(PAYMENT_ACTION_PAYMENT_PROCESSED, [
            'order_id' => $request->input('custom_str1'),
            'amount' => $request->input('amount'),
            'currency' => $request->input('custom_str_5'),
            'charge_id' => $request->input('m_payment_id'),
            'payment_channel' => PayfastServiceProvider::MODULE_NAME,
            'status' => $status,
            'customer_id' => $request->input('custom_str2'),
            'customer_type' => stripslashes($request->input('custom_str3')),
            'payment_type' => 'direct',
        ], $request);
    }
}
