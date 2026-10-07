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
        if ($this->reminder->isBirthday()) {
            return $this->birthdayCard();
        }

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

    /**
     * The yearly card, addressed to the birthday person on behalf of the owner.
     */
    private function birthdayCard(): MailMessage
    {
        $owner = $this->reminder->user;
        $age = $this->reminder->ageOn($this->date->notify_at);
        $wish = ($age ? 'Happy '.$this->ordinal($age).' birthday' : 'Happy birthday').', '.$this->reminder->title.'!';

        $mail = (new MailMessage)
            ->subject($wish.' 🎂')
            ->greeting($wish.' 🎂')
            ->line($owner->name.' is thinking of you today and wishes you a wonderful year ahead.');

        if ($this->reminder->message) {
            $mail->line($this->reminder->message);
        }

        return $mail->salutation('With love, '.$owner->name);
    }

    private function ordinal(int $number): string
    {
        $suffix = in_array($number % 100, [11, 12, 13], true) ? 'th' : (['th', 'st', 'nd', 'rd'][$number % 10] ?? 'th');

        return $number.$suffix;
    }

    private function finalDateText(): string
    {
        // Shown in the owner's timezone, the one the dates were picked in.
        $owner = $this->reminder->user;

        return $owner->toLocal($this->reminder->finalDate->notify_at)->format('l, F j, Y \a\t H:i')
            .' ('.$owner->timezone.')';
    }
}
