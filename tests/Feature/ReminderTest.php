<?php

namespace Tests\Feature;

use App\Enums\ReminderStatus;
use App\Models\Reminder;
use App\Models\User;
use App\Notifications\ReminderDue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    private function validData(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Team dinner',
            'notify_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'timezone' => 'UTC',
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

    public function test_create_and_edit_forms_can_be_rendered(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->for($user)->create([
            'notify_at' => '2030-05-01 06:30:00',
            'timezone' => 'Europe/Athens',
        ]);

        $this->actingAs($user)->get('/notifications/create')->assertOk()->assertSee('New notification');
        $this->actingAs($user)->get("/notifications/{$reminder->id}/edit")
            ->assertOk()
            ->assertSee('value="2030-05-01T09:30"', false);
    }

    public function test_a_notification_can_be_created_in_the_users_timezone(): void
    {
        $user = User::factory()->create();
        $local = now('Europe/Athens')->addDay()->setTime(9, 30);

        $this->actingAs($user)->post('/notifications', [
            'title' => 'Dentist',
            'message' => 'Bring the papers',
            'notify_at' => $local->format('Y-m-d\TH:i'),
            'timezone' => 'Europe/Athens',
            'channel' => 'email',
        ])->assertRedirect('/notifications');

        $reminder = $user->reminders()->sole();
        $this->assertSame('Dentist', $reminder->title);
        $this->assertTrue($reminder->notify_at->eq($local->copy()->utc()));
        $this->assertSame(ReminderStatus::Pending, $reminder->status);
    }

    public function test_a_notification_must_be_in_the_future(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/notifications', [
            'title' => 'Too late',
            'notify_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'timezone' => 'UTC',
            'channel' => 'email',
        ])->assertSessionHasErrors('notify_at');

        $this->assertDatabaseCount('reminders', 0);
    }

    public function test_unknown_channels_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/notifications', [
            'title' => 'Pigeon',
            'notify_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'timezone' => 'UTC',
            'channel' => 'pigeon',
        ])->assertSessionHasErrors('channel');
    }

    public function test_updating_a_sent_notification_schedules_it_again(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->for($user)->create([
            'status' => ReminderStatus::Sent,
            'sent_at' => now(),
        ]);

        $this->actingAs($user)->put("/notifications/{$reminder->id}", [
            'title' => 'Renamed',
            'notify_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
            'timezone' => 'UTC',
            'channel' => 'email',
        ])->assertRedirect('/notifications');

        $reminder->refresh();
        $this->assertSame('Renamed', $reminder->title);
        $this->assertSame(ReminderStatus::Pending, $reminder->status);
        $this->assertNull($reminder->sent_at);
    }

    public function test_a_notification_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->for($user)->create();

        $this->actingAs($user)->delete("/notifications/{$reminder->id}")->assertRedirect('/notifications');

        $this->assertModelMissing($reminder);
    }

    public function test_users_cannot_touch_other_users_notifications(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->create();

        $this->actingAs($user)->get("/notifications/{$reminder->id}/edit")->assertForbidden();
        $this->actingAs($user)->put("/notifications/{$reminder->id}", [
            'title' => 'Hijacked',
            'notify_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'timezone' => 'UTC',
            'channel' => 'email',
        ])->assertForbidden();
        $this->actingAs($user)->delete("/notifications/{$reminder->id}")->assertForbidden();

        $this->assertModelExists($reminder);
    }

    public function test_due_notifications_are_emailed_and_marked_sent(): void
    {
        Notification::fake();

        $due = Reminder::factory()->due()->create();
        $future = Reminder::factory()->create();

        $this->artisan('reminders:send')->assertSuccessful();

        Notification::assertSentTo($due->user, ReminderDue::class, function (ReminderDue $notification, array $channels) use ($due) {
            return $notification->reminder->is($due) && $channels === ['mail'];
        });
        Notification::assertNotSentTo($future->user, ReminderDue::class);

        $this->assertSame(ReminderStatus::Sent, $due->fresh()->status);
        $this->assertNotNull($due->fresh()->sent_at);
        $this->assertSame(ReminderStatus::Pending, $future->fresh()->status);
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

    public function test_deleting_a_notification_deletes_its_recipients(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->for($user)->create();
        $reminder->syncRecipients(['a@example.com']);

        $this->actingAs($user)->delete("/notifications/{$reminder->id}");

        $this->assertDatabaseCount('reminder_recipients', 0);
    }

    public function test_due_notifications_are_emailed_to_the_other_people_too(): void
    {
        Notification::fake();

        $reminder = Reminder::factory()->due()->create();
        $reminder->syncRecipients(['a@example.com', 'b@example.com']);

        $this->artisan('reminders:send')->assertSuccessful();

        Notification::assertSentTo($reminder->user, ReminderDue::class);
        foreach (['a@example.com', 'b@example.com'] as $email) {
            Notification::assertSentOnDemand(ReminderDue::class, function ($notification, $channels, $notifiable) use ($email) {
                return $notifiable->routes['mail'] === $email;
            });
        }
        Notification::assertSentOnDemandTimes(ReminderDue::class, 2);
        $this->assertSame(ReminderStatus::Sent, $reminder->fresh()->status);
    }

    public function test_the_email_tells_other_people_who_asked_for_the_reminder(): void
    {
        $owner = User::factory()->create(['name' => 'Thodoris']);
        $reminder = Reminder::factory()->for($owner)->create(['title' => 'Team dinner']);

        $ownerMail = (new ReminderDue($reminder))->toMail($owner);
        $otherMail = (new ReminderDue($reminder))->toMail(Notification::route('mail', 'a@example.com'));

        $this->assertSame('Hello Thodoris,', $ownerMail->greeting);
        $this->assertNotNull($ownerMail->actionUrl);
        $this->assertSame('Hello,', $otherMail->greeting);
        $this->assertContains('Thodoris asked us to remind you about this.', $otherMail->introLines);
        $this->assertNull($otherMail->actionUrl);
    }

    public function test_sent_notifications_are_not_sent_twice(): void
    {
        Notification::fake();

        Reminder::factory()->due()->create();

        $this->artisan('reminders:send');
        $this->artisan('reminders:send');

        Notification::assertSentTimes(ReminderDue::class, 1);
    }
}
