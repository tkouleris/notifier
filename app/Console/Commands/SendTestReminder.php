<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Notifications\ReminderDue;
use Illuminate\Console\Command;
use Throwable;

class SendTestReminder extends Command
{
    protected $signature = 'reminders:test {reminder : The ID of the notification to send}';

    protected $description = 'Send a notification\'s final email (or birthday card) to its owner right away, as a test';

    /**
     * The email is sent immediately, bypassing the queue, and only to the owner: extra
     * recipients and the birthday person get nothing, and no date's status changes.
     */
    public function handle(): int
    {
        $reminder = Reminder::with(['user', 'dates'])->find($this->argument('reminder'));

        if (! $reminder) {
            $this->error("Notification {$this->argument('reminder')} not found.");

            return self::FAILURE;
        }

        // A birthday keeps last year's sent card next to the upcoming one; use the upcoming one.
        $date = $reminder->dates->where('is_final', true)->last();

        if (! $date) {
            $this->error("Notification {$reminder->id} has no final date to send.");

            return self::FAILURE;
        }

        try {
            $reminder->user->notifyNow(new ReminderDue($date));
        } catch (Throwable $e) {
            $this->error('Sending failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Sent a test of \"{$reminder->title}\" to {$reminder->user->email}.");

        return self::SUCCESS;
    }
}
