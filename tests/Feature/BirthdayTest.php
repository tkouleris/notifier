<?php

namespace Tests\Feature;

use App\Enums\BirthdayLayout;
use App\Enums\ReminderStatus;
use App\Enums\ReminderType;
use App\Jobs\SendReminderEmail;
use App\Models\Reminder;
use App\Models\User;
use App\Notifications\ReminderDue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class BirthdayTest extends TestCase
{
    use RefreshDatabase;

    private function validData(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Maria',
            'email' => 'maria@example.com',
            'day' => 15,
            'month' => 3,
            'layout' => 'balloons',
            'sender_name' => 'Thodoris',
        ];
    }

    public function test_the_sender_name_defaults_to_the_users_name(): void
    {
        $user = User::factory()->create(['name' => 'Thodoris Kouleris']);
        $reminder = Reminder::factory()->birthday()->for($user)->create(['sender_name' => 'Uncle Tom']);

        $this->actingAs($user)->get('/notifications/birthdays/create')
            ->assertSee('name="sender_name" value="Thodoris Kouleris"', false);
        $this->actingAs($user)->get("/notifications/birthdays/{$reminder->id}/edit")
            ->assertSee('name="sender_name" value="Uncle Tom"', false);
    }

    public function test_the_sender_name_is_required_and_saved(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/notifications/birthdays', ['sender_name' => ''] + $this->validData())
            ->assertSessionHasErrors('sender_name');

        $this->actingAs($user)->post('/notifications/birthdays', $this->validData(['sender_name' => 'Uncle Tom']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Uncle Tom', $user->reminders()->sole()->sender_name);
    }

    public function test_the_card_is_signed_and_sent_by_the_sender_name(): void
    {
        $owner = User::factory()->create(['name' => 'Thodoris']);
        $reminder = Reminder::factory()->birthday()->for($owner)->create(['title' => 'Maria', 'sender_name' => 'Uncle Tom']);

        $mail = (new ReminderDue($reminder->finalDate))->toMail(Notification::route('mail', 'maria@example.com'));

        $this->assertSame([config('mail.from.address'), 'Uncle Tom'], $mail->from);
        $this->assertSame('With love, Uncle Tom', $mail->salutation);
        $this->assertSame(['Uncle Tom is thinking of you today and wishes you a wonderful year ahead.'], $mail->introLines);
        $html = (string) $mail->render();
        $this->assertStringContainsString('With love, Uncle Tom', $html);
        $this->assertStringNotContainsString('Thodoris', $html);
    }

    public function test_the_form_offers_every_layout_and_remembers_the_chosen_one(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->birthday()->for($user)->create(['layout' => BirthdayLayout::Cake]);

        $this->actingAs($user)->get('/notifications/birthdays/create')
            ->assertOk()
            ->assertSeeInOrder(['Balloons', 'Confetti', 'Cake'])
            ->assertSee('name="layout" value="balloons" checked', false);
        $this->actingAs($user)->get("/notifications/birthdays/{$reminder->id}/edit")
            ->assertSee('name="layout" value="cake" checked', false)
            ->assertDontSee('name="layout" value="balloons" checked', false);
    }

    public function test_the_chosen_layout_is_saved_and_must_be_a_known_one(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/notifications/birthdays', $this->validData(['layout' => 'confetti']))->assertSessionHasNoErrors();
        $this->assertSame(BirthdayLayout::Confetti, $user->reminders()->sole()->layout);

        $this->actingAs($user)->post('/notifications/birthdays', $this->validData(['layout' => 'disco']))
            ->assertSessionHasErrors(['layout' => 'Choose one of the card layouts.']);
        $this->actingAs($user)->post('/notifications/birthdays', ['layout' => null] + $this->validData())
            ->assertSessionHasErrors('layout');
    }

    public function test_the_card_is_sent_with_the_chosen_layout(): void
    {
        // The next card goes out on March 15, 2027.
        $this->travelTo(Carbon::parse('2026-10-07 12:00', 'UTC'));
        $owner = User::factory()->create(['name' => 'Thodoris']);
        $notifiable = Notification::route('mail', 'maria@example.com');

        foreach (BirthdayLayout::cases() as $layout) {
            $reminder = Reminder::factory()->birthday()->for($owner)->create([
                'title' => 'Maria',
                'birth_year' => 1997,
                'message' => "Line one <b>\nLine two",
                'layout' => $layout,
            ]);
            $mail = (new ReminderDue($reminder->finalDate))->toMail($notifiable);

            $this->assertSame(['html' => "emails.birthdays.{$layout->value}", 'text' => 'emails.birthdays.text'], $mail->view);
            $html = (string) $mail->render();
            $this->assertStringContainsString('Happy 30th birthday, Maria!', $html);
            $this->assertStringContainsString('Thodoris is thinking of you today', $html);
            $this->assertStringContainsString('Line one &lt;b&gt;<br />', $html);
            $this->assertStringContainsString('With love, Thodoris', $html);
        }

        $text = view('emails.birthdays.text', $mail->data())->render();
        $this->assertStringContainsString("Line one <b>\nLine two", $text);
        $this->assertStringContainsString('Happy 30th birthday, Maria! 🎂', $text);
    }

    public function test_cards_saved_without_a_layout_use_the_first_one(): void
    {
        $reminder = Reminder::factory()->birthday()->create(['layout' => null]);

        $mail = (new ReminderDue($reminder->finalDate))->toMail(Notification::route('mail', 'maria@example.com'));

        $this->assertSame('emails.birthdays.balloons', $mail->view['html']);
    }

    public function test_each_layout_can_be_previewed(): void
    {
        $user = User::factory()->create(['name' => 'Thodoris']);

        foreach (BirthdayLayout::cases() as $layout) {
            $this->actingAs($user)->get("/notifications/birthdays/layouts/{$layout->value}")
                ->assertOk()
                ->assertSee('Happy 30th birthday, Alex!')
                ->assertSee('With love, Thodoris');
        }

        $this->actingAs($user)->get('/notifications/birthdays/layouts/disco')->assertNotFound();
        auth()->logout();
        $this->get('/notifications/birthdays/layouts/cake')->assertRedirect('/login');
    }

    public function test_create_and_edit_forms_can_be_rendered(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->birthday()->for($user)->create(['title' => 'Maria', 'birth_year' => 1990]);

        $this->actingAs($user)->get('/notifications/birthdays/create')->assertOk()->assertSee('New birthday');
        $this->actingAs($user)->get("/notifications/birthdays/{$reminder->id}/edit")
            ->assertOk()
            ->assertSee('value="Maria"', false)
            ->assertSee('name="year" value="1990"', false);
    }

    public function test_a_birthday_is_scheduled_for_its_next_occurrence_in_the_users_timezone(): void
    {
        $this->travelTo(Carbon::parse('2026-10-07 12:00', 'UTC'));
        $user = User::factory()->create(['timezone' => 'Europe/Athens']);

        $this->actingAs($user)->post('/notifications/birthdays', $this->validData([
            'email' => ' Maria@Example.com ',
            'year' => 1990,
            'message' => 'Have a great day!',
        ]))->assertRedirect('/notifications');

        $reminder = $user->reminders()->sole();
        $this->assertSame(ReminderType::Birthday, $reminder->type);
        $this->assertSame('Maria', $reminder->title);
        $this->assertSame('maria@example.com', $reminder->email);
        $this->assertSame([15, 3, 1990], [$reminder->birth_day, $reminder->birth_month, $reminder->birth_year]);

        $date = $reminder->dates()->sole();
        $this->assertTrue($date->is_final);
        $this->assertTrue($date->notify_at->eq(Carbon::parse('2027-03-15 09:00', 'Europe/Athens')));
        $this->assertSame(ReminderStatus::Pending, $date->status);
    }

    public function test_a_birthday_later_this_year_is_scheduled_this_year(): void
    {
        $this->travelTo(Carbon::parse('2026-10-07 12:00', 'UTC'));
        $user = User::factory()->create();

        $this->actingAs($user)->post('/notifications/birthdays', $this->validData(['day' => 20, 'month' => 12]));

        $this->assertTrue($user->reminders()->sole()->finalDate->notify_at->eq(Carbon::parse('2026-12-20 09:00', 'UTC')));
    }

    public function test_the_year_is_optional_but_the_rest_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/notifications/birthdays', [])
            ->assertSessionHasErrors(['name', 'email', 'day', 'month'])
            ->assertSessionDoesntHaveErrors('year');
    }

    public function test_impossible_dates_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/notifications/birthdays', $this->validData(['day' => 31, 'month' => 4]))
            ->assertSessionHasErrors(['day' => 'This day does not exist in that month.']);
        $this->actingAs($user)->post('/notifications/birthdays', $this->validData(['day' => 29, 'month' => 2, 'year' => 1991]))
            ->assertSessionHasErrors('day');
        $this->actingAs($user)->post('/notifications/birthdays', $this->validData(['year' => now()->year + 1, 'email' => 'nope']))
            ->assertSessionHasErrors(['year', 'email']);

        // February 29 is fine without a year, or in a leap year.
        $this->actingAs($user)->post('/notifications/birthdays', $this->validData(['day' => 29, 'month' => 2]))
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/notifications/birthdays', $this->validData(['day' => 29, 'month' => 2, 'year' => 1992]))
            ->assertSessionHasNoErrors();
    }

    public function test_a_february_29_birthday_falls_on_february_28_in_other_years(): void
    {
        $reminder = Reminder::factory()->birthday()->make(['birth_day' => 29, 'birth_month' => 2]);

        $this->assertTrue($reminder->nextBirthday(Carbon::parse('2026-10-07'))->eq(Carbon::parse('2027-02-28 09:00')));
        $this->assertTrue($reminder->nextBirthday(Carbon::parse('2027-10-07'))->eq(Carbon::parse('2028-02-29 09:00')));
    }

    public function test_updating_a_birthday_reschedules_it(): void
    {
        $this->travelTo(Carbon::parse('2026-10-07 12:00', 'UTC'));
        $user = User::factory()->create();
        $reminder = Reminder::factory()->birthday()->for($user)->create();

        $this->actingAs($user)->put("/notifications/birthdays/{$reminder->id}", $this->validData([
            'name' => 'Nikos',
            'day' => 1,
            'month' => 11,
        ]))->assertRedirect('/notifications');

        $reminder->refresh();
        $this->assertSame('Nikos', $reminder->title);
        $this->assertTrue($reminder->dates()->sole()->notify_at->eq(Carbon::parse('2026-11-01 09:00', 'UTC')));
    }

    public function test_birthdays_and_other_notifications_use_their_own_forms(): void
    {
        $user = User::factory()->create();
        $birthday = Reminder::factory()->birthday()->for($user)->create();
        $other = Reminder::factory()->for($user)->create();

        $this->actingAs($user)->get("/notifications/{$birthday->id}/edit")->assertRedirect("/notifications/birthdays/{$birthday->id}/edit");
        $this->actingAs($user)->put("/notifications/{$birthday->id}", ['title' => 'x', 'final_at' => now()->addDay()->format('Y-m-d\TH:i'), 'channel' => 'email'])->assertNotFound();
        $this->actingAs($user)->get("/notifications/birthdays/{$other->id}/edit")->assertNotFound();
        $this->actingAs($user)->put("/notifications/birthdays/{$other->id}", $this->validData())->assertNotFound();
    }

    public function test_users_cannot_touch_other_users_birthdays(): void
    {
        $reminder = Reminder::factory()->birthday()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->get("/notifications/birthdays/{$reminder->id}/edit")->assertForbidden();
        $this->actingAs($user)->put("/notifications/birthdays/{$reminder->id}", $this->validData())->assertForbidden();
    }

    public function test_the_list_shows_birthdays(): void
    {
        $user = User::factory()->create();
        Reminder::factory()->birthday()->for($user)->create([
            'title' => 'Maria',
            'email' => 'maria@example.com',
            'birth_year' => 1990,
            'message' => 'A secret wish',
        ]);

        $this->actingAs($user)->get('/notifications')
            ->assertOk()
            ->assertSee('New birthday')
            ->assertSee('Maria')
            ->assertSeeInOrder(['Birthday', 'March 15, 1990'])
            ->assertSee('maria@example.com')
            ->assertDontSee('A secret wish');
    }

    public function test_the_card_goes_to_the_birthday_person_and_repeats_every_year(): void
    {
        Queue::fake();
        $this->travelTo(Carbon::parse('2027-03-15 08:00', 'UTC'));
        $reminder = Reminder::factory()->birthday()->create(['email' => 'maria@example.com']);
        $date = $reminder->finalDate;
        $this->assertTrue($date->notify_at->eq(Carbon::parse('2027-03-15 09:00', 'UTC')));

        $this->travelTo(Carbon::parse('2027-03-15 09:00', 'UTC'));
        $this->artisan('reminders:send')->assertSuccessful();

        Queue::assertPushed(SendReminderEmail::class, 1);
        Queue::assertPushed(SendReminderEmail::class, fn (SendReminderEmail $job) => $job->date->is($date) && $job->email === 'maria@example.com');

        [$sent, $next] = $reminder->dates()->get()->all();
        $this->assertTrue($sent->is($date));
        $this->assertSame(ReminderStatus::Sent, $sent->status);
        $this->assertSame(ReminderStatus::Pending, $next->status);
        $this->assertTrue($next->notify_at->eq(Carbon::parse('2028-03-15 09:00', 'UTC')));
        $this->assertSame(ReminderStatus::Pending, $reminder->fresh()->status());

        // A year later only the latest sent card is kept, next to the following one.
        $this->travelTo(Carbon::parse('2028-03-15 09:00', 'UTC'));
        $this->artisan('reminders:send')->assertSuccessful();

        $this->assertEquals(
            ['2028-03-15 09:00', '2029-03-15 09:00'],
            $reminder->dates()->get()->map(fn ($date) => $date->notify_at->format('Y-m-d H:i'))->all()
        );
    }

    public function test_a_card_that_finally_fails_marks_its_date_failed(): void
    {
        $reminder = Reminder::factory()->birthday()->create(['email' => 'maria@example.com']);
        $date = $reminder->finalDate;
        $date->update(['status' => ReminderStatus::Sent]);

        (new SendReminderEmail($date, 'maria@example.com'))->failed(new RuntimeException('SMTP down'));

        $this->assertSame(ReminderStatus::Failed, $date->fresh()->status);
    }

    public function test_the_card_wishes_happy_birthday_with_the_age_when_the_year_is_known(): void
    {
        Notification::fake();
        $this->travelTo(Carbon::parse('2027-03-15 08:00', 'UTC'));
        $owner = User::factory()->create(['name' => 'Thodoris']);
        $reminder = Reminder::factory()->birthday()->for($owner)->create([
            'title' => 'Maria',
            'email' => 'maria@example.com',
            'birth_year' => 1996,
            'message' => 'See you tonight!',
        ]);

        $this->travelTo(Carbon::parse('2027-03-15 09:00', 'UTC'));
        $this->artisan('reminders:send')->assertSuccessful();

        Notification::assertNothingSentTo($owner);
        Notification::assertSentOnDemand(ReminderDue::class, function (ReminderDue $notification, array $channels, $notifiable) {
            $mail = $notification->toMail($notifiable);

            return $notifiable->routes['mail'] === 'maria@example.com'
                && $mail->subject === 'Happy 31st birthday, Maria! 🎂'
                && $mail->introLines === ['Thodoris is thinking of you today and wishes you a wonderful year ahead.', 'See you tonight!']
                && $mail->salutation === 'With love, Thodoris'
                && $mail->actionUrl === null;
        });

        $reminder->update(['birth_year' => null]);
        $mail = (new ReminderDue($reminder->dates()->first()))->toMail(Notification::route('mail', 'maria@example.com'));
        $this->assertSame('Happy birthday, Maria! 🎂', $mail->subject);
    }

    public function test_changing_timezone_keeps_the_card_at_the_same_local_time(): void
    {
        $this->travelTo(Carbon::parse('2026-10-07 12:00', 'UTC'));
        $user = User::factory()->create(['timezone' => 'UTC']);
        $reminder = Reminder::factory()->birthday()->for($user)->create();

        $user->changeTimezone('Europe/Athens');

        $this->assertTrue($reminder->finalDate()->first()->notify_at->eq(Carbon::parse('2027-03-15 09:00', 'Europe/Athens')));
    }
}
