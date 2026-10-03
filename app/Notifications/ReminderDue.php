<?php

namespace App\Notifications;

use App\Models\Reminder;
use App\Models\ReminderDate;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReminderDue extends Notification
{
    public Reminder $reminder;

    public function __construct(public ReminderDate $date)
    {
        $this->reminder = $date->reminder;
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

        $mail = (new MailMessage)->subject(
            ($this->date->is_final ? 'Reminder: ' : 'Upcoming: ').$this->reminder->title
        );

        if ($isOwner) {
            $mail->greeting('Hello '.$notifiable->name.',');
        } else {
            $mail->greeting('Hello,')
                ->line($this->reminder->user->name.' asked us to remind you about this.');
        }

        $mail->line($this->reminder->title);

        if (! $this->date->is_final) {
            $mail->line('This is an early reminder. The final date is '.$this->finalDateText().'.');
        }

        if ($this->reminder->message) {
            $mail->line($this->reminder->message);
        }

        // Only the owner has an account to view the reminder in.
        return $isOwner
            ? $mail->action('View your notifications', route('reminders.index'))
            : $mail;
    }

    private function finalDateText(): string
    {
        // Shown in the owner's timezone, the one the dates were picked in.
        $owner = $this->reminder->user;

        return $owner->toLocal($this->reminder->finalDate->notify_at)->format('l, F j, Y \a\t H:i')
            .' ('.$owner->timezone.')';
    }
}
