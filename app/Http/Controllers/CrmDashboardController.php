<?php

namespace App\Http\Controllers;

use App\Models\CrmLead;
use App\Models\CrmLeadActivity;
use App\Models\CrmLeadStage;
use App\Models\CrmQuotation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CrmDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        abort_unless(
            $request->user()?->can('crm.lead.view') || $request->user()?->can('crm.quotation.view'),
            403
        );

        $ownerFilter = $request->integer('owner_id') ?: null;
        $stageFilter = $request->integer('lead_stage_id') ?: null;

        $leadQuery = CrmLead::query()
            ->with([
                'owner:id,name',
                'stage:id,name',
                'activities:id,lead_id,due_at,done_at,created_at',
                'quotations:id,lead_id,status,grand_total,created_at',
            ])
            ->when($ownerFilter, fn ($q) => $q->where('owner_id', $ownerFilter))
            ->when($stageFilter, fn ($q) => $q->where('lead_stage_id', $stageFilter));

        $quotationQuery = CrmQuotation::query()
            ->with(['lead:id,owner_id'])
            ->when($ownerFilter, function ($q) use ($ownerFilter) {
                $q->whereHas('lead', fn ($lead) => $lead->where('owner_id', $ownerFilter));
            })
            ->when($stageFilter, function ($q) use ($stageFilter) {
                $q->whereHas('lead', fn ($lead) => $lead->where('lead_stage_id', $stageFilter));
            });

        $activityQuery = CrmLeadActivity::query()
            ->with(['lead:id,code,title,owner_id', 'user:id,name'])
            ->when($ownerFilter, function ($q) use ($ownerFilter) {
                $q->whereHas('lead', fn ($lead) => $lead->where('owner_id', $ownerFilter));
            })
            ->when($stageFilter, function ($q) use ($stageFilter) {
                $q->whereHas('lead', fn ($lead) => $lead->where('lead_stage_id', $stageFilter));
            });

        $leads = (clone $leadQuery)->get([
            'id',
            'code',
            'title',
            'lead_stage_id',
            'owner_id',
            'status',
            'expected_value',
            'lead_date',
            'created_at',
        ]);

        $openLeads = $leads->where('status', 'open')->values();
        $quotations = (clone $quotationQuery)->get([
            'id',
            'code',
            'lead_id',
            'status',
            'grand_total',
            'created_at',
            'accepted_at',
        ]);

        $activities = (clone $activityQuery)
            ->orderBy('due_at')
            ->orderByDesc('created_at')
            ->get([
                'id',
                'lead_id',
                'user_id',
                'type',
                'subject',
                'description',
                'due_at',
                'done_at',
                'created_at',
            ]);

        $owners = User::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $stages = CrmLeadStage::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $overdueActivities = $activities
            ->filter(fn (CrmLeadActivity $activity) => $activity->done_at === null && $activity->due_at && $activity->due_at->isPast())
            ->take(10)
            ->values();

        $upcomingActivities = $activities
            ->filter(function (CrmLeadActivity $activity) {
                return $activity->done_at === null
                    && $activity->due_at
                    && $activity->due_at->isFuture()
                    && $activity->due_at->lte(now()->addDays(7));
            })
            ->take(10)
            ->values();

        $pipeline = $this->buildPipeline($openLeads);
        $ageing = $this->buildAgeing($openLeads);
        $ownerWorkload = $this->buildOwnerWorkload($leads, $quotations, $activities);
        $quotationSummary = $this->buildQuotationSummary($quotations);

        $kpis = [
            'open_leads' => $openLeads->count(),
            'won_leads' => $leads->where('status', 'won')->count(),
            'lost_leads' => $leads->where('status', 'lost')->count(),
            'overdue_followups' => $overdueActivities->count(),
            'due_this_week' => $upcomingActivities->count(),
            'accepted_quotations' => $quotations->where('status', 'accepted')->count(),
            'avg_open_lead_score' => round($openLeads->avg(fn (CrmLead $lead) => $lead->lead_score) ?? 0, 1),
            'hot_leads' => $openLeads->filter(fn (CrmLead $lead) => $lead->lead_temperature === 'hot')->count(),
        ];

        return view('crm.dashboard', compact(
            'owners',
            'stages',
            'ownerFilter',
            'stageFilter',
            'kpis',
            'pipeline',
            'ageing',
            'ownerWorkload',
            'quotationSummary',
            'overdueActivities',
            'upcomingActivities'
        ));
    }

    protected function buildPipeline(Collection $openLeads): Collection
    {
        return $openLeads
            ->groupBy(fn (CrmLead $lead) => $lead->stage?->name ?? 'Unstaged')
            ->map(function (Collection $group, string $stageName) {
                return [
                    'stage_name' => $stageName,
                    'lead_count' => $group->count(),
                    'expected_value' => (float) $group->sum(fn (CrmLead $lead) => (float) ($lead->expected_value ?? 0)),
                    'avg_age_days' => round($group->avg(fn (CrmLead $lead) => $this->leadAgeInDays($lead)), 1),
                ];
            })
            ->sortByDesc('lead_count')
            ->values();
    }

    protected function buildAgeing(Collection $openLeads): Collection
    {
        $buckets = collect([
            ['label' => '0-7 Days', 'min' => 0, 'max' => 7],
            ['label' => '8-15 Days', 'min' => 8, 'max' => 15],
            ['label' => '16-30 Days', 'min' => 16, 'max' => 30],
            ['label' => '31-60 Days', 'min' => 31, 'max' => 60],
            ['label' => '60+ Days', 'min' => 61, 'max' => null],
        ]);

        return $buckets->map(function (array $bucket) use ($openLeads) {
            $leads = $openLeads->filter(function (CrmLead $lead) use ($bucket) {
                $age = $this->leadAgeInDays($lead);

                if ($bucket['max'] === null) {
                    return $age >= $bucket['min'];
                }

                return $age >= $bucket['min'] && $age <= $bucket['max'];
            });

            return [
                'label' => $bucket['label'],
                'lead_count' => $leads->count(),
                'expected_value' => (float) $leads->sum(fn (CrmLead $lead) => (float) ($lead->expected_value ?? 0)),
            ];
        });
    }

    protected function buildOwnerWorkload(Collection $leads, Collection $quotations, Collection $activities): Collection
    {
        return $leads
            ->groupBy('owner_id')
            ->map(function (Collection $ownerLeads, $ownerId) use ($quotations, $activities) {
                $leadIds = $ownerLeads->pluck('id')->all();
                $ownerActivities = $activities->whereIn('lead_id', $leadIds);
                $ownerQuotes = $quotations->filter(fn (CrmQuotation $quotation) => in_array($quotation->lead_id, $leadIds, true));

                $wonCount = $ownerLeads->where('status', 'won')->count();
                $lostCount = $ownerLeads->where('status', 'lost')->count();
                $closedCount = $wonCount + $lostCount;

                return [
                    'owner_name' => $ownerLeads->first()?->owner?->name ?? 'Unassigned',
                    'open_leads' => $ownerLeads->where('status', 'open')->count(),
                    'overdue_followups' => $ownerActivities->filter(
                        fn (CrmLeadActivity $activity) => $activity->done_at === null && $activity->due_at && $activity->due_at->isPast()
                    )->count(),
                    'accepted_quotations' => $ownerQuotes->where('status', 'accepted')->count(),
                    'avg_lead_score' => round($ownerLeads->avg(fn (CrmLead $lead) => $lead->lead_score) ?? 0, 1),
                    'conversion_rate' => $closedCount > 0 ? round(($wonCount / $closedCount) * 100, 1) : null,
                ];
            })
            ->sortByDesc('open_leads')
            ->values();
    }

    protected function buildQuotationSummary(Collection $quotations): array
    {
        $acceptedCount = $quotations->where('status', 'accepted')->count();
        $closedCount = $quotations->whereIn('status', ['accepted', 'rejected', 'superseded'])->count();

        return [
            'draft' => $quotations->where('status', 'draft')->count(),
            'sent' => $quotations->where('status', 'sent')->count(),
            'accepted' => $acceptedCount,
            'rejected' => $quotations->where('status', 'rejected')->count(),
            'superseded' => $quotations->where('status', 'superseded')->count(),
            'accepted_value' => (float) $quotations
                ->where('status', 'accepted')
                ->sum(fn (CrmQuotation $quotation) => (float) ($quotation->grand_total ?? 0)),
            'conversion_rate' => $closedCount > 0 ? round(($acceptedCount / $closedCount) * 100, 1) : null,
        ];
    }

    protected function leadAgeInDays(CrmLead $lead): int
    {
        $start = $lead->lead_date ?? $lead->created_at;

        if (! $start) {
            return 0;
        }

        return max(0, $start->startOfDay()->diffInDays(now()->startOfDay()));
    }
}
