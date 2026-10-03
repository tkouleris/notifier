<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get('/register')->assertOk()->assertSee('images/logo-full.png');
    }

    public function test_new_user_is_registered_and_sent_a_verification_email(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticated();

        $user = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertSame('UTC', $user->timezone);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_new_users_start_with_the_browser_timezone(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'timezone' => 'Europe/Athens',
        ]);

        $this->assertSame('Europe/Athens', User::where('email', 'test@example.com')->value('timezone'));
    }

    public function test_user_sees_verification_page_when_verification_email_fails(): void
    {
        Notification::shouldReceive('send')->andThrow(new TransportException('Mailgun unavailable'));

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHas('status', 'verification-link-failed');
        $this->assertAuthenticated();
    }

    public function test_registration_requires_unique_email_and_confirmed_password(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'taken@example.com',
            'password' => 'password',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }
}
