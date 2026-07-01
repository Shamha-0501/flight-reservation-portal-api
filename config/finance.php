<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | Currency used for agency-controlled prices, add-ons, and internal fees.
    | Duffel-supplied ticket amounts can still arrive in supplier currency.
    |
    */
    'default_currency' => env('APP_DEFAULT_CURRENCY', 'LKR'),

    /*
    |--------------------------------------------------------------------------
    | Exchange Rates
    |--------------------------------------------------------------------------
    |
    | Live defaults captured on 2026-07-01. Override via env when needed.
    | Values are LKR per 1 unit of source currency.
    |
    */
    'exchange_rates' => [
        'USD' => (float) env('FX_USD_TO_LKR', 335.896442),
        'EUR' => (float) env('FX_EUR_TO_LKR', 383.144531),
        'AED' => (float) env('FX_AED_TO_LKR', 91.462612),
        'SGD' => (float) env('FX_SGD_TO_LKR', 259.532205),
        'LKR' => 1.0,
    ],
];
