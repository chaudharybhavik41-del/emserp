<?php

namespace App\Http\Controllers;

use App\Models\CrmLead;
use App\Models\CrmLeadActivity;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CrmLeadActivityController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:crm.lead.update')->only(['store', 'complete', 'destroy']);
    }

    public function store(Request $request, CrmLead $lead): RedirectResponse
    {
        $data = $request->validateWithBag('activity', [
            'type'        => ['required', 'string', 'in:call,meeting,email,note,task'],
            'subject'     => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_at'      => ['nullable', 'date'],
        ]);

        $lead->activities()->create([
            'user_id'     => $request->user()->id,
            'type'        => $data['type'],
            'subject'     => $data['subject'],
            'description' => $data['description'] ?? null,
            'due_at'      => $this->resolveDueAt($data['type'], $data['due_at'] ?? null),
        ]);

        return redirect()
            ->route('crm.leads.show', $lead)
            ->with('success', 'Lead activity added successfully.');
    }

    public function complete(CrmLead $lead, CrmLeadActivity $activity): RedirectResponse
    {
        $this->ensureActivityBelongsToLead($lead, $activity);

        if ($activity->done_at) {
            return redirect()
                ->route('crm.leads.show', $lead)
                ->with('info', 'This activity is already completed.');
        }

        $activity->update([
            'done_at' => now(),
        ]);

        return redirect()
            ->route('crm.leads.show', $lead)
            ->with('success', 'Lead activity marked as completed.');
    }

    public function destroy(CrmLead $lead, CrmLeadActivity $activity): RedirectResponse
    {
        $this->ensureActivityBelongsToLead($lead, $activity);

        $activity->delete();

        return redirect()
            ->route('crm.leads.show', $lead)
            ->with('success', 'Lead activity deleted successfully.');
    }

    protected function ensureActivityBelongsToLead(CrmLead $lead, CrmLeadActivity $activity): void
    {
        if ((int) $activity->lead_id !== (int) $lead->id) {
            abort(404);
        }
    }

    protected function resolveDueAt(string $type, ?string $dueAt): Carbon
    {
        if ($dueAt) {
            return Carbon::parse($dueAt);
        }

        $hours = (int) config('crm.activity_slas.' . $type, 24);

        return now()->addHours(max(1, $hours));
    }
}
