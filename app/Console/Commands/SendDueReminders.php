<?php

namespace App\Console\Commands;

use App\Enums\ReminderStatus;
use App\Models\ReminderDate;
use App\Notifications\ReminderDue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SendDueReminders extends Command
{
    protected $signature = 'reminders:send';

    protected $description = 'Send every pending notification date whose time has come';

    public function handle(): int
    {
        $sent = 0;
        $failed = 0;

        ReminderDate::due()
            ->with(['reminder.user', 'reminder.recipients', 'reminder.finalDate'])
            ->chunkById(100, function ($dates) use (&$sent, &$failed) {
                foreach ($dates as $date) {
                    if ($this->deliver($date)) {
                        $date->update(['status' => ReminderStatus::Sent, 'sent_at' => now()]);
                        $sent++;
                    } else {
                        $date->update(['status' => ReminderStatus::Failed]);
                        $failed++;
                    }
                }
            });

        $this->info("Sent {$sent} notification(s), {$failed} failed.");

        return self::SUCCESS;
    }

    /**
     * Notify the owner and every extra recipient. One failed delivery doesn't stop
     * the others, but marks the date as failed.
     */
    private function deliver(ReminderDate $date): bool
    {
        $reminder = $date->reminder;
        $notification = new ReminderDue($date);
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
