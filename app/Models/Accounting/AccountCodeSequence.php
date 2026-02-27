<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class AccountCodeSequence extends Model
{
    protected $table = 'account_code_sequences';

    protected $fillable = [
        'company_id',
        'series_key',
        'prefix',
        'next_number',
        'pad_width',
    ];
}
