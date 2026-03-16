<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCrmLeadRequest;
use App\Http\Requests\UpdateCrmLeadRequest;
use App\Models\CrmLead;
use App\Models\CrmLeadSource;
use App\Models\CrmLeadStage;
use App\Models\Party;
use App\Models\Project;
use App\Models\User;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class CrmLeadController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        $this->middleware('permission:crm.lead.view')->only(['index', 'show']);
        $this->middleware('permission:crm.lead.create')->only(['create', 'store']);
        $this->middleware('permission:crm.lead.update')->only(['edit', 'update', 'markWon', 'markLost']);
        $this->middleware('permission:crm.lead.delete')->only(['destroy']);
    }

    protected function generateLeadCode(): string
    {
        $year   = now()->format('Y');
        $prefix = "LEAD-{$year}";

        $last = CrmLead::where('code', 'like', "{$prefix}-%")
            ->orderBy('code', 'desc')
            ->first();

        $nextSeq = 1;
        if ($last) {
            $nextSeq = (int) substr($last->code, -4) + 1;
        }

        return sprintf('%s-%04d', $prefix, $nextSeq);
    }

    public function index(Request $request)
    {
        $query = CrmLead::with([
            'party',
            'source',
            'stage',
            'owner',
            'department',
            'activities:id,lead_id,due_at,done_at,created_at',
            'quotations:id,lead_id,status,grand_total,created_at',
        ]);

        if ($request->filled('q')) {
            $q = trim($request->get('q'));
            $query->where(function ($qb) use ($q) {
                $qb->where('code', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%")
                    ->orWhere('contact_name', 'like', "%{$q}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('lead_stage_id')) {
            $query->where('lead_stage_id', $request->integer('lead_stage_id'));
        }

        if ($request->filled('owner_id')) {
            $query->where('owner_id', $request->integer('owner_id'));
        }

        $priorityFilter = $request->string('priority')->toString();
        $followUpFilter = $request->string('follow_up_status')->toString();
        $sortBy = $request->string('sort_by')->toString();

        $usesComputedSorting = in_array($sortBy, ['score_desc', 'weighted_value_desc', 'next_follow_up_asc'], true);
        $usesComputedFilters = $priorityFilter !== '' || $followUpFilter !== '';

        if ($usesComputedSorting || $usesComputedFilters) {
            $collection = $query
                ->orderByDesc('id')
                ->get();

            if ($priorityFilter !== '') {
                $collection = $collection
                    ->filter(fn (CrmLead $lead) => $lead->lead_temperature === $priorityFilter)
                    ->values();
            }

            if ($followUpFilter !== '') {
                $collection = $collection
                    ->filter(fn (CrmLead $lead) => $lead->follow_up_status === $followUpFilter)
                    ->values();
            }

            $collection = (match ($sortBy) {
                'score_desc' => $collection->sortByDesc(
                    fn (CrmLead $lead) => ((int) $lead->lead_score * 1000000000) + ((int) round($lead->weighted_value))
                ),
                'weighted_value_desc' => $collection->sortByDesc(
                    fn (CrmLead $lead) => ((int) round($lead->weighted_value) * 1000) + (int) $lead->lead_score
                ),
                'next_follow_up_asc' => $collection->sortBy(fn (CrmLead $lead) => $lead->next_follow_up_at?->getTimestamp() ?? PHP_INT_MAX),
                default => $collection->sortByDesc('id'),
            })->values();

            $perPage = 25;
            $page = max(1, (int) $request->integer('page', 1));
            $leads = new LengthAwarePaginator(
                $collection->forPage($page, $perPage)->values(),
                $collection->count(),
                $perPage,
                $page,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );
        } else {
            $leads = $query
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString();
        }

        $stages = CrmLeadStage::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $owners = User::orderBy('name')->get();

        return view('crm.leads.index', compact('leads', 'stages', 'owners'));
    }

    public function create(Request $request)
    {
        $clients     = Party::where('is_client', true)->orderBy('name')->get();
        $sources     = CrmLeadSource::where('is_active', true)->orderBy('name')->get();
        $stages      = CrmLeadStage::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $owners      = User::orderBy('name')->get();
        $departments = Department::orderBy('name')->get();

        $prefill = [
            'title' => trim((string) $request->query('title', '')),
            'contact_name' => trim((string) $request->query('contact_name', '')),
            'contact_email' => trim((string) $request->query('contact_email', '')),
            'contact_phone' => trim((string) $request->query('contact_phone', '')),
            'notes' => trim((string) $request->query('notes', '')),
        ];

        return view('crm.leads.create', compact('clients', 'sources', 'stages', 'owners', 'departments', 'prefill'));
    }

    public function store(StoreCrmLeadRequest $request)
    {
        $data = $request->validated();

        $code = $data['code'] ?: $this->generateLeadCode();

        $lead = CrmLead::create([
            'code'                 => $code,
            'title'                => $data['title'],
            'party_id'             => $data['party_id'] ?? null,
            'contact_name'         => $data['contact_name'] ?? null,
            'contact_email'        => $data['contact_email'] ?? null,
            'contact_phone'        => $data['contact_phone'] ?? null,
            'lead_source_id'       => $data['lead_source_id'] ?? null,
            'lead_stage_id'        => $data['lead_stage_id'] ?? null,
            'expected_value'       => $data['expected_value'] ?? null,
            'probability'          => $data['probability'] ?? null,
            'lead_date'            => $data['lead_date'] ?? now()->toDateString(),
            'expected_close_date'  => $data['expected_close_date'] ?? null,
            'owner_id'             => $data['owner_id'] ?? $request->user()->id,
            'department_id'        => $data['department_id'] ?? null,
            'status'               => 'open',
            'notes'                => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('crm.leads.show', $lead)
            ->with('success', 'Lead created successfully.');
    }

    public function show(CrmLead $lead)
    {
        $lead->load([
            'party',
            'source',
            'stage',
            'owner',
            'department',
            'quotations',
            'attachments.uploader',
        ]);

        $activities = $lead->activities()
            ->with('user')
            ->orderByRaw('CASE WHEN done_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('due_at')
            ->orderByDesc('created_at')
            ->get();

        $pendingActivities = $activities
            ->filter(fn ($activity) => $activity->done_at === null)
            ->values();

        $completedActivities = $activities
            ->filter(fn ($activity) => $activity->done_at !== null)
            ->values();

        $overdueActivitiesCount = $pendingActivities
            ->filter(fn ($activity) => $activity->due_at && $activity->due_at->isPast())
            ->count();

        $wonStages = CrmLeadStage::where('is_active', true)
            ->where('is_won', true)
            ->orderBy('sort_order')
            ->get();

        $lostStages = CrmLeadStage::where('is_active', true)
            ->where('is_lost', true)
            ->orderBy('sort_order')
            ->get();

        return view('crm.leads.show', compact(
            'lead',
            'wonStages',
            'lostStages',
            'activities',
            'pendingActivities',
            'completedActivities',
            'overdueActivitiesCount'
        ));
    }

    public function edit(CrmLead $lead)
    {
        if (in_array($lead->status, ['won', 'lost'], true)) {
            return redirect()
                ->route('crm.leads.show', $lead)
                ->with('error', 'Closed leads (WON/LOST) cannot be edited.');
        }

        $clients     = Party::where('is_client', true)->orderBy('name')->get();
        $sources     = CrmLeadSource::where('is_active', true)->orderBy('name')->get();
        $stages      = CrmLeadStage::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $owners      = User::orderBy('name')->get();
        $departments = Department::orderBy('name')->get();

        return view('crm.leads.edit', compact('lead', 'clients', 'sources', 'stages', 'owners', 'departments'));
    }

    public function update(UpdateCrmLeadRequest $request, CrmLead $lead)
    {
        if (in_array($lead->status, ['won', 'lost'], true)) {
            return redirect()
                ->route('crm.leads.show', $lead)
                ->with('error', 'Closed leads (WON/LOST) cannot be edited.');
        }

        $data = $request->validated();

        $lead->update([
            'title'                => $data['title'],
            'party_id'             => $data['party_id'] ?? null,
            'contact_name'         => $data['contact_name'] ?? null,
            'contact_email'        => $data['contact_email'] ?? null,
            'contact_phone'        => $data['contact_phone'] ?? null,
            'lead_source_id'       => $data['lead_source_id'] ?? null,
            'lead_stage_id'        => $data['lead_stage_id'] ?? null,
            'expected_value'       => $data['expected_value'] ?? null,
            'probability'          => $data['probability'] ?? null,
            'lead_date'            => $data['lead_date'] ?? $lead->lead_date,
            'expected_close_date'  => $data['expected_close_date'] ?? null,
            'owner_id'             => $data['owner_id'] ?? $lead->owner_id,
            'department_id'        => $data['department_id'] ?? null,
            'notes'                => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('crm.leads.show', $lead)
            ->with('success', 'Lead updated successfully.');
    }

    public function destroy(CrmLead $lead)
    {
        if ($lead->quotations()->exists() || Project::query()->where('lead_id', $lead->id)->exists()) {
            return redirect()
                ->route('crm.leads.show', $lead)
                ->with('error', 'This lead cannot be deleted because quotations or downstream project records already exist.');
        }

        $lead->delete();

        return redirect()
            ->route('crm.leads.index')
            ->with('success', 'Lead deleted successfully.');
    }

    // 🔹 Mark lead as WON – only if it is still OPEN
    public function markWon(Request $request, CrmLead $lead)
    {
        if ($lead->status !== 'open') {
            return redirect()
                ->route('crm.leads.show', $lead)
                ->with('error', 'This lead is already closed (status: ' . strtoupper($lead->status) . ').');
        }

        $stageId = null;

        // If a specific WON stage is chosen & is flagged as WON, use it
        if ($request->filled('lead_stage_id')) {
            $stage = CrmLeadStage::where('is_won', true)
                ->where('id', $request->integer('lead_stage_id'))
                ->first();

            if ($stage) {
                $stageId = $stage->id;
            }
        }

        // Otherwise, pick the first configured WON stage (if any)
        if (!$stageId) {
            $stage = CrmLeadStage::where('is_active', true)
                ->where('is_won', true)
                ->orderBy('sort_order')
                ->first();

            if ($stage) {
                $stageId = $stage->id;
            }
        }

        $lead->update([
            'lead_stage_id' => $stageId,
            'status'        => 'won',
            'probability'   => 100,
        ]);

        return redirect()
            ->route('crm.leads.show', $lead)
            ->with('success', 'Lead marked as WON.');
    }

    // 🔹 Mark lead as LOST – only if it is still OPEN
    public function markLost(Request $request, CrmLead $lead)
    {
        if ($lead->status !== 'open') {
            return redirect()
                ->route('crm.leads.show', $lead)
                ->with('error', 'This lead is already closed (status: ' . strtoupper($lead->status) . ').');
        }

        $request->validate([
            'lost_reason'   => ['required', 'string', 'max:1000'],
            'lead_stage_id' => ['nullable', 'integer', 'exists:crm_lead_stages,id'],
        ]);

        $stageId = null;

        if ($request->filled('lead_stage_id')) {
            $stage = CrmLeadStage::where('is_lost', true)
                ->where('id', $request->integer('lead_stage_id'))
                ->first();

            if ($stage) {
                $stageId = $stage->id;
            }
        }

        if (!$stageId) {
            $stage = CrmLeadStage::where('is_active', true)
                ->where('is_lost', true)
                ->orderBy('sort_order')
                ->first();

            if ($stage) {
                $stageId = $stage->id;
            }
        }

        $lead->update([
            'lead_stage_id' => $stageId,
            'status'        => 'lost',
            'lost_reason'   => $request->lost_reason,
            'probability'   => 0,
        ]);

        // When a lead is LOST, auto-reject any still-open quotations
        $lead->quotations()
            ->whereIn('status', ['draft', 'sent'])
            ->update([
                'status'      => 'rejected',
                'rejected_at' => now(),
            ]);

        return redirect()
            ->route('crm.leads.show', $lead)
            ->with('success', 'Lead marked as LOST and related quotations closed.');
    }
}
