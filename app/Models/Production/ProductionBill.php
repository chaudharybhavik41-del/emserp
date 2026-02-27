<?php

namespace App\Models\Production;

use App\Models\Party;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionBill extends Model
{
    use HasFactory;

    protected $table = 'production_bills';

    protected $fillable = [
        'project_id',
        'contractor_party_id',
        'bill_number',
        'bill_date',
        'period_from',
        'period_to',
        'status',

        'gst_type',
        'gst_rate',

        'subtotal',
        'tax_total',
        'cgst_total',
        'sgst_total',
        'igst_total',
        'grand_total',

        'remarks',
        'created_by',
        'updated_by',
        'finalized_by',
        'finalized_at',
    ];

    protected $casts = [
        'bill_date' => 'date',
        'period_from' => 'date',
        'period_to' => 'date',
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'cgst_total' => 'decimal:2',
        'sgst_total' => 'decimal:2',
        'igst_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'gst_rate' => 'decimal:2',
        'finalized_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function contractor()
    {
        return $this->belongsTo(Party::class, 'contractor_party_id');
    }

    public function lines()
    {
        return $this->hasMany(ProductionBillLine::class, 'production_bill_id');
    }

    public function finalizedBy()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function isDraft(): bool { return $this->status === 'draft'; }
    public function isFinalized(): bool { return $this->status === 'finalized'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }

    public static function nextBillNumber(Project $project): string
    {
        $year = now()->year;
        $prefix = "PB-{$project->code}-{$year}-";

        $last = static::query()
            ->where('bill_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('bill_number');

        $seq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = ((int)$m[1]) + 1;
        }

        return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }
}
