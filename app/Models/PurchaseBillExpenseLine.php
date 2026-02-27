<?php

namespace App\Models;

use App\Models\Accounting\Account;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseBillExpenseLine extends Model
{
    use HasFactory;

    protected $table = 'purchase_bill_expense_lines';

    protected $fillable = [
        'purchase_bill_id',
        'account_id',
        'project_id',
        'is_reverse_charge',
        'description',
        'basic_amount',
        'tax_rate',
        'tax_amount',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'total_amount',
        'line_no',
    ];

    protected $casts = [
        'is_reverse_charge' => 'boolean',
        'project_id'        => 'integer',
        'basic_amount'      => 'float',
        'tax_rate'          => 'float',
        'tax_amount'        => 'float',
        'cgst_amount'       => 'float',
        'sgst_amount'       => 'float',
        'igst_amount'       => 'float',
        'total_amount'      => 'float',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(PurchaseBill::class, 'purchase_bill_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
