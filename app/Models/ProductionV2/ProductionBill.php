<?php

namespace App\Models\ProductionV2;

use App\Models\Party;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionBill extends Model
{
    use HasFactory;

    protected $table = 'production_v2_bills';

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
        'finalized_by',
        'finalized_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'bill_date' => 'date',
        'period_from' => 'date',
        'period_to' => 'date',
        'gst_rate' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'cgst_total' => 'decimal:2',
        'sgst_total' => 'decimal:2',
        'igst_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
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
        return $this->hasMany(ProductionBillLine::class, 'production_v2_bill_id');
    }

    public function sourceLinks()
    {
        return $this->hasMany(ProductionBillSourceLink::class, 'production_v2_bill_id');
    }

    public function finalizedBy()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public static function nextBillNumber(Project $project): string
    {
        $year = now()->year;
        $prefix = 'PV2-BILL-' . ($project->code ?: $project->id) . '-' . $year . '-';

        $last = static::query()
            ->where('project_id', $project->id)
            ->where('bill_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('bill_number');

        $seq = 1;
        if ($last && preg_match('/(\d+)$/', (string) $last, $matches)) {
            $seq = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isFinalized(): bool
    {
        return $this->status === 'finalized';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
