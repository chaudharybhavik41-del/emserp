<?php

namespace App\Notifications;

use App\Models\MachineMaintenancePlan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MaintenanceDueNotification extends Notification
{
    use Queueable;

    public MachineMaintenancePlan $plan;

    public function __construct(MachineMaintenancePlan $plan)
    {
        $this->plan = $plan;
    }

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable)
    {
        $machineName = $this->plan->machine->name ?? 'Machine';

        return (new MailMessage)
            ->subject('Upcoming Maintenance Due: ' . $this->plan->plan_name)
            ->greeting('Hello!')
            ->line("The maintenance plan '{$this->plan->plan_name}' for machine '{$machineName}' is due soon.")
            ->line('Next scheduled: ' . optional($this->plan->next_scheduled_date)->format('Y-m-d'))
            ->action('View Plan', route('maintenance.plans.show', $this->plan));
    }

    /**
     * Database payload (stored in notifications.data JSON).
     *
     * Normalized keys: type/title/message/url/level for UI.
     */
    public function toArray($notifiable)
    {
        $machineName = $this->plan->machine->name ?? 'Machine';
        $next = optional($this->plan->next_scheduled_date)->format('d-M-Y') ?: 'N/A';

        $title = 'Maintenance due soon: ' . $machineName;
        $message = "Plan: {$this->plan->plan_name}. Next scheduled: {$next}.";

        return [
            'type'    => 'machinery.maintenance',
            'title'   => $title,
            'message' => $message,
            'url'     => route('maintenance.plans.show', $this->plan),
            'level'   => 'warning',

            // extra details (safe)
            'plan_id'    => $this->plan->id,
            'plan_code'  => $this->plan->plan_code ?? null,
            'machine_id' => $this->plan->machine_id ?? null,
        ];
    }
}
