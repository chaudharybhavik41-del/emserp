<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default company for accounting
    |--------------------------------------------------------------------------
    */
    'default_company_id' => 1,

    /*
    |--------------------------------------------------------------------------
    | Default accounts (by code)
    |--------------------------------------------------------------------------
    | These codes must exist in your "accounts" table. Adjust them to match
    | your actual chart of accounts.
    */
    'default_accounts' => [
        // Inventory / material
        'inventory_raw_material_code'   => 'INV-RM',        // e.g. Raw Material
        'consumables_expense_code'      => 'EXP-CONS',      // e.g. Consumables Expense

        // Fixed Assets (used when item.type.accounting_usage = fixed_asset)
        // If an item (or its subcategory) does not have asset_account_id set,
        // the posting will fall back to this ledger.
        'fixed_asset_machinery_code'    => 'FA-MACHINERY',  // Plant & Machinery / Tools

        // Tool stock / small tools (Phase-C)
        'inventory_tools_code'         => 'INV-TOOLS',
        'tools_with_contractor_code'   => 'TOOLS-WITH-CONTRACTOR',
        'tools_scrap_loss_code'        => 'TOOLS-SCRAP-LOSS',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default groups for auto-created party ledgers
    |--------------------------------------------------------------------------
    */
    'default_groups' => [
        'sundry_debtors'   => 'SUNDRY_DEBTORS',
        'sundry_creditors' => 'SUNDRY_CREDITORS',
    ],

    /*
    |--------------------------------------------------------------------------
    | GST Input accounts
    |--------------------------------------------------------------------------
    | These should be set to your actual ledger codes for Input CGST/SGST/IGST.
    */
    'gst' => [
        'input_cgst_account_code' => 'GST-IN-CGST',
        'input_sgst_account_code' => 'GST-IN-SGST',
        'input_igst_account_code' => 'GST-IN-IGST',
      	// Output GST (Sales, Client RA bills)
        'cgst_output_account_code' => env('GST_CGST_OUTPUT_CODE', 'GST-CGST-OUTPUT'),
        'sgst_output_account_code' => env('GST_SGST_OUTPUT_CODE', 'GST-SGST-OUTPUT'),
        'igst_output_account_code' => env('GST_IGST_OUTPUT_CODE', 'GST-IGST-OUTPUT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | TDS / TCS accounts
    |--------------------------------------------------------------------------
    | TDS payable is a liability; TCS receivable is an asset.
    */
    'tds' => [
        'tds_payable_account_code'    => 'TDS-PAYABLE',
        'tds_receivable_account_code' => 'TDS-RECEIVABLE',
    ],

    'tcs' => [
        'tcs_receivable_account_code' => 'TCS-RECEIVABLE',
    ],
	
  	'store' => [
    'project_wip_material_account_code'        => 'WIP-MATERIAL',       // Dr when project issue
    'factory_consumable_expense_account_code'  => 'FACTORY-CONS-EXP',   // Dr when no project
    'inventory_consumables_account_code'       => 'INV-CONSUMABLES',    // fallback Cr if item has no inventory_account_id
	],


'subcontractor' => [
    // WIP account used in Subcontractor RA posting (Dr Project WIP – Subcontractor)
    'project_wip_account_code' => 'WIP-SUBCON',
],
  
  	 /*
    |--------------------------------------------------------------------------
    | Project Cost Sheet Configuration (DEV-9)
    |--------------------------------------------------------------------------
    | Account codes used to categorize costs in project cost sheet report.
    | Multiple codes can be specified per category.
    */
    'project_costing' => [
        // Material costs (raw material, steel, etc.)
        'material_account_codes' => [
            'WIP-MATERIAL',
            'INV-RM',
        ],
        
        // Consumable costs
        'consumable_account_codes' => [
            'WIP-CONSUMABLES',
            'CONSUMABLE-EXP',
            'FACTORY-CONSUMABLES',
        ],
        
        // Subcontractor costs
        'subcontractor_account_codes' => [
            'WIP-SUBCON',
        ],
        
        // Other direct costs
        'other_direct_account_codes' => [
            'WIP-OTHER',
            'WIP-LABOUR',    // For Phase 2 DPR integration
            'WIP-MACHINE',   // For Phase 2 DPR integration
        ],
    ],


/*
|--------------------------------------------------------------------------
| Project Closing (WIP → COGS)
|--------------------------------------------------------------------------
| Phase 1 approach = Completed Contract Method:
| - Keep costs in WIP during project execution
| - When project is marked COMPLETED, auto-create a DRAFT journal voucher
|   that transfers WIP balances to COGS (review + post by Accounts team)
|
| wip_to_cogs_map format:
|   'WIP-ACCOUNT-CODE' => 'COGS-ACCOUNT-CODE'
*/
'project_close' => [
    'auto_generate_wip_to_cogs_on_completion' => true,
    'completion_status_value'                => 'completed',
    'voucher_series_key'                     => 'journal', // JV series
    'auto_create_missing_cogs_accounts'      => true,
    'cogs_account_group_code'                => 'DIRECT_EXPENSES',
    'wip_to_cogs_map' => [
        'WIP-MATERIAL' => 'COGS-MATERIAL',
        'WIP-SUBCON'   => 'COGS-SUBCON',
    ],
],

  	/*
    |--------------------------------------------------------------------------
    | Voucher Number Series
    |--------------------------------------------------------------------------
    | Prefix patterns for auto-generated voucher numbers.
    */
    'voucher_series' => [
        'purchase'           => 'PB',      // Purchase Bill
        'sales'              => 'SALE',    // Sales/Client RA
        'sales_reversal'     => 'SREV',    // Reversal of sales posting
        'subcontractor_ra'   => 'SCRA',    // Subcontractor RA
        'subcontractor_ra_reversal' => 'REV', // Reversal of subcontractor RA
        'payment'            => 'PMT',     // Payment voucher
        'receipt'            => 'RCT',     // Receipt voucher
        'journal'            => 'JV',      // Journal voucher
        'tools_transfer'     => 'TT',      // Tools custody transfer
        'store_issue'        => 'ISS',     // Store issue
        'contra'             => 'CTR',     // Contra voucher
    ],

    /*
    |--------------------------------------------------------------------------
    | AR/AP Bill Model (for Account Bill Allocation Service)
    |--------------------------------------------------------------------------
    | Configure the model classes used for AR (receivables) and AP (payables)
    */
    'ar_bill_model' => \App\Models\ClientRaBill::class,
    'ap_bill_model' => \App\Models\PurchaseBill::class,

    /*
    |--------------------------------------------------------------------------
    | Financial Year Settings
    |--------------------------------------------------------------------------
    */
    'financial_year' => [
        // Month when financial year starts (4 = April for India)
        'start_month' => 4,
    ],

  
  	'enable_store_issue_posting' => true,

	/*
	|--------------------------------------------------------------------------
	| Ledger Code Mode (Tally-style numeric codes)
	|--------------------------------------------------------------------------
	| manual      => user enters code (current behaviour)
	| numeric_auto=> if code left blank, system generates numeric code like 1001001
	*/
	'ledger_code_mode' => env('ACCOUNTING_LEDGER_CODE_MODE', 'numeric_auto'),
		
  		// Party (auto-ledger) code mode
		// - numeric_auto: Party ledgers also get numeric codes (recommended)
		// - legacy_party_code: keep old behavior (account code derived from party code/name)
		'party_ledger_code_mode' => env('ACCOUNTING_PARTY_LEDGER_CODE_MODE', 'numeric_auto'),

  
  
  
	/*
	|--------------------------------------------------------------------------
	| Prefix setup (Series Key => Prefix)
	|--------------------------------------------------------------------------
	| series_key is resolved as:
	|  - account_groups.code (BANK_ACCOUNTS, SUNDRY_DEBTORS, etc.)
	|  - else DEFAULT_<NATURE>
	|
	| prefix + padded running number becomes code.
	| Example: prefix 1001 + pad 3 => 1001001, 1001002 ...
	*/
	'ledger_code_prefix_by_series_key' => [
    // Assets (1000000 range)
    'CURRENT_ASSETS'   => ['prefix' => '1100', 'pad' => 3, 'start' => 1],
    'BANK_ACCOUNTS'    => ['prefix' => '1110', 'pad' => 3, 'start' => 1],
    'CASH_IN_HAND'     => ['prefix' => '1120', 'pad' => 3, 'start' => 1],
    'SUNDRY_DEBTORS'   => ['prefix' => '1130', 'pad' => 3, 'start' => 1],
    'INVENTORY'        => ['prefix' => '1140', 'pad' => 3, 'start' => 1],
    'LOANS_ADVANCES'   => ['prefix' => '1150', 'pad' => 3, 'start' => 1],
    'GST_INPUT_GROUP'  => ['prefix' => '1160', 'pad' => 3, 'start' => 1],
    'TDS_RECEIVABLE_G' => ['prefix' => '1170', 'pad' => 3, 'start' => 1],
    'TCS_RECEIVABLE_G' => ['prefix' => '1180', 'pad' => 3, 'start' => 1],

    'FIXED_ASSETS'     => ['prefix' => '1200', 'pad' => 3, 'start' => 1],
    'INVESTMENTS'      => ['prefix' => '1300', 'pad' => 3, 'start' => 1],
    'WORK_IN_PROGRESS' => ['prefix' => '1400', 'pad' => 3, 'start' => 1],

    // Liabilities (2000000 range)
    'CURRENT_LIABILITIES' => ['prefix' => '2100', 'pad' => 3, 'start' => 1],
    'SUNDRY_CREDITORS'    => ['prefix' => '2110', 'pad' => 3, 'start' => 1],
    'DUTIES_TAXES'        => ['prefix' => '2120', 'pad' => 3, 'start' => 1],
    'GST_OUTPUT_GROUP'    => ['prefix' => '2121', 'pad' => 3, 'start' => 1],
    'TDS_PAYABLE_G'       => ['prefix' => '2130', 'pad' => 3, 'start' => 1],
    'LOANS_LIABILITY'     => ['prefix' => '2200', 'pad' => 3, 'start' => 1],

    // Capital / Equity (3000000 range)
    'EQUITY'           => ['prefix' => '3000', 'pad' => 3, 'start' => 1],
    'CAPITAL_ACCOUNT'  => ['prefix' => '3100', 'pad' => 3, 'start' => 1],
    'CURRENT_CAPITAL'  => ['prefix' => '3110', 'pad' => 3, 'start' => 1],
    'SHARE_CAPITAL'    => ['prefix' => '3120', 'pad' => 3, 'start' => 1],

    // Income (4000000 range)
    'SALES'            => ['prefix' => '4100', 'pad' => 3, 'start' => 1],
    'REVENUE'          => ['prefix' => '4200', 'pad' => 3, 'start' => 1],
    'OTHER_INCOME'     => ['prefix' => '4300', 'pad' => 3, 'start' => 1],

    // Expenses (5000000 range)
    'DIRECT_EXPENSES'   => ['prefix' => '5100', 'pad' => 3, 'start' => 1],
    'CONSUMABLE_EXP'    => ['prefix' => '5110', 'pad' => 3, 'start' => 1],
    'INDIRECT_EXPENSES' => ['prefix' => '5200', 'pad' => 3, 'start' => 1],
	],

	/*
	|--------------------------------------------------------------------------
	| Nature fallback prefixes (when group has no code or not mapped)
	|--------------------------------------------------------------------------
	*/
	'ledger_code_prefix_by_nature' => [
    // Last-resort fallbacks (should rarely be used once all groups have a series)
    'asset'     => ['prefix' => '1999', 'pad' => 3, 'start' => 1],
    'liability' => ['prefix' => '2099', 'pad' => 3, 'start' => 1],
    'equity'    => ['prefix' => '3099', 'pad' => 3, 'start' => 1],
    'income'    => ['prefix' => '4099', 'pad' => 3, 'start' => 1],
    'expense'   => ['prefix' => '5099', 'pad' => 3, 'start' => 1],
],




];