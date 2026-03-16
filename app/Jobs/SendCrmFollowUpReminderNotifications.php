<?php

namespace App\Jobs;

use App\Models\CrmLeadActivity;
use App\Models\Department;
use App\Models\User;
use App\Notifications\CrmFollowUpEscalationNotification;
use App\Notifications\CrmFollowUpReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendCrmFollowUpReminderNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        try {
            $repeatAfterHours = max(1, (int) config('crm.follow_up_reminders.repeat_after_hours', 24));
            $escalateAfterHours = max(1, (int) config('crm.follow_up_reminders.escalate_after_hours', 48));
            $escalationRepeatAfterHours = max(1, (int) config('crm.follow_up_reminders.escalation_repeat_after_hours', 48));
            $threshold = now()->subHours($repeatAfterHours);
            $escalationThreshold = now()->subHours($escalateAfterHours);
            $escalationRepeatThreshold = now()->subHours($escalationRepeatAfterHours);

            $activities = CrmLeadActivity::query()
                ->with(['lead.owner.departments.head', 'lead.department.head', 'user'])
                ->whereNull('done_at')
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->get();

            $notificationsSent = 0;

            foreach ($activities as $activity) {
                $lead = $activity->lead;

                if (! $lead) {
                    continue;
                }

                $recipients = collect([$lead->owner, $activity->user])
                    ->filter(fn ($user) => $user instanceof User && $user->isActive())
                    ->unique('id')
                    ->values();

                if ($recipients->isEmpty()) {
                    continue;
                }

                if (! $activity->last_reminded_at || $activity->last_reminded_at->lessThanOrEqualTo($threshold)) {
                    foreach ($recipients as $recipient) {
                        try {
                            $recipient->notify(new CrmFollowUpReminderNotification($activity));
                            $notificationsSent++;
                        } catch (\Throwable $e) {
                            Log::error('Failed to send CRM follow-up reminder', [
                                'activity_id' => $activity->id,
                                'user_id' => $recipient->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    $activity->last_reminded_at = now();
                }

                if (
                    $activity->due_at->lessThanOrEqualTo($escalationThreshold)
                    && (! $activity->last_escalated_at || $activity->last_escalated_at->lessThanOrEqualTo($escalationRepeatThreshold))
                ) {
                    $escalationRecipients = $this->resolveEscalationRecipients($activity);

                    foreach ($escalationRecipients as $recipient) {
                        try {
                            $recipient->notify(new CrmFollowUpEscalationNotification($activity));
                            $notificationsSent++;
                        } catch (\Throwable $e) {
                            Log::error('Failed to send CRM follow-up escalation', [
                                'activity_id' => $activity->id,
                                'user_id' => $recipient->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    if ($escalationRecipients->isNotEmpty()) {
                        $activity->last_escalated_at = now();
                    }
                }

                $activity->save();
            }

            Log::info('CRM overdue follow-up reminders processed', [
                'activities' => $activities->count(),
                'notifications_sent' => $notificationsSent,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to process CRM follow-up reminders', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function resolveEscalationRecipients(CrmLeadActivity $activity)
    {
        $lead = $activity->lead;

        $leadDepartmentHead = $lead?->department?->head;
        $ownerPrimaryDepartmentHead = $lead?->owner?->departments
            ?->firstWhere('pivot.is_primary', true)
            ?->head;

        return collect([$leadDepartmentHead, $ownerPrimaryDepartmentHead])
            ->filter(fn ($user) => $user instanceof User && $user->isActive())
            ->unique('id')
            ->values();
    }
}
