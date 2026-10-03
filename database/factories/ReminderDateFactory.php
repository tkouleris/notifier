<?php

namespace Database\Factories;

use App\Enums\ReminderStatus;
use App\Models\Reminder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReminderDate>
 */
class ReminderDateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reminder_id' => Reminder::factory(),
            'notify_at' => now()->addHours(12),
            'is_final' => false,
            'status' => ReminderStatus::Pending,
        ];
    }

    public function final(): static
    {
        return $this->state(fn (array $attributes) => [
            'notify_at' => now()->addDay(),
            'is_final' => true,
        ]);
    }

    public function due(): static
    {
        return $this->state(fn (array $attributes) => [
            'notify_at' => now()->subMinute(),
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReminderStatus::Sent,
            'sent_at' => now(),
        ]);
    }
}
