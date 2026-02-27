<?php

namespace App\Notifications;

use App\Models\MachineCalibrationRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CalibrationDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MachineCalibrationRecord $record,
        public bool $overdue = false
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $machine = $this->record->machine;
        $dueDate = $this->record->due_date?->format('d-M-Y');

        $subject = $this->overdue
            ? 'Calibration OVERDUE: ' . ($machine->name ?? 'Machine')
            : 'Calibration due soon: ' . ($machine->name ?? 'Machine');

        $url = route('machine-calibrations.index', [
            'machine_id' => $machine?->id,
        ]);

        return (new MailMessage)
            ->subject($subject)
            ->line('Machine: ' . ($machine->code ?? 'N/A') . ' - ' . ($machine->name ?? ''))
            ->line('Calibration number: ' . $this->record->calibration_number)
            ->line('Due date: ' . ($dueDate ?: 'N/A'))
            ->action('View calibration records', $url);
    }

    /**
     * Database payload (stored in notifications.data JSON).
     *
     * IMPORTANT:
     * - The Notifications UI expects type/title/message/url to render nicely.
     * - We keep existing keys (record_id, machine_id, etc.) for backward compatibility.
     */
    public function toArray(object $notifiable): array
    {
        $machine = $this->record->machine;

        $dueDateHuman = $this->record->due_date?->format('d-M-Y') ?? 'N/A';
        $dueDateIso = optional($this->record->due_date)->toDateString();

        $machineLabel = trim(($machine?->code ?? 'N/A') . ' ' . ($machine?->name ?? ''));
        $title = $this->overdue
            ? 'Calibration OVERDUE: ' . ($machine?->code ?? 'Machine')
            : 'Calibration due soon: ' . ($machine?->code ?? 'Machine');

        $message = $this->overdue
            ? "Calibration {$this->record->calibration_number} is overdue (due {$dueDateHuman})."
            : "Calibration {$this->record->calibration_number} is due on {$dueDateHuman}.";

        $url = route('machine-calibrations.index', [
            'machine_id' => $machine?->id,
        ]);

        return [
            // UI-friendly keys
            'type'    => 'machinery.calibration',
            'title'   => $title,
            'message' => $message,
            'url'     => $url,
            'level'   => $this->overdue ? 'danger' : 'warning',

            // Backward-compatible / detail keys
            'record_id' => $this->record->id,
            'calibration_number' => $this->record->calibration_number,
            'machine_id' => $machine?->id,
            'machine_code' => $machine?->code,
            'machine_name' => $machine?->name,
            'machine_label' => $machineLabel,
            'overdue' => $this->overdue,
            'due_date' => $dueDateIso,
        ];
    }
}
