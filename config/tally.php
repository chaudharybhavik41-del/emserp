<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tally Export Defaults
    |--------------------------------------------------------------------------
    |
    | These values are used when generating XML for manual import into
    | Tally Prime. The target company name is optional but recommended
    | when the Tally instance hosts multiple companies.
    |
    */
    'company_name' => env('TALLY_COMPANY_NAME', ''),
    'purchase_voucher_type' => env('TALLY_PURCHASE_VOUCHER_TYPE', 'Purchase'),
];
