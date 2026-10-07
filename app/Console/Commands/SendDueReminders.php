<?php

namespace App\Console\Commands;

use App\Enums\ReminderStatus;
use App\Jobs\SendReminderEmail;
use App\Models\ReminderDate;
use Illuminate\Console\Command;
use Throwable;

class SendDueReminders extends Command
{
    protected $signature = 'reminders:send';

    protected $description = 'Queue one email job per person for every pending notification date whose time has come';

    public function handle(): int
    {
        $dates = 0;
        $jobs = 0;

        ReminderDate::due()
            ->with(['reminder.recipients'])
            ->chunkById(100, function ($chunk) use (&$dates, &$jobs) {
                foreach ($chunk as $date) {
                    $jobs += $this->dispatchFor($date);
                    $dates++;
                }
            });

        $this->info("Queued {$jobs} email(s) for {$dates} notification date(s).");

        return self::SUCCESS;
    }

    /**
     * Queue a separate job for the owner and for every extra recipient; a birthday
     * card goes only to the birthday person and next year's is scheduled. The date
     * is marked sent first so it isn't picked up again; if the owner's email (or the
     * birthday card) finally fails, it flips to failed.
     */
    private function dispatchFor(ReminderDate $date): int
    {
        $date->update(['status' => ReminderStatus::Sent, 'sent_at' => now()]);
        $reminder = $date->reminder;

        if ($reminder->isBirthday()) {
            $reminder->scheduleNextBirthday($date);
        }

        $jobs = $reminder->isBirthday()
            ? [new SendReminderEmail($date, $reminder->email)]
            : [
                new SendReminderEmail($date),
                ...$reminder->recipients->map(fn ($recipient) => new SendReminderEmail($date, $recipient->email)),
            ];

        foreach ($jobs as $job) {
            try {
                dispatch($job);
            } catch (Throwable $e) {
                // Only reached on the sync driver, where the job has already marked the date failed.
                report($e);
            }
        }

        return count($jobs);
    }
}
