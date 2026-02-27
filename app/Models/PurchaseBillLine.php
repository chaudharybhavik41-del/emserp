<?php

namespace App\Models;

use App\Models\Accounting\Account;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseBillLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_bill_id',
        'material_receipt_id',
        'material_receipt_line_id',
        'item_id',
        'uom_id',
        'qty',
        'rate',
        'discount_percent',
        'discount_amount',
        'basic_amount',
        'tax_rate',
        'tax_amount',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'total_amount',
        'account_id',
        'line_no',
    ];

    protected $casts = [
        'qty'          => 'float',
        'rate'         => 'float',
        'discount_percent' => 'float',
        'discount_amount'  => 'float',
        'basic_amount'     => 'float',
        'tax_rate'         => 'float',
        'tax_amount'       => 'float',
        'cgst_amount'      => 'float',
        'sgst_amount'      => 'float',
        'igst_amount'      => 'float',
        'total_amount'     => 'float',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(PurchaseBill::class, 'purchase_bill_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }

    public function materialReceipt(): BelongsTo
    {
        return $this->belongsTo(MaterialReceipt::class, 'material_receipt_id');
    }

    public function materialReceiptLine(): BelongsTo
    {
        return $this->belongsTo(MaterialReceiptLine::class, 'material_receipt_line_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
