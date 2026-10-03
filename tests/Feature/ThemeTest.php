<?php

namespace Tests\Feature;

use App\Enums\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_start_with_the_light_theme(): void
    {
        $user = User::factory()->create();

        $this->assertSame(Theme::Light, $user->fresh()->theme);
        $this->actingAs($user)->get('/notifications')->assertSee('data-theme="light"', false);
    }

    public function test_a_user_can_switch_to_dark_and_it_is_persisted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/notifications/create')
            ->put('/theme', ['theme' => 'dark'])
            ->assertRedirect('/notifications/create');

        $this->assertSame(Theme::Dark, $user->fresh()->theme);
        $this->actingAs($user->fresh())->get('/notifications')->assertSee('data-theme="dark"', false);
    }

    public function test_a_user_can_switch_back_to_light(): void
    {
        $user = User::factory()->create(['theme' => Theme::Dark]);

        $this->actingAs($user)->put('/theme', ['theme' => 'light']);

        $this->assertSame(Theme::Light, $user->fresh()->theme);
    }

    public function test_unknown_themes_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/theme', ['theme' => 'neon'])->assertSessionHasErrors('theme');

        $this->assertSame(Theme::Light, $user->fresh()->theme);
    }

    public function test_guests_cannot_change_the_theme(): void
    {
        $this->put('/theme', ['theme' => 'dark'])->assertRedirect('/login');
    }

    public function test_guests_see_the_light_theme_without_a_switch(): void
    {
        $this->get('/login')
            ->assertSee('data-theme="light"', false)
            ->assertDontSee('Color theme');
    }
}
