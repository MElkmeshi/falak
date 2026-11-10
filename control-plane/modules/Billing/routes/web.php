<?php

use Falak\Billing\Http\Controllers\BillingAdminController;
use Falak\Billing\Http\Controllers\BillingController;
use Falak\Billing\Http\Controllers\CheckoutController;
use Falak\Billing\Http\Controllers\CustomerController;
use Falak\Billing\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

if (config('billing.landing')) {
    Route::get('/', [LandingController::class, 'index'])->name('home');
}

Route::get('start/{plan?}', [LandingController::class, 'start'])->name('billing.start');

Route::middleware(['auth', 'org'])->group(function () {
    Route::get('home', [CustomerController::class, 'home'])->name('space.home');
    Route::get('apps', [CustomerController::class, 'apps'])->name('space.apps');
    Route::post('apps/{site}/restart', [CustomerController::class, 'restart'])->name('space.apps.restart');
    Route::delete('apps/{site}', [CustomerController::class, 'destroy'])->name('space.apps.destroy');
    Route::get('store', [CustomerController::class, 'store'])->name('space.store');
    Route::get('account', [CustomerController::class, 'account'])->name('space.account');
    Route::get('billing', [CustomerController::class, 'plan'])->name('billing.index');
    Route::post('billing/checkout', [CheckoutController::class, 'start'])->name('billing.checkout');
    Route::get('billing/payments/{payment}', [CheckoutController::class, 'show'])->name('billing.payments.show');
    Route::post('billing/payments/{payment}/confirm', [CheckoutController::class, 'confirm'])->name('billing.payments.confirm');
    Route::post('billing/apps/{slug}', [BillingController::class, 'deployApp'])->name('billing.apps.deploy');

    Route::get('billing/admin', [BillingAdminController::class, 'index'])->name('billing.admin');
    Route::post('billing/admin/payments/{payment}/approve', [BillingAdminController::class, 'approve'])->name('billing.admin.approve');
    Route::post('billing/admin/payments/{payment}/reject', [BillingAdminController::class, 'reject'])->name('billing.admin.reject');
    Route::post('billing/admin/spaces/{space}/{action}', [BillingAdminController::class, 'space'])->whereIn('action', ['suspend', 'resume', 'reprovision'])->name('billing.admin.space');
    Route::put('billing/admin/plans/{plan}', [BillingAdminController::class, 'updatePlan'])->name('billing.admin.plans.update');
    Route::post('billing/admin/plans', [BillingAdminController::class, 'storePlan'])->name('billing.admin.plans.store');
});
