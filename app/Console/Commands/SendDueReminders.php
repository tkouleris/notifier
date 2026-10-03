<?php

namespace App\Console\Commands;

use App\Enums\ReminderStatus;
use App\Models\Reminder;
use App\Notifications\ReminderDue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SendDueReminders extends Command
{
    protected $signature = 'reminders:send';

    protected $description = 'Send every pending notification whose time has come';

    public function handle(): int
    {
        $sent = 0;
        $failed = 0;

        Reminder::due()->with(['user', 'recipients'])->chunkById(100, function ($reminders) use (&$sent, &$failed) {
            foreach ($reminders as $reminder) {
                if ($this->deliver($reminder)) {
                    $reminder->update(['status' => ReminderStatus::Sent, 'sent_at' => now()]);
                    $sent++;
                } else {
                    $reminder->update(['status' => ReminderStatus::Failed]);
                    $failed++;
                }
            }
        });

        $this->info("Sent {$sent} notification(s), {$failed} failed.");

        return self::SUCCESS;
    }

    /**
     * Notify the owner and every extra recipient. One failed delivery doesn't stop
     * the others, but marks the whole reminder as failed.
     */
    private function deliver(Reminder $reminder): bool
    {
        $notification = new ReminderDue($reminder);
        $targets = [$reminder->user];

        foreach ($reminder->recipients as $recipient) {
            $targets[] = Notification::route($reminder->channel->driver(), $recipient->email);
        }

        $delivered = true;

        foreach ($targets as $target) {
            try {
                $target->notify($notification);
            } catch (Throwable $e) {
                report($e);
                $delivered = false;
            }
        }

        return $delivered;
    }
}
