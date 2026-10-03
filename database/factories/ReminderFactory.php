<?php

namespace Database\Factories;

use App\Enums\ReminderChannel;
use App\Models\Reminder;
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
            'timezone' => 'UTC',
            'channel' => ReminderChannel::Email,
        ];
    }

    /**
     * Every reminder needs a final date; give it one tomorrow unless the test built its own.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Reminder $reminder) {
            if ($reminder->dates()->doesntExist()) {
                $reminder->dates()->create(['notify_at' => now()->addDay(), 'is_final' => true]);
            }
        });
    }
}
