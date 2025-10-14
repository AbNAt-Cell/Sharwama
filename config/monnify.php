<?php

/*
 * This file is part of the Laravel Monnify package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Monnify Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Monnify settings. Monnify is a payment gateway srevice
    | provider.
    |
    |
    */

    /**
     * Api key From Monnify
     */
    'api_key' => env('MK_TEST_8AGWBDUSGS'),

    /**
     * Secret key From Monnify
     */
    'secret_key' => env('Y4FPBP1T72CGCP7RCVWNQCR80TYJ40AQ'),

    /**
     * Api contract code From Monnify
     */
    'contract_code' => env('2295851286'),

    /**
     * Api Wallet number From Monnify
     */
    'wallet_number' => env('2631913247'),

    /**
     * Api Account number From Monnify
     */
    'account_number' => env('2631913247'),

    /**
     * Monnify environment: SANDBOX or LIVE
     * default: 'SANDBOX'
     */
    'environment' => env('https://sandbox.monnify.com', 'SANDBOX'),
];