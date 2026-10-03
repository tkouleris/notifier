<?php

namespace Tests\Feature;

use App\Enums\ReminderStatus;
use App\Jobs\SendReminderEmail;
use App\Models\Reminder;
use App\Models\ReminderDate;
use App\Models\User;
use App\Notifications\ReminderDue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    private const FORMAT = 'Y-m-d\TH:i';

    private function validData(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Team dinner',
            'final_at' => now()->addDays(10)->format(self::FORMAT),
            'channel' => 'email',
        ];
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/notifications')->assertRedirect('/login');
    }

    public function test_the_old_home_url_redirects_to_the_notification_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/home')->assertRedirect('/notifications');
    }

    public function test_users_only_see_their_own_notifications(): void
    {
        $user = User::factory()->create();
        Reminder::factory()->for($user)->create(['title' => 'Mine']);
        Reminder::factory()->create(['title' => 'Someone else']);

        $this->actingAs($user)->get('/notifications')
            ->assertOk()
            ->assertSee('Mine')
            ->assertDontSee('Someone else');
    }

    public function test_the_list_shows_every_date_of_a_notification(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->for($user)->create();
        $reminder->syncDates(now()->setDate(2030, 5, 10)->setTime(9, 0), [now()->setDate(2030, 5, 1)->setTime(9, 0)]);

        $this->actingAs($user)->get('/notifications')
            ->assertSeeInOrder(['Reminder:', 'May 1, 2030 09:00', 'Final:', 'May 10, 2030 09:00']);
    }

    public function test_create_and_edit_forms_can_be_rendered(): void
    {
        // The form shows times in the user's settings timezone, not the one the notification was made in.
        $user = User::factory()->create(['timezone' => 'Europe/Athens']);
        $reminder = Reminder::factory()->for($user)->create(['timezone' => 'UTC']);
        $reminder->syncDates(now()->setDate(2030, 5, 10)->setTime(6, 30), [now()->setDate(2030, 5, 1)->setTime(6, 30)]);

        $this->actingAs($user)->get('/notifications/create')->assertOk()->assertSee('New notification')->assertSee('Timezone: Europe/Athens');
        $this->actingAs($user)->get("/notifications/{$reminder->id}/edit")
            ->assertOk()
            ->assertSee('name="final_at" required value="2030-05-10T09:30"', false)
            ->assertSee('name="reminder_dates[0]" value="2030-05-01T09:30"', false);
    }

    public function test_the_edit_form_leaves_out_reminders_already_sent(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->for($user)
            ->has(ReminderDate::factory()->final(), 'dates')
            ->has(ReminderDate::factory()->sent()->state(['notify_at' => now()->subDay()]), 'dates')
            ->create();

        $this->actingAs($user)->get("/notifications/{$reminder->id}/edit")
            ->assertDontSee('name="reminder_dates[0]"', false)
            ->assertSee('Dates that have already been sent are not shown.');
    }

    public function test_the_create_form_starts_without_reminder_or_people_rows(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/notifications/create')
            ->assertDontSee('name="reminder_dates[0]"', false)
            ->assertDontSee('name="recipients[0]"', false)
            ->assertSee('+ Add reminder')
            ->assertSee('+ Add person');
    }

    public function test_rows_with_errors_are_shown_again_under_their_own_keys(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/notifications/create')
            ->followingRedirects()
            ->post('/notifications', $this->validData([
                'reminder_dates' => [2 => now()->subDay()->format(self::FORMAT)],
                'recipients' => [5 => 'not-an-email'],
            ]))
            ->assertSee('name="reminder_dates[2]"', false)
            ->assertSee('Reminders must be in the future.')
            ->assertSee('name="recipients[5]" value="not-an-email"', false)
            ->assertSee('Enter a valid email address.')
            ->assertSee('data-next-key="6"', false);
    }

    public function test_a_notification_can_be_created_in_the_users_timezone(): void
    {
        $user = User::factory()->create(['timezone' => 'Europe/Athens']);
        $local = now('Europe/Athens')->addDay()->setTime(9, 30);

        $this->actingAs($user)->post('/notifications', $this->validData([
            'title' => 'Dentist',
            'message' => 'Bring the papers',
            'final_at' => $local->format(self::FORMAT),
            'timezone' => 'America/New_York', // ignored: the timezone from settings wins
        ]))->assertRedirect('/notifications');

        $reminder = $user->reminders()->sole();
        $this->assertSame('Dentist', $reminder->title);
        $this->assertSame('Europe/Athens', $reminder->timezone);

        $date = $reminder->dates()->sole();
        $this->assertTrue($date->is_final);
        $this->assertTrue($date->notify_at->eq($local->copy()->utc()));
        $this->assertSame(ReminderStatus::Pending, $date->status);
    }

    public function test_a_notification_can_have_four_optional_reminders_before_the_final_date(): void
    {
        $user = User::factory()->create();
        $final = now()->addDays(10)->setTime(9, 0);

        $this->actingAs($user)->post('/notifications', $this->validData([
            'final_at' => $final->format(self::FORMAT),
            'reminder_dates' => [
                $final->copy()->subDays(1)->format(self::FORMAT),
                '',
                $final->copy()->subDays(7)->format(self::FORMAT),
                $final->copy()->subDays(3)->format(self::FORMAT),
            ],
        ]))->assertRedirect('/notifications');

        $dates = $user->reminders()->sole()->dates;
        $this->assertCount(4, $dates);
        $this->assertSame([false, false, false, true], $dates->pluck('is_final')->all());
        $this->assertTrue($dates[0]->notify_at->eq($final->copy()->subDays(7)));
        $this->assertTrue($dates[3]->notify_at->eq($final));
    }

    public function test_the_final_date_is_required_and_must_be_in_the_future(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/notifications', $this->validData(['final_at' => '']))
            ->assertSessionHasErrors('final_at');
        $this->actingAs($user)->post('/notifications', $this->validData([
            'final_at' => now()->subHour()->format(self::FORMAT),
        ]))->assertSessionHasErrors('final_at');

        $this->assertDatabaseCount('reminders', 0);
    }

    public function test_reminders_must_be_in_the_future_and_before_the_final_date(): void
    {
        $user = User::factory()->create();
        $final = now()->addDays(5);

        $this->actingAs($user)->post('/notifications', $this->validData([
            'final_at' => $final->format(self::FORMAT),
            'reminder_dates' => [
                now()->subHour()->format(self::FORMAT),
                $final->format(self::FORMAT),
                $final->copy()->addDay()->format(self::FORMAT),
                'not a date',
            ],
        ]))->assertSessionHasErrors(['reminder_dates.0', 'reminder_dates.1', 'reminder_dates.2', 'reminder_dates.3']);

        $this->assertDatabaseCount('reminders', 0);
    }

    public function test_more_than_four_reminders_or_duplicates_are_rejected(): void
    {
        $user = User::factory()->create();
        $day = fn (int $n) => now()->addDays($n)->format(self::FORMAT);

        $this->actingAs($user)->post('/notifications', $this->validData([
            'reminder_dates' => [$day(1), $day(2), $day(3), $day(4), $day(5)],
        ]))->assertSessionHasErrors('reminder_dates');

        $this->actingAs($user)->post('/notifications', $this->validData([
            'reminder_dates' => [$day(1), $day(1)],
        ]))->assertSessionHasErrors('reminder_dates.1');

        $this->assertDatabaseCount('reminders', 0);
    }

    public function test_unknown_channels_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/notifications', $this->validData(['channel' => 'pigeon']))
            ->assertSessionHasErrors('channel');
    }

    public function test_updating_a_notification_replaces_its_whole_schedule(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->for($user)
            ->has(ReminderDate::factory()->final()->sent()->state(['notify_at' => now()->subHour()]), 'dates')
            ->has(ReminderDate::factory()->sent()->state(['notify_at' => now()->subDay()]), 'dates')
            ->create();
        $final = now()->addDays(3)->setTime(8, 0);

        $this->actingAs($user)->put("/notifications/{$reminder->id}", $this->validData([
            'title' => 'Renamed',
            'final_at' => $final->format(self::FORMAT),
            'reminder_dates' => [$final->copy()->subDay()->format(self::FORMAT)],
        ]))->assertRedirect('/notifications');

        $reminder->refresh();
        $this->assertSame('Renamed', $reminder->title);
        $this->assertCount(2, $reminder->dates);
        $this->assertTrue($reminder->dates->every(fn ($date) => $date->status === ReminderStatus::Pending));
        $this->assertTrue($reminder->dates->last()->is_final);
        $this->assertTrue($reminder->dates->last()->notify_at->eq($final));
    }

    public function test_a_notification_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->for($user)->create();
        $reminder->syncRecipients(['a@example.com']);

        $this->actingAs($user)->delete("/notifications/{$reminder->id}")->assertRedirect('/notifications');

        $this->assertModelMissing($reminder);
        $this->assertDatabaseCount('reminder_dates', 0);
        $this->assertDatabaseCount('reminder_recipients', 0);
    }

    public function test_users_cannot_touch_other_users_notifications(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->create();

        $this->actingAs($user)->get("/notifications/{$reminder->id}/edit")->assertForbidden();
        $this->actingAs($user)->put("/notifications/{$reminder->id}", $this->validData(['title' => 'Hijacked']))
            ->assertForbidden();
        $this->actingAs($user)->delete("/notifications/{$reminder->id}")->assertForbidden();

        $this->assertModelExists($reminder);
    }

    public function test_a_notification_can_notify_up_to_three_other_people(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/notifications', $this->validData([
            'recipients' => [' Anna@Example.com ', '', 'bob@example.com', 'carl@example.com'],
        ]))->assertRedirect('/notifications');

        $reminder = $user->reminders()->sole();
        $this->assertSame(
            ['anna@example.com', 'bob@example.com', 'carl@example.com'],
            $reminder->recipients()->orderBy('id')->pluck('email')->all()
        );

        $this->actingAs($user)->get('/notifications')->assertSee('Also notifies anna@example.com, bob@example.com, carl@example.com');
    }

    public function test_more_than_three_other_people_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/notifications', $this->validData([
            'recipients' => ['a@example.com', 'b@example.com', 'c@example.com', 'd@example.com'],
        ]))->assertSessionHasErrors('recipients');

        $this->assertDatabaseCount('reminders', 0);
    }

    public function test_invalid_duplicate_and_own_recipient_emails_are_rejected(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);

        $this->actingAs($user)->post('/notifications', $this->validData([
            'recipients' => ['not-an-email', 'same@example.com', 'SAME@example.com'],
        ]))->assertSessionHasErrors(['recipients.0', 'recipients.1', 'recipients.2']);

        $this->actingAs($user)->post('/notifications', $this->validData([
            'recipients' => ['Me@Example.com'],
        ]))->assertSessionHasErrors('recipients.0');

        $this->assertDatabaseCount('reminders', 0);
    }

    public function test_updating_a_notification_replaces_its_recipients(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->for($user)->create();
        $reminder->syncRecipients(['old@example.com', 'kept@example.com']);

        $this->actingAs($user)->get("/notifications/{$reminder->id}/edit")
            ->assertSee('value="old@example.com"', false);

        $this->actingAs($user)->put("/notifications/{$reminder->id}", $this->validData([
            'recipients' => ['kept@example.com', 'new@example.com'],
        ]))->assertRedirect('/notifications');

        $this->assertSame(
            ['kept@example.com', 'new@example.com'],
            $reminder->recipients()->orderBy('id')->pluck('email')->all()
        );
    }

    public function test_each_due_date_is_sent_on_its_own(): void
    {
        Notification::fake();

        $reminder = Reminder::factory()
            ->has(ReminderDate::factory()->due(), 'dates')
            ->has(ReminderDate::factory()->final(), 'dates')
            ->create();
        [$early, $final] = $reminder->dates->all();

        $this->artisan('reminders:send')->assertSuccessful();

        Notification::assertSentTo($reminder->user, ReminderDue::class, function (ReminderDue $notification, array $channels) use ($early) {
            return $notification->date->is($early) && $channels === ['mail'];
        });
        Notification::assertSentTimes(ReminderDue::class, 1);
        $this->assertSame(ReminderStatus::Sent, $early->fresh()->status);
        $this->assertNotNull($early->fresh()->sent_at);
        $this->assertSame(ReminderStatus::Pending, $final->fresh()->status);
        $this->assertSame(ReminderStatus::Pending, $reminder->fresh()->status());

        $this->travel(2)->days();
        $this->artisan('reminders:send')->assertSuccessful();

        Notification::assertSentTimes(ReminderDue::class, 2);
        $this->assertSame(ReminderStatus::Sent, $final->fresh()->status);
        $this->assertSame(ReminderStatus::Sent, $reminder->fresh()->status());
    }

    public function test_due_dates_are_emailed_to_the_other_people_too(): void
    {
        Notification::fake();

        $reminder = Reminder::factory()->has(ReminderDate::factory()->final()->due(), 'dates')->create();
        $reminder->syncRecipients(['a@example.com', 'b@example.com']);

        $this->artisan('reminders:send')->assertSuccessful();

        Notification::assertSentTo($reminder->user, ReminderDue::class);
        foreach (['a@example.com', 'b@example.com'] as $email) {
            Notification::assertSentOnDemand(ReminderDue::class, function ($notification, $channels, $notifiable) use ($email) {
                return $notifiable->routes['mail'] === $email;
            });
        }
        Notification::assertSentOnDemandTimes(ReminderDue::class, 2);
    }

    public function test_each_email_is_queued_as_its_own_job(): void
    {
        Queue::fake();

        $reminder = Reminder::factory()->has(ReminderDate::factory()->final()->due(), 'dates')->create();
        $reminder->syncRecipients(['a@example.com', 'b@example.com']);
        $date = $reminder->finalDate;

        $this->artisan('reminders:send')->assertSuccessful();

        Queue::assertPushed(SendReminderEmail::class, 3);
        foreach ([null, 'a@example.com', 'b@example.com'] as $email) {
            Queue::assertPushed(SendReminderEmail::class, fn (SendReminderEmail $job) => $job->date->is($date) && $job->email === $email);
        }
        $this->assertSame(ReminderStatus::Sent, $date->fresh()->status);
    }

    public function test_a_job_that_finally_fails_marks_its_date_failed(): void
    {
        $reminder = Reminder::factory()->has(ReminderDate::factory()->final()->due(), 'dates')->create();
        $date = $reminder->finalDate;
        $date->update(['status' => ReminderStatus::Sent]);

        (new SendReminderEmail($date, 'a@example.com'))->failed(new RuntimeException('SMTP down'));

        $this->assertSame(ReminderStatus::Failed, $date->fresh()->status);
        $this->assertSame(ReminderStatus::Failed, $reminder->fresh()->status());
    }

    public function test_sent_dates_are_not_sent_twice(): void
    {
        Notification::fake();

        Reminder::factory()->has(ReminderDate::factory()->final()->due(), 'dates')->create();

        $this->artisan('reminders:send');
        $this->artisan('reminders:send');

        Notification::assertSentTimes(ReminderDue::class, 1);
    }

    public function test_the_email_tells_other_people_who_asked_for_the_reminder(): void
    {
        $owner = User::factory()->create(['name' => 'Thodoris']);
        $reminder = Reminder::factory()->for($owner)->create(['title' => 'Team dinner']);
        $final = $reminder->finalDate;

        $ownerMail = (new ReminderDue($final))->toMail($owner);
        $otherMail = (new ReminderDue($final))->toMail(Notification::route('mail', 'a@example.com'));

        $this->assertSame('Hello Thodoris,', $ownerMail->greeting);
        $this->assertNotNull($ownerMail->actionUrl);
        $this->assertSame('Hello,', $otherMail->greeting);
        $this->assertContains('Thodoris asked us to remind you about this.', $otherMail->introLines);
        $this->assertNull($otherMail->actionUrl);
    }

    public function test_early_reminder_emails_mention_the_final_date(): void
    {
        $owner = User::factory()->create(['timezone' => 'Europe/Athens']);
        $reminder = Reminder::factory()->for($owner)->create(['title' => 'Exam', 'timezone' => 'Europe/Athens']);
        $reminder->syncDates(now()->setDate(2030, 6, 14)->setTime(6, 0), [now()->setDate(2030, 6, 7)->setTime(6, 0)]);
        [$early, $final] = $reminder->dates->all();

        $earlyMail = (new ReminderDue($early))->toMail($owner);
        $finalMail = (new ReminderDue($final))->toMail($owner);

        $this->assertSame('Upcoming: Exam', $earlyMail->subject);
        $this->assertContains(
            'This is an early reminder. The final date is Friday, June 14, 2030 at 09:00 (Europe/Athens).',
            $earlyMail->introLines
        );
        $this->assertSame('Reminder: Exam', $finalMail->subject);
    }
}
