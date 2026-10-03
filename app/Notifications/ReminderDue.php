<?php

namespace App\Notifications;

use App\Models\Reminder;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReminderDue extends Notification
{
    public function __construct(public Reminder $reminder)
    {
    }

    /**
     * Deliver through the channel the user picked for this reminder.
     */
    public function via(object $notifiable): array
    {
        return [$this->reminder->channel->driver()];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Reminder: '.$this->reminder->title)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->reminder->title);

        if ($this->reminder->message) {
            $mail->line($this->reminder->message);
        }

        return $mail->action('View your notifications', route('reminders.index'));
    }
}
