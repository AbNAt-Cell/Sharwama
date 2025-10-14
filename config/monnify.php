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
    | Here you may configure your Monnify settings. Monnify is a payment gateway service
    | provider. Get your credentials from https://monnify.com
    |
    */

    /**
     * Api key From Monnify Dashboard
     */
    'api_key' => env('MONNIFY_API_KEY', 'MK_TEST_8AGWBDUSGS'),

    /**
     * Secret key From Monnify Dashboard
     */
    'secret_key' => env('MONNIFY_SECRET_KEY', 'Y4FPBP1T72CGCP7RCVWNQCR80TYJ40AQ'),

    /**
     * Contract code From Monnify Dashboard
     */
    'contract_code' => env('MONNIFY_CONTRACT_CODE', '2295851286'),

    /**
     * Wallet number From Monnify Dashboard (Optional)
     */
    'wallet_number' => env('MONNIFY_WALLET_NUMBER', '2631913247'),

    /**
     * Account number From Monnify Dashboard (Optional)
     */
    'account_number' => env('MONNIFY_ACCOUNT_NUMBER', '2631913247'),

    /**
     * Monnify environment: 'SANDBOX' or 'LIVE'
     * SANDBOX: https://sandbox.monnify.com
     * LIVE: https://api.monnify.com
     */
    'environment' => env('MONNIFY_ENVIRONMENT', 'SANDBOX'),

    /**
     * Base URLs for API endpoints
     */
    'base_url' => [
        'SANDBOX' => 'https://sandbox.monnify.com',
        'LIVE' => 'https://api.monnify.com',
    ],
];
