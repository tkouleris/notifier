<?php

namespace Tests\Feature;

use App\Enums\ReminderStatus;
use App\Models\Reminder;
use App\Models\User;
use App\Notifications\ReminderDue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/profile')->assertRedirect('/login');
        $this->get('/settings')->assertRedirect('/login');
    }

    public function test_the_navbar_links_to_profile_and_settings(): void
    {
        $user = User::factory()->create(['name' => 'Thodoris']);

        $this->actingAs($user)->get('/notifications')
            ->assertSee('href="'.route('profile.edit').'"', false)
            ->assertSee('href="'.route('settings.edit').'"', false)
            ->assertSee('images/logo-mark.png')
            ->assertSee('Thodoris')
            ->assertSee('Log out');
    }

    public function test_the_profile_page_can_be_rendered(): void
    {
        $user = User::factory()->create(['birthday' => '1990-04-12']);

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee('value="'.$user->name.'"', false)
            ->assertSee('value="1990-04-12"', false);
    }

    public function test_the_username_and_birthday_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put('/profile', ['name' => 'New Name', 'birthday' => '1990-04-12'])
            ->assertRedirect('/profile')
            ->assertSessionHas('status', 'Profile updated.');

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('1990-04-12', $user->birthday->format('Y-m-d'));
    }

    public function test_the_birthday_is_optional_and_can_be_cleared(): void
    {
        $user = User::factory()->create(['birthday' => '1990-04-12']);

        $this->actingAs($user)->put('/profile', ['name' => $user->name, 'birthday' => ''])->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->birthday);
    }

    public function test_invalid_profile_data_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put('/profile', ['name' => '', 'birthday' => now()->addDay()->format('Y-m-d')])
            ->assertSessionHasErrors(['name', 'birthday']);
    }

    public function test_the_password_can_be_changed_with_the_current_one(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put('/profile/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect('/profile')
            ->assertSessionHas('status', 'Password changed.');

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_the_password_is_not_changed_with_a_wrong_current_one(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put('/profile/password', [
                'current_password' => 'wrong',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasErrorsIn('password', 'current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_the_settings_page_shows_the_current_timezone(): void
    {
        $user = User::factory()->create(['timezone' => 'Europe/Athens']);

        $this->actingAs($user)->get('/settings')
            ->assertOk()
            ->assertSee('<option value="Europe/Athens" selected', false);
    }

    public function test_the_timezone_can_be_changed_and_is_used_for_new_notifications(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put('/settings', ['timezone' => 'America/New_York'])
            ->assertRedirect('/settings');

        $this->assertSame('America/New_York', $user->fresh()->timezone);

        $local = now('America/New_York')->addDay()->setTime(9, 0);
        $this->actingAs($user->fresh())->post('/notifications', [
            'title' => 'Call',
            'final_at' => $local->format('Y-m-d\TH:i'),
            'channel' => 'email',
            'email_theme' => 'light',
        ]);

        $this->assertTrue($user->reminders()->sole()->finalDate->notify_at->eq($local->copy()->utc()));
    }

    public function test_changing_the_timezone_keeps_pending_dates_at_the_same_clock_time(): void
    {
        $user = User::factory()->create(['timezone' => 'Europe/Athens']);
        $reminder = Reminder::factory()->for($user)->create(['timezone' => 'Europe/Athens']);
        // 09:30 in Athens (UTC+3 in summer).
        $reminder->syncDates(Carbon::parse('2030-06-14 09:30', 'Europe/Athens')->utc(), []);
        $sent = $reminder->dates()->create([
            'notify_at' => now()->subDay()->second(0),
            'status' => ReminderStatus::Sent,
        ]);
        $other = Reminder::factory()->create();
        $otherAt = $other->finalDate->notify_at;

        $this->actingAs($user)->put('/settings', ['timezone' => 'America/New_York'])->assertRedirect('/settings');

        $final = $reminder->finalDate()->first();
        $this->assertTrue($final->notify_at->eq(Carbon::parse('2030-06-14 09:30', 'America/New_York')));
        $this->assertSame('America/New_York', $reminder->fresh()->timezone);
        $this->assertTrue($sent->fresh()->notify_at->eq($sent->notify_at));
        $this->assertTrue($other->finalDate()->first()->notify_at->eq($otherAt));
    }

    public function test_reminders_go_out_at_the_clock_time_of_the_users_timezone(): void
    {
        Notification::fake();

        $user = User::factory()->create(['timezone' => 'Europe/Athens']);
        $reminder = Reminder::factory()->for($user)->create();
        $reminder->syncDates(Carbon::parse('2030-06-14 09:30', 'Europe/Athens')->utc(), []);
        $user->changeTimezone('Asia/Tokyo');

        $this->travelTo(Carbon::parse('2030-06-14 09:29', 'Asia/Tokyo'));
        $this->artisan('reminders:send');
        Notification::assertNothingSent();

        $this->travelTo(Carbon::parse('2030-06-14 09:30', 'Asia/Tokyo'));
        $this->artisan('reminders:send');
        Notification::assertSentTo($user, ReminderDue::class);
    }

    public function test_unknown_timezones_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/settings', ['timezone' => 'Mars/Olympus'])->assertSessionHasErrors('timezone');

        $this->assertSame('UTC', $user->fresh()->timezone);
    }
}
