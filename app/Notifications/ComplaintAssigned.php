<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells an officer a complaint was assigned to them. Contains no complainant data.
 */
class ComplaintAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $reference,
        public string $subject,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Complaint {$this->reference} assigned to you")
            ->line("Complaint {$this->reference} has been assigned to you.")
            ->action('Open complaint', route('admin.complaints.show', $this->reference));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'complaint_assigned',
            'reference' => $this->reference,
            'subject' => $this->subject,
        ];
    }
}
