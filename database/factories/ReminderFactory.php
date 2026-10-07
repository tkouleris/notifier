<?php

namespace Database\Factories;

use App\Enums\ReminderChannel;
use App\Enums\ReminderType;
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

    public function birthday(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ReminderType::Birthday,
            'title' => fake()->firstName(),
            'message' => null,
            'email' => fake()->safeEmail(),
            'birth_day' => 15,
            'birth_month' => 3,
            'birth_year' => null,
        ]);
    }

    /**
     * Every reminder needs a final date; give it one tomorrow (or on the next birthday)
     * unless the test built its own.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Reminder $reminder) {
            if ($reminder->dates()->doesntExist()) {
                $notifyAt = $reminder->isBirthday() ? $reminder->nextBirthday() : now()->addDay();
                $reminder->dates()->create(['notify_at' => $notifyAt, 'is_final' => true]);
            }
        });
    }
}
