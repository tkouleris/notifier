<?php

namespace Tests\Feature;

use App\Enums\ReminderStatus;
use App\Models\Reminder;
use App\Models\User;
use App\Notifications\ReminderDue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class SendTestReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_the_final_email_to_the_owner_only_and_changes_nothing(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $reminder = Reminder::factory()->for($owner)->create(['title' => 'Pay rent']);
        $reminder->syncDates(now()->addWeek(), [now()->addDay()]);
        $reminder->syncRecipients(['friend@example.com']);

        $this->artisan('reminders:test', ['reminder' => $reminder->id])
            ->expectsOutput('Sent a test of "Pay rent" to owner@example.com.')
            ->assertSuccessful();

        Notification::assertSentTo($owner, ReminderDue::class, fn (ReminderDue $notification) => $notification->date->is_final);
        Notification::assertSentTimes(ReminderDue::class, 1);
        $this->assertSame([ReminderStatus::Pending, ReminderStatus::Pending], $reminder->dates()->pluck('status')->all());
    }

    public function test_a_birthday_card_goes_to_the_owner_instead_of_the_birthday_person(): void
    {
        Notification::fake();
        $reminder = Reminder::factory()->birthday()->create(['email' => 'maria@example.com']);
        $sent = $reminder->finalDate;
        $sent->update(['status' => ReminderStatus::Sent]);
        $reminder->scheduleNextBirthday($sent);
        $upcoming = $reminder->dates()->where('status', ReminderStatus::Pending)->sole();

        $this->artisan('reminders:test', ['reminder' => $reminder->id])->assertSuccessful();

        Notification::assertSentTo($reminder->user, ReminderDue::class, fn (ReminderDue $notification) => $notification->date->is($upcoming));
        Notification::assertSentOnDemandTimes(ReminderDue::class, 0);
    }

    public function test_an_unknown_notification_fails(): void
    {
        $this->artisan('reminders:test', ['reminder' => 999])
            ->expectsOutput('Notification 999 not found.')
            ->assertFailed();
    }

    public function test_a_sending_error_is_reported_as_a_failure(): void
    {
        $reminder = Reminder::factory()->create();
        Notification::shouldReceive('sendNow')->andThrow(new RuntimeException('SMTP down'));

        $this->artisan('reminders:test', ['reminder' => $reminder->id])
            ->expectsOutput('Sending failed: SMTP down')
            ->assertFailed();
    }
}
