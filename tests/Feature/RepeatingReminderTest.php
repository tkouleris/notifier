<?php

namespace Tests\Feature;

use App\Enums\ReminderStatus;
use App\Enums\Repeat;
use App\Models\Reminder;
use App\Models\User;
use App\Notifications\ReminderDue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RepeatingReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-19 12:00', 'UTC'));
    }

    private function validData(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Water the plants',
            'final_at' => '2026-10-23T18:00',
            'repeat' => 'weekly',
            'reminder_offsets' => [['amount' => 1, 'unit' => 'day'], ['amount' => 2, 'unit' => 'day']],
            'channel' => 'email',
            'email_theme' => 'light',
        ];
    }

    /**
     * The reminder's dates as [local time, is_final, status] rows, earliest first.
     */
    private function schedule(Reminder $reminder): array
    {
        return $reminder->dates()->get()->map(fn ($date) => [
            $date->notify_at->copy()->setTimezone($reminder->timezone)->format('Y-m-d H:i'),
            $date->is_final,
            $date->status->value,
        ])->all();
    }

    public function test_a_weekly_notification_is_scheduled_with_reminders_before_it(): void
    {
        $user = User::factory()->create(['timezone' => 'Europe/Athens']);

        $this->actingAs($user)->post('/notifications', $this->validData())
            ->assertSessionHasNoErrors()
            ->assertRedirect('/notifications');

        $reminder = $user->reminders()->sole();
        $this->assertSame(Repeat::Weekly, $reminder->repeat);
        $this->assertSame('2026-10-23 18:00', $reminder->repeat_anchor);
        $this->assertSame([['amount' => 2, 'unit' => 'day'], ['amount' => 1, 'unit' => 'day']], $reminder->reminder_offsets);
        $this->assertSame([
            ['2026-10-21 18:00', false, 'pending'],
            ['2026-10-22 18:00', false, 'pending'],
            ['2026-10-23 18:00', true, 'pending'],
        ], $this->schedule($reminder));
        $this->assertTrue($reminder->finalDate->notify_at->eq(Carbon::parse('2026-10-23 15:00', 'UTC')));
    }

    public function test_reminders_already_past_are_left_out_of_the_first_occurrence(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/notifications', $this->validData([
            'final_at' => '2026-10-20T18:00',
            'reminder_offsets' => [['amount' => 3, 'unit' => 'day'], ['amount' => 1, 'unit' => 'day']],
        ]))->assertSessionHasNoErrors();

        $this->assertSame([
            ['2026-10-19 18:00', false, 'pending'],
            ['2026-10-20 18:00', true, 'pending'],
        ], $this->schedule($user->reminders()->sole()));
    }

    public function test_reminders_must_be_shorter_than_the_period_and_listed_once(): void
    {
        $user = User::factory()->create();
        $post = fn (array $overrides) => $this->actingAs($user)->post('/notifications', $this->validData($overrides));

        $post(['reminder_offsets' => [['amount' => 1, 'unit' => 'week']]])
            ->assertSessionHasErrors(['reminder_offsets.0.unit' => "A weekly notification can't remind weeks before."]);
        $post(['reminder_offsets' => [['amount' => 7, 'unit' => 'day']]])
            ->assertSessionHasErrors(['reminder_offsets.0.amount' => 'A weekly notification can remind at most 6 days before.']);
        $post(['repeat' => 'monthly', 'reminder_offsets' => [['amount' => 4, 'unit' => 'week']]])
            ->assertSessionHasErrors(['reminder_offsets.0.amount' => 'A monthly notification can remind at most 3 weeks before.']);
        $post(['repeat' => 'monthly', 'reminder_offsets' => [['amount' => 1, 'unit' => 'month']]])
            ->assertSessionHasErrors('reminder_offsets.0.unit');
        $post(['repeat' => 'yearly', 'reminder_offsets' => [['amount' => 12, 'unit' => 'month']]])
            ->assertSessionHasErrors('reminder_offsets.0.amount');
        $post(['reminder_offsets' => [['amount' => 2, 'unit' => 'day'], ['amount' => 2, 'unit' => 'day']]])
            ->assertSessionHasErrors(['reminder_offsets.1.amount' => 'This reminder is listed more than once.']);
        $post(['reminder_offsets' => array_map(fn ($days) => ['amount' => $days, 'unit' => 'day'], [1, 2, 3, 4, 5])])
            ->assertSessionHasErrors(['reminder_offsets' => 'You can add at most 4 reminders.']);
        $post(['reminder_offsets' => [['amount' => 0, 'unit' => 'day'], ['amount' => 1, 'unit' => 'hour']]])
            ->assertSessionHasErrors(['reminder_offsets.0.amount', 'reminder_offsets.1.unit']);
        $post(['repeat' => 'daily'])->assertSessionHasErrors('repeat');

        $post(['repeat' => 'yearly', 'reminder_offsets' => [
            ['amount' => 11, 'unit' => 'month'], ['amount' => 51, 'unit' => 'week'], ['amount' => 364, 'unit' => 'day'],
        ]])->assertSessionHasNoErrors();
        $this->assertSame(0, Reminder::whereNull('repeat')->count());
    }

    public function test_blank_reminder_rows_are_dropped(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/notifications', $this->validData([
            'reminder_offsets' => [['amount' => '', 'unit' => 'day'], ['amount' => 1, 'unit' => 'day']],
        ]))->assertSessionHasNoErrors();

        $this->assertSame([['amount' => 1, 'unit' => 'day']], $user->reminders()->sole()->reminder_offsets);
    }

    public function test_fixed_reminder_dates_are_ignored_when_repeating_and_offsets_when_not(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/notifications', $this->validData([
            'reminder_dates' => ['not a date'],
            'reminder_offsets' => [],
        ]))->assertSessionHasNoErrors();
        $this->assertCount(1, $user->reminders()->sole()->dates);

        $user->reminders()->delete();

        $this->actingAs($user)->post('/notifications', $this->validData([
            'repeat' => '',
            'reminder_offsets' => [['amount' => 99, 'unit' => 'hour']],
            'reminder_dates' => ['2026-10-22T09:00'],
        ]))->assertSessionHasNoErrors();
        $reminder = $user->reminders()->sole();
        $this->assertNull($reminder->repeat);
        $this->assertNull($reminder->reminder_offsets);
        $this->assertCount(2, $reminder->dates);
    }

    public function test_each_occurrence_schedules_the_next_one_from_the_first_date(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->travelTo(Carbon::parse('2027-01-10 12:00', 'UTC'));
        $this->actingAs($user)->post('/notifications', $this->validData([
            'final_at' => '2027-01-31T09:00',
            'repeat' => 'monthly',
            'reminder_offsets' => [['amount' => 1, 'unit' => 'week']],
        ]))->assertSessionHasNoErrors();
        $reminder = $user->reminders()->sole();

        $this->travelTo(Carbon::parse('2027-01-24 09:00', 'UTC'));
        $this->artisan('reminders:send')->assertSuccessful();
        $this->travelTo(Carbon::parse('2027-01-31 09:00', 'UTC'));
        $this->artisan('reminders:send')->assertSuccessful();

        // January 31 falls on February 28, and the last sent final date is kept as history.
        $this->assertSame([
            ['2027-01-31 09:00', true, 'sent'],
            ['2027-02-21 09:00', false, 'pending'],
            ['2027-02-28 09:00', true, 'pending'],
        ], $this->schedule($reminder));
        $this->assertSame(ReminderStatus::Pending, $reminder->fresh()->status());

        $this->travelTo(Carbon::parse('2027-02-21 09:00', 'UTC'));
        $this->artisan('reminders:send')->assertSuccessful();
        $this->travelTo(Carbon::parse('2027-02-28 09:00', 'UTC'));
        $this->artisan('reminders:send')->assertSuccessful();

        // Counted from January 31 again, not from February 28.
        $this->assertSame([
            ['2027-02-28 09:00', true, 'sent'],
            ['2027-03-24 09:00', false, 'pending'],
            ['2027-03-31 09:00', true, 'pending'],
        ], $this->schedule($reminder));
        Notification::assertSentTimes(ReminderDue::class, 4);
    }

    public function test_occurrences_missed_while_nothing_was_sending_are_skipped(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->actingAs($user)->post('/notifications', $this->validData())->assertSessionHasNoErrors();
        $reminder = $user->reminders()->sole();

        // Three weeks without the scheduler: only the overdue dates already stored go out.
        $this->travelTo(Carbon::parse('2026-11-15 12:00', 'UTC'));
        $this->artisan('reminders:send')->assertSuccessful();

        $this->assertSame([
            ['2026-10-23 18:00', true, 'sent'],
            ['2026-11-18 18:00', false, 'pending'],
            ['2026-11-19 18:00', false, 'pending'],
            ['2026-11-20 18:00', true, 'pending'],
        ], $this->schedule($reminder));
        Notification::assertSentTimes(ReminderDue::class, 3);
    }

    public function test_emails_show_the_upcoming_date_and_the_next_occurrence(): void
    {
        $owner = User::factory()->create(['timezone' => 'Europe/Athens']);
        $reminder = Reminder::factory()->for($owner)
            ->repeating(Repeat::Weekly, '2026-10-20 09:00', [['amount' => 1, 'unit' => 'day']])
            ->create(['title' => 'Gym', 'timezone' => 'Europe/Athens']);
        $reminder->syncDates(Carbon::parse('2026-10-20 06:00', 'UTC'), []);

        $this->travelTo(Carbon::parse('2026-10-20 06:00', 'UTC'));
        $this->artisan('reminders:send')->assertSuccessful();
        [$sent, $early, $next] = $reminder->dates()->get()->all();

        $finalMail = (new ReminderDue($sent))->toMail($owner);
        $this->assertContains(
            'This notification repeats every week; the next one is on Tuesday, October 27, 2026 at 09:00 (Europe/Athens).',
            $finalMail->introLines
        );

        // After the clocks change, the local time stays 09:00.
        $this->assertTrue($next->notify_at->eq(Carbon::parse('2026-10-27 07:00', 'UTC')));
        $earlyMail = (new ReminderDue($early))->toMail($owner);
        $this->assertContains(
            'This is an early reminder. The final date is Tuesday, October 27, 2026 at 09:00 (Europe/Athens).',
            $earlyMail->introLines
        );
    }

    public function test_after_a_timezone_change_occurrences_keep_their_local_time(): void
    {
        $user = User::factory()->create(['timezone' => 'Europe/Athens']);
        $this->actingAs($user)->post('/notifications', $this->validData(['reminder_offsets' => []]))->assertSessionHasNoErrors();
        $reminder = $user->reminders()->sole();

        $user->changeTimezone('America/New_York');
        $reminder->refresh();

        $this->travelTo($reminder->finalDate->notify_at);
        $this->artisan('reminders:send')->assertSuccessful();

        $this->assertSame([
            ['2026-10-23 18:00', true, 'sent'],
            ['2026-10-30 18:00', true, 'pending'],
        ], $this->schedule($reminder));
    }

    public function test_the_forms_and_list_show_the_repeat(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/notifications/create')
            ->assertOk()
            ->assertSee('<option value="">Does not repeat</option>', false)
            ->assertSee('data-max-offsets="{&quot;day&quot;:6}"', false);

        $this->actingAs($user)->post('/notifications', $this->validData());
        $reminder = $user->reminders()->sole();

        $this->actingAs($user)->get("/notifications/{$reminder->id}/edit")
            ->assertOk()
            ->assertSee('value="weekly" selected', false)
            ->assertSee('value="2026-10-23T18:00"', false)
            ->assertSee('name="reminder_offsets[0][amount]" value="2"', false);
        $this->actingAs($user)->get('/notifications')->assertSee('Every week');
    }

    public function test_appointments_do_not_repeat(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/notifications/appointments', [
            'title' => 'Coffee with Maria',
            'final_at' => '2026-10-20T18:30',
            'reminder_dates' => ['2026-10-19T19:00'],
            'repeat' => 'weekly',
            'reminder_offsets' => [['amount' => 1, 'unit' => 'day']],
            'channel' => 'email',
            'email_theme' => 'light',
            'appointment_mode' => 'phone',
            'phone' => '6900000001',
            'participants' => [['name' => 'Maria']],
        ])->assertSessionHasNoErrors();

        $reminder = $user->reminders()->sole();
        $this->assertNull($reminder->repeat);
        $this->assertNull($reminder->reminder_offsets);
        $this->assertCount(2, $reminder->dates);
    }
}
