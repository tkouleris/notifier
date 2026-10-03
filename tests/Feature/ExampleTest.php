<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_what_the_app_does_and_how_to_sign_up(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Never miss the dates that matter.')
            ->assertSee('How it works')
            ->assertSee('images/logo-full.png')
            ->assertSee('images/logo-mark.png')
            ->assertSee('href="'.route('register').'"', false)
            ->assertSee('href="'.route('login').'"', false);
    }

    public function test_logged_in_users_are_pointed_to_their_notifications(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSee('Go to my notifications')
            ->assertDontSee('Create a free account');
    }
}
