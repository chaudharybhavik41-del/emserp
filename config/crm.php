<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CRM Quotation - Currency (Single Currency)
    |--------------------------------------------------------------------------
    |
    | Multi-currency is intentionally NOT supported for CRM quotations.
    | Configure the single currency used across the quotation module here.
    |
    */
    'currency_code'   => 'INR',
    'currency_symbol' => '₹',

    /*
    |--------------------------------------------------------------------------
    | CRM Quotation - Default Cost Breakup Heads
    |--------------------------------------------------------------------------
    |
    | Used as a quick template for rate analysis / cost breakup. Users can still
    | add/remove/edit heads per quotation line.
    |
    */
    'quotation_cost_heads' => [
        ['code' => 'FAB_LAB',   'name' => 'Fabrication labour'],
        ['code' => 'CONS',      'name' => 'Consumables'],
        ['code' => 'PAINT_LAB', 'name' => 'Painting labour'],
        ['code' => 'PAINT_MAT', 'name' => 'Paint material'],
        ['code' => 'TRANSPORT', 'name' => 'Transport'],
        ['code' => 'OTHER',     'name' => 'Other'],
    ],

];
