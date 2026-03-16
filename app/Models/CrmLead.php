<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class CrmLead extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'crm_leads';

    protected $fillable = [
        'code',
        'title',
        'party_id',
        'contact_name',
        'contact_email',
        'contact_phone',
        'lead_source_id',
        'lead_stage_id',
        'expected_value',
        'probability',
        'lead_date',
        'expected_close_date',
        'owner_id',
        'department_id',
        'status',
        'lost_reason',
        'notes',
    ];

    protected $casts = [
        'expected_value'      => 'decimal:2',
        'probability'         => 'integer',
        'lead_date'           => 'date',
        'expected_close_date' => 'date',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(CrmLeadSource::class, 'lead_source_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmLeadStage::class, 'lead_stage_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmLeadActivity::class, 'lead_id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(CrmQuotation::class, 'lead_id');
    }

    public function getWeightedValueAttribute(): float
    {
        return round(((float) ($this->expected_value ?? 0)) * ((int) ($this->probability ?? 0)) / 100, 2);
    }

    public function getLeadScoreAttribute(): int
    {
        return $this->calculateLeadScore();
    }

    public function getLeadTemperatureAttribute(): string
    {
        if ($this->status === 'won') {
            return 'won';
        }

        if ($this->status === 'lost') {
            return 'lost';
        }

        $score = $this->lead_score;

        if ($score >= 75) {
            return 'hot';
        }

        if ($score >= 45) {
            return 'warm';
        }

        return 'cold';
    }

    public function getLeadScoreBreakdownAttribute(): array
    {
        return $this->calculateLeadScoreBreakdown();
    }

    public function getNextFollowUpAtAttribute(): ?CarbonInterface
    {
        return $this->openActivitiesForFollowUp()
            ->sortBy(fn (CrmLeadActivity $activity) => optional($activity->sla_due_at)?->getTimestamp() ?? PHP_INT_MAX)
            ->first()?->sla_due_at;
    }

    public function getFollowUpStatusAttribute(): string
    {
        $nextFollowUpAt = $this->next_follow_up_at;

        if (! $nextFollowUpAt) {
            return 'none';
        }

        if ($nextFollowUpAt->isPast()) {
            return 'overdue';
        }

        if ($nextFollowUpAt->lessThanOrEqualTo(now()->addHours((int) config('crm.lead_scoring.due_soon_hours', 24)))) {
            return 'due_soon';
        }

        return 'scheduled';
    }

    public function calculateLeadScore(?CarbonInterface $asOf = null): int
    {
        return (int) array_sum($this->calculateLeadScoreBreakdown($asOf));
    }

    public function calculateLeadScoreBreakdown(?CarbonInterface $asOf = null): array
    {
        if ($this->status === 'won') {
            return [
                'probability' => 35,
                'value' => 20,
                'completeness' => 20,
                'engagement' => 15,
                'timeline' => 10,
            ];
        }

        if ($this->status === 'lost') {
            return [
                'probability' => 0,
                'value' => 0,
                'completeness' => 0,
                'engagement' => 0,
                'timeline' => 0,
            ];
        }

        $asOf ??= now();

        $probability = min(100, max(0, (int) ($this->probability ?? 0)));
        $expectedValue = max(0, (float) ($this->expected_value ?? 0));
        $valueThreshold = max(1, (float) config('crm.lead_scoring.large_value_threshold', 500000));
        $recentActivityCutoff = $asOf->copy()->subDays((int) config('crm.lead_scoring.recent_activity_days', 14));
        $closeWindowDays = max(1, (int) config('crm.lead_scoring.expected_close_window_days', 30));

        $activities = $this->scoringActivities();
        $quotations = $this->scoringQuotations();

        $recentActivity = $activities->first(function (CrmLeadActivity $activity) use ($recentActivityCutoff) {
            $activityAt = $activity->done_at ?? $activity->created_at ?? $activity->due_at;

            return $activityAt && $activityAt->greaterThanOrEqualTo($recentActivityCutoff);
        });

        $openActivities = $activities->filter(fn (CrmLeadActivity $activity) => $activity->done_at === null);
        $bestQuotationStatus = $quotations->pluck('status')->first(fn ($status) => in_array($status, ['accepted', 'sent', 'draft'], true));

        $timelineScore = 0;
        if ($this->expected_close_date) {
            $daysToClose = $asOf->copy()->startOfDay()->diffInDays($this->expected_close_date->copy()->startOfDay(), false);

            if ($daysToClose >= 0 && $daysToClose <= $closeWindowDays) {
                $timelineScore = 10;
            } elseif ($daysToClose > $closeWindowDays) {
                $timelineScore = 6;
            } else {
                $timelineScore = 2;
            }
        }

        return [
            'probability' => (int) round($probability * 0.35),
            'value' => $expectedValue > 0
                ? (int) round(min($expectedValue / $valueThreshold, 1) * 20)
                : 0,
            'completeness' => collect([
                filled($this->contact_name),
                filled($this->contact_phone),
                filled($this->contact_email),
                filled($this->party_id),
            ])->sum(fn (bool $filled) => $filled ? 5 : 0),
            'engagement' => ($recentActivity ? 8 : 0)
                + ($openActivities->isNotEmpty() ? 4 : 0)
                + match ($bestQuotationStatus) {
                    'accepted' => 3,
                    'sent' => 3,
                    'draft' => 2,
                    default => 0,
                },
            'timeline' => $timelineScore,
        ];
    }

    protected function scoringActivities(): Collection
    {
        if ($this->relationLoaded('activities')) {
            return $this->activities;
        }

        return $this->activities()
            ->get(['id', 'lead_id', 'due_at', 'done_at', 'created_at']);
    }

    protected function openActivitiesForFollowUp(): Collection
    {
        return $this->scoringActivities()
            ->filter(fn (CrmLeadActivity $activity) => $activity->done_at === null)
            ->values();
    }

    protected function scoringQuotations(): Collection
    {
        if ($this->relationLoaded('quotations')) {
            return $this->quotations->sortByDesc(function (CrmQuotation $quotation) {
                return match ($quotation->status) {
                    'accepted' => 4,
                    'sent' => 3,
                    'draft' => 2,
                    default => 1,
                };
            })->values();
        }

        return $this->quotations()
            ->orderByRaw("CASE status WHEN 'accepted' THEN 4 WHEN 'sent' THEN 3 WHEN 'draft' THEN 2 ELSE 1 END DESC")
            ->get(['id', 'lead_id', 'status', 'grand_total', 'created_at']);
    }
}
