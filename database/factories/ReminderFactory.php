<?php

namespace Database\Factories;

use App\Enums\AppointmentMode;
use App\Enums\BirthdayLayout;
use App\Enums\ReminderChannel;
use App\Enums\ReminderType;
use App\Enums\Repeat;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reminder>
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
            'layout' => BirthdayLayout::Balloons,
        ]);
    }

    public function appointment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ReminderType::Appointment,
            'appointment_mode' => AppointmentMode::InPerson,
            'address' => 'Ermou 10, Athens',
            'latitude' => 37.9755,
            'longitude' => 23.7348,
        ]);
    }

    /**
     * Repeats from the given local time, with reminders like ['amount' => 2, 'unit' => 'day'].
     */
    public function repeating(Repeat $repeat, string $firstAt, array $offsets = []): static
    {
        return $this->state(fn (array $attributes) => [
            'repeat' => $repeat,
            'repeat_anchor' => $firstAt,
            'reminder_offsets' => $offsets,
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
