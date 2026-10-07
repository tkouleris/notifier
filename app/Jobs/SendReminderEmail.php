<?php

namespace App\Jobs;

use App\Enums\ReminderStatus;
use App\Models\ReminderDate;
use App\Notifications\ReminderDue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Sends one date of a reminder to one person: the owner, or an extra recipient by email.
 */
class SendReminderEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    /**
     * The reminder (and its dates) may be deleted before the job runs.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * @param  string|null  $email  An extra recipient's address, or null for the owner.
     */
    public function __construct(public ReminderDate $date, public ?string $email = null)
    {
    }

    public function handle(): void
    {
        $reminder = $this->date->reminder;

        $notifiable = $this->email === null
            ? $reminder->user
            : Notification::route($reminder->channel->driver(), $this->email);

        $notifiable->notify(new ReminderDue($this->date));
    }

    /**
     * Once every retry is used up, the date shows as failed — but only for the email
     * its status describes: the owner's, or a birthday card. Extra recipients don't count.
     */
    public function failed(Throwable $e): void
    {
        if ($this->email === null || $this->date->reminder->isBirthday()) {
            $this->date->update(['status' => ReminderStatus::Failed]);
        }
    }
}
