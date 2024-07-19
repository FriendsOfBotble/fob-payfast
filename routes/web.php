<?php

use FriendsOfBotble\Payfast\Http\Controllers\PayfastController;
use Illuminate\Support\Facades\Route;

Route::middleware(['core'])->prefix('payment/payfast')->name('payment.payfast.')->group(function () {
    Route::post('webhook', [PayfastController::class, 'webhook'])->name('webhook');
});
