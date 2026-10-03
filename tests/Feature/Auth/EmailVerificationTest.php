<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_cannot_access_home(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/home')->assertRedirect(route('verification.notice'));
    }

    public function test_unverified_user_is_sent_to_verification_page_after_login(): void
    {
        $user = User::factory()->unverified()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/home');

        $this->get('/home')->assertRedirect(route('verification.notice'));
        $this->get(route('verification.notice'))->assertOk()->assertSee('needs to be verified');
    }

    public function test_verified_user_can_access_home(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/home')->assertOk()->assertSee($user->name);
    }

    public function test_verified_user_is_redirected_away_from_verification_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('verification.notice'))->assertRedirect('/home');
    }

    public function test_verification_email_failure_is_shown_on_resend(): void
    {
        $user = User::factory()->unverified()->create();
        Notification::shouldReceive('send')->andThrow(new TransportException('Mailgun unavailable'));

        $this->actingAs($user)
            ->from('/email/verify')
            ->post('/email/verification-notification')
            ->assertRedirect('/email/verify')
            ->assertSessionHas('status', 'verification-link-failed');
    }

    public function test_verification_notice_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/email/verify')->assertOk();
    }

    public function test_email_can_be_verified_with_signed_link(): void
    {
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get($this->verificationUrl($user, sha1($user->email)));

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect('/home?verified=1');
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get($this->verificationUrl($user, sha1('wrong@example.com')))
            ->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_is_not_verified_with_unsigned_link(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get("/email/verify/{$user->id}/".sha1($user->email))
            ->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_email_can_be_resent(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->from('/email/verify')
            ->post('/email/verification-notification')
            ->assertRedirect('/email/verify')
            ->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    private function verificationUrl(User $user, string $hash): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => $hash],
        );
    }
}
