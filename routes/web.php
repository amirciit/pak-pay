<?php

declare(strict_types=1);

use PakPay\PakPay\Http\Controllers\PakPayRedirectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PakPay routes
|--------------------------------------------------------------------------
|
| A single GET route that renders the self-submitting POST form used by
| hosted-redirect gateways (JazzCash / EasyPaisa / NayaPay). The URL prefix
| and middleware are configurable via config('pakpay.route').
|
*/

Route::group([
    'prefix' => config('pakpay.route.prefix', 'pakpay'),
    'middleware' => config('pakpay.route.middleware', ['web']),
], function (): void {
    Route::get('redirect/{token}', PakPayRedirectController::class)
        ->name('pakpay.redirect');
});
