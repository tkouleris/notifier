<?php

namespace App\Console\Commands;

use App\Enums\ReminderStatus;
use App\Models\Reminder;
use App\Notifications\ReminderDue;
use Illuminate\Console\Command;
use Throwable;

class SendDueReminders extends Command
{
    protected $signature = 'reminders:send';

    protected $description = 'Send every pending notification whose time has come';

    public function handle(): int
    {
        $sent = 0;
        $failed = 0;

        Reminder::due()->with('user')->chunkById(100, function ($reminders) use (&$sent, &$failed) {
            foreach ($reminders as $reminder) {
                try {
                    $reminder->user->notify(new ReminderDue($reminder));
                    $reminder->update(['status' => ReminderStatus::Sent, 'sent_at' => now()]);
                    $sent++;
                } catch (Throwable $e) {
                    report($e);
                    $reminder->update(['status' => ReminderStatus::Failed]);
                    $failed++;
                }
            }
        });

        $this->info("Sent {$sent} notification(s), {$failed} failed.");

        return self::SUCCESS;
    }
}
