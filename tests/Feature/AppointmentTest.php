<?php

namespace Tests\Feature;

use App\Enums\AppointmentMode;
use App\Enums\ReminderType;
use App\Models\Reminder;
use App\Models\User;
use App\Notifications\ReminderDue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-15 12:00', 'UTC'));
    }

    private function validData(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Coffee with Maria',
            'final_at' => '2026-10-20T18:30',
            'reminder_dates' => ['2026-10-19T09:00'],
            'channel' => 'email',
            'email_theme' => 'light',
            'appointment_mode' => 'in_person',
            'address' => 'Ermou 10, Athens',
            'latitude' => '37.9755000',
            'longitude' => '23.7348000',
            'participants' => [
                ['name' => 'Maria', 'email' => 'Maria@Example.com ', 'phone' => '6900000001'],
                ['name' => 'Nikos', 'email' => 'nikos@example.com'],
            ],
        ];
    }

    private function appointmentWith(User $owner, array $attributes = []): Reminder
    {
        $reminder = Reminder::factory()->appointment()->for($owner)->create($attributes);
        $reminder->syncParticipants([['name' => 'Maria', 'email' => 'maria@example.com', 'phone' => '6900000001']]);

        return $reminder->refresh();
    }

    public function test_the_forms_can_be_rendered(): void
    {
        $user = User::factory()->create();
        $reminder = $this->appointmentWith($user);

        $this->actingAs($user)->get('/notifications/appointments/create')
            ->assertOk()
            ->assertSeeInOrder(['In person', 'Phone call', 'Online'])
            ->assertSee('name="appointment_mode" value="in_person" checked', false)
            ->assertSee('name="participants[0][name]"', false)
            ->assertSee('name="participants[0][email]"', false)
            ->assertSee('name="participants[0][phone]"', false)
            ->assertSee('appointment-map', false);

        $this->actingAs($user)->get("/notifications/appointments/{$reminder->id}/edit")
            ->assertOk()
            ->assertSee('value="Ermou 10, Athens"', false)
            ->assertSee('value="maria@example.com"', false)
            ->assertSee('value="6900000001"', false);
    }

    public function test_an_in_person_appointment_can_be_created(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/notifications/appointments', $this->validData())
            ->assertSessionHasNoErrors()
            ->assertRedirect('/notifications');

        $reminder = $user->reminders()->sole();
        $this->assertSame(ReminderType::Appointment, $reminder->type);
        $this->assertSame(AppointmentMode::InPerson, $reminder->appointment_mode);
        $this->assertSame('Ermou 10, Athens', $reminder->address);
        $this->assertEqualsWithDelta(37.9755, $reminder->latitude, 0.00001);
        $this->assertEqualsWithDelta(23.7348, $reminder->longitude, 0.00001);
        $this->assertSame('2026-10-20 18:30:00', $reminder->finalDate->notify_at->toDateTimeString());
        $this->assertCount(2, $reminder->dates);
        $this->assertSame(
            [
                ['name' => 'Maria', 'email' => 'maria@example.com', 'phone' => '6900000001'],
                ['name' => 'Nikos', 'email' => 'nikos@example.com', 'phone' => null],
            ],
            $reminder->participants->map->only('name', 'email', 'phone')->all()
        );
        $this->assertSame(0, $reminder->recipients()->count());
    }

    public function test_a_persons_email_and_phone_are_optional(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/notifications/appointments', $this->validData(['participants' => [
            ['name' => 'Maria', 'email' => '', 'phone' => ''],
            ['name' => 'Nikos', 'phone' => '6900000002'],
            ['name' => '', 'email' => '', 'phone' => ''],
        ]]))->assertSessionHasNoErrors();

        $this->assertSame(
            [['name' => 'Maria', 'email' => null, 'phone' => null], ['name' => 'Nikos', 'email' => null, 'phone' => '6900000002']],
            $user->reminders()->sole()->participants->map->only('name', 'email', 'phone')->all()
        );
    }

    public function test_the_list_shows_appointments_and_links_to_their_form(): void
    {
        $user = User::factory()->create();
        $reminder = $this->appointmentWith($user);

        $this->actingAs($user)->get('/notifications')
            ->assertOk()
            ->assertSee('/notifications/appointments/create')
            ->assertSee('In person')
            ->assertSee('with Maria')
            ->assertSee("/notifications/appointments/{$reminder->id}/edit");
    }

    public function test_in_person_needs_an_address_but_phone_and_online_do_not(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/notifications/appointments', $this->validData(['address' => '']))
            ->assertSessionHasErrors(['address' => 'Enter the address, or pick the place on the map.']);

        $this->actingAs($user)->post('/notifications/appointments', $this->validData(['address' => '', 'appointment_mode' => 'phone', 'phone' => '+30 210 1234567']))
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/notifications/appointments', $this->validData(['address' => '', 'appointment_mode' => 'online', 'meeting_url' => 'https://meet.example.com/abc']))
            ->assertSessionHasNoErrors();

        $this->assertSame(['+30 210 1234567', null], $user->reminders()->orderBy('id')->pluck('phone')->all());
    }

    public function test_the_mode_and_meeting_link_are_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/notifications/appointments', $this->validData(['appointment_mode' => 'carrier_pigeon']))
            ->assertSessionHasErrors('appointment_mode');
        $this->actingAs($user)->post('/notifications/appointments', $this->validData(['appointment_mode' => 'online', 'meeting_url' => 'not a link']))
            ->assertSessionHasErrors('meeting_url');
        $this->actingAs($user)->post('/notifications/appointments', $this->validData(['latitude' => '120']))
            ->assertSessionHasErrors('latitude');
    }

    public function test_only_the_chosen_modes_details_are_kept(): void
    {
        $user = User::factory()->create();
        $reminder = $this->appointmentWith($user);

        $this->actingAs($user)->put("/notifications/appointments/{$reminder->id}", $this->validData([
            'appointment_mode' => 'online',
            'meeting_url' => 'https://meet.example.com/abc',
            'phone' => '123',
        ]))->assertSessionHasNoErrors();

        $reminder->refresh();
        $this->assertSame(AppointmentMode::Online, $reminder->appointment_mode);
        $this->assertSame('https://meet.example.com/abc', $reminder->meeting_url);
        $this->assertNull($reminder->address);
        $this->assertNull($reminder->latitude);
        $this->assertNull($reminder->phone);
    }

    public function test_people_are_required_limited_and_validated(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);

        $this->actingAs($user)->post('/notifications/appointments', $this->validData(['participants' => [['name' => '', 'email' => '']]]))
            ->assertSessionHasErrors(['participants' => 'Add at least one person you are meeting.']);

        $tooMany = array_map(fn ($i) => ['name' => "P$i", 'email' => "p$i@example.com"], range(1, Reminder::MAX_PARTICIPANTS + 1));
        $this->actingAs($user)->post('/notifications/appointments', $this->validData(['participants' => $tooMany]))
            ->assertSessionHasErrors('participants');

        $this->actingAs($user)->post('/notifications/appointments', $this->validData(['participants' => [
            ['name' => '', 'email' => 'a@example.com'],
            ['name' => 'Me', 'email' => 'ME@example.com'],
            ['name' => 'B', 'email' => 'a@example.com'],
            ['name' => 'C', 'email' => 'not-an-email', 'phone' => str_repeat('1', 51)],
        ]]))->assertSessionHasErrors(['participants.0.name', 'participants.1.email', 'participants.2.email', 'participants.3.email', 'participants.3.phone']);

        $this->assertSame(0, $user->reminders()->count());
    }

    public function test_updating_replaces_the_schedule_and_people(): void
    {
        $user = User::factory()->create();
        $reminder = $this->appointmentWith($user);

        $this->actingAs($user)->put("/notifications/appointments/{$reminder->id}", $this->validData([
            'reminder_dates' => [],
            'participants' => [['name' => 'Eleni', 'email' => 'eleni@example.com']],
        ]))->assertSessionHasNoErrors()->assertRedirect('/notifications');

        $reminder->refresh();
        $this->assertCount(1, $reminder->dates);
        $this->assertSame(['Eleni'], $reminder->participants->pluck('name')->all());
    }

    public function test_other_users_and_other_kinds_cannot_use_the_appointment_routes(): void
    {
        $owner = User::factory()->create();
        $reminder = $this->appointmentWith($owner);
        $standard = Reminder::factory()->for($owner)->create();

        $this->actingAs(User::factory()->create())->get("/notifications/appointments/{$reminder->id}/edit")->assertForbidden();
        $this->actingAs($owner)->get("/notifications/appointments/{$standard->id}/edit")->assertNotFound();

        $this->actingAs($owner)->get("/notifications/{$reminder->id}/edit")
            ->assertRedirect("/notifications/appointments/{$reminder->id}/edit");
        $this->actingAs($owner)->put("/notifications/{$reminder->id}", [
            'title' => 'x', 'final_at' => '2026-10-20T18:30', 'channel' => 'email', 'email_theme' => 'light',
        ])->assertNotFound();
    }

    public function test_due_dates_are_emailed_to_the_owner_and_each_person_with_an_email(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $reminder = $this->appointmentWith($owner);
        $reminder->syncParticipants([
            ['name' => 'Maria', 'email' => 'maria@example.com', 'phone' => null],
            ['name' => 'Nikos', 'email' => 'nikos@example.com', 'phone' => '6900000002'],
            ['name' => 'Eleni', 'email' => null, 'phone' => '6900000003'],
        ]);
        $this->travel(2)->days();

        $this->artisan('reminders:send')->assertSuccessful();

        Notification::assertSentTo($owner, ReminderDue::class);
        foreach (['maria@example.com', 'nikos@example.com'] as $email) {
            Notification::assertSentOnDemand(ReminderDue::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === $email);
        }
        Notification::assertSentOnDemandTimes(ReminderDue::class, 2);
    }

    public function test_the_email_shows_where_and_with_whom(): void
    {
        $owner = User::factory()->create(['name' => 'Thodoris']);
        $reminder = $this->appointmentWith($owner, ['title' => 'Coffee']);

        $mail = (new ReminderDue($reminder->finalDate))->toMail(Notification::route('mail', 'maria@example.com'));

        $this->assertSame('Appointment: Coffee', $mail->subject);
        $this->assertSame('Hello Maria,', $mail->greeting);
        $this->assertContains('Where: Ermou 10, Athens', $mail->introLines);
        $this->assertContains('With: Thodoris', $mail->introLines);
        $this->assertSame('Open map', $mail->actionText);
        $this->assertStringContainsString('openstreetmap.org/?mlat=37.9755&mlon=23.7348', $mail->actionUrl);

        $ownerMail = (new ReminderDue($reminder->finalDate))->toMail($owner);
        $this->assertContains('With: Maria (6900000001)', $ownerMail->introLines);
        $this->assertSame('Open map', $ownerMail->actionText);
        $this->assertStringContainsString('View your notifications', implode(' ', $ownerMail->outroLines));
    }

    public function test_phone_and_online_emails_show_their_details(): void
    {
        $owner = User::factory()->create();
        $phone = $this->appointmentWith($owner, ['appointment_mode' => AppointmentMode::Phone, 'address' => null, 'phone' => '+30 210 1234567']);
        $online = $this->appointmentWith($owner, ['appointment_mode' => AppointmentMode::Online, 'address' => null, 'meeting_url' => 'https://meet.example.com/abc']);

        $phoneMail = (new ReminderDue($phone->finalDate))->toMail($owner);
        $this->assertContains('How: Phone call', $phoneMail->introLines);
        $this->assertContains('Phone: +30 210 1234567', $phoneMail->introLines);
        $this->assertSame('View your notifications', $phoneMail->actionText);

        $onlineMail = (new ReminderDue($online->finalDate))->toMail(Notification::route('mail', 'maria@example.com'));
        $this->assertSame('Join meeting', $onlineMail->actionText);
        $this->assertSame('https://meet.example.com/abc', $onlineMail->actionUrl);
        $this->assertStringContainsString('Join meeting', (string) $onlineMail->render());
    }
}
