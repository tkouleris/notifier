<?php

namespace App\Models;

use App\Enums\ReminderStatus;
use App\Enums\Theme;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'theme',
        'timezone',
        'birthday',
    ];

    /**
     * Mirrors the column defaults so new, unrefreshed users have them too.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'theme' => 'light',
        'timezone' => 'UTC',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'theme' => Theme::class,
        'birthday' => 'date',
    ];

    /**
     * Switch to another timezone, keeping every pending date at the same clock time:
     * 09:30 in the old timezone becomes 09:30 in the new one.
     */
    public function changeTimezone(string $timezone): void
    {
        if ($timezone === $this->timezone) {
            return;
        }

        DB::transaction(function () use ($timezone) {
            ReminderDate::query()
                ->where('status', ReminderStatus::Pending)
                ->whereIn('reminder_id', $this->reminders()->select('id'))
                ->each(function (ReminderDate $date) use ($timezone) {
                    $local = $this->toLocal($date->notify_at)->format('Y-m-d H:i:s');

                    $date->update(['notify_at' => Carbon::createFromFormat('Y-m-d H:i:s', $local, $timezone)->utc()]);
                });

            $this->reminders()->update(['timezone' => $timezone]);
            $this->update(['timezone' => $timezone]);
        });
    }

    /**
     * A stored (UTC) time in the timezone picked in settings.
     */
    public function toLocal(Carbon $time): Carbon
    {
        return $time->copy()->setTimezone($this->timezone);
    }

    /**
     * Named "reminders" because Notifiable already defines notifications().
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }
}
