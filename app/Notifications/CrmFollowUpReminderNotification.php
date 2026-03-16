<?php

namespace App\Notifications;

use App\Models\CrmLeadActivity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CrmFollowUpReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public CrmLeadActivity $activity)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lead = $this->activity->lead;
        $dueAt = $this->activity->due_at?->format('d-M-Y H:i') ?? 'N/A';

        return (new MailMessage)
            ->subject('CRM follow-up overdue: ' . ($lead?->code ?? 'Lead'))
            ->line('Lead: ' . ($lead?->code ?? 'N/A') . ' - ' . ($lead?->title ?? 'CRM Lead'))
            ->line('Activity: ' . ($this->activity->subject ?: 'Untitled activity'))
            ->line('Due at: ' . $dueAt)
            ->action('Open lead', route('crm.leads.show', $lead));
    }

    public function toArray(object $notifiable): array
    {
        $lead = $this->activity->lead;
        $dueAtHuman = $this->activity->due_at?->format('d-M-Y H:i') ?? 'N/A';

        return [
            'type' => 'crm.follow_up.reminder',
            'title' => 'Overdue CRM follow-up',
            'message' => ($this->activity->subject ?: 'Untitled activity') . ' is overdue for lead ' . ($lead?->code ?? 'N/A') . " (due {$dueAtHuman}).",
            'url' => route('crm.leads.show', $lead),
            'level' => 'warning',
            'lead_id' => $lead?->id,
            'lead_code' => $lead?->code,
            'activity_id' => $this->activity->id,
            'activity_type' => $this->activity->type,
            'activity_subject' => $this->activity->subject,
            'due_at' => optional($this->activity->due_at)->toDateTimeString(),
        ];
    }
}
