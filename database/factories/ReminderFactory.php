<?php

namespace Database\Factories;

use App\Enums\ReminderChannel;
use App\Enums\ReminderStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reminder>
 */
class ReminderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'notify_at' => now()->addDay(),
            'timezone' => 'UTC',
            'channel' => ReminderChannel::Email,
            'status' => ReminderStatus::Pending,
        ];
    }

    public function due(): static
    {
        return $this->state(fn (array $attributes) => [
            'notify_at' => now()->subMinute(),
        ]);
    }
}
