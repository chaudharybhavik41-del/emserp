<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseRfqActivity extends Model
{
    protected $table = 'purchase_rfq_activities';

    protected $fillable = [
        'purchase_rfq_id',
        'user_id',
        'action',
        'message',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function rfq()
    {
        return $this->belongsTo(PurchaseRfq::class, 'purchase_rfq_id');
    }
}
