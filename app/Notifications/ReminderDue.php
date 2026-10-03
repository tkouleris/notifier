<?php

namespace App\Notifications;

use App\Models\Reminder;
use App\Models\User;
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
        $isOwner = $notifiable instanceof User && $notifiable->is($this->reminder->user);

        $mail = (new MailMessage)->subject('Reminder: '.$this->reminder->title);

        if ($isOwner) {
            $mail->greeting('Hello '.$notifiable->name.',');
        } else {
            $mail->greeting('Hello,')
                ->line($this->reminder->user->name.' asked us to remind you about this.');
        }

        $mail->line($this->reminder->title);

        if ($this->reminder->message) {
            $mail->line($this->reminder->message);
        }

        // Only the owner has an account to view the reminder in.
        return $isOwner
            ? $mail->action('View your notifications', route('reminders.index'))
            : $mail;
    }
}
