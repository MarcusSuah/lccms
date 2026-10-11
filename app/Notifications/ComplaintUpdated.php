<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sends the complainant a public response. The tracking token is never
 * included because it is not stored; the complainant keeps their own link.
 */
class ComplaintUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $reference,
        public string $status,
        public string $response,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Update on your complaint {$this->reference}")
            ->line("Status: {$this->status}")
            ->line($this->response)
            ->line('Use the tracking link you received when you submitted the complaint to see its full history.');
    }
}
