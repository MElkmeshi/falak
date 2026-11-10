<?php

use Falak\Billing\Http\Controllers\MoamalatController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/billing/moamalat', [MoamalatController::class, 'notify'])
    ->middleware('throttle:billing-payment-webhooks')
    ->name('billing.moamalat.notify');
