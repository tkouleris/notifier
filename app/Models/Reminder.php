<?php

namespace App\Models;

use App\Enums\BirthdayLayout;
use App\Enums\ReminderChannel;
use App\Enums\ReminderStatus;
use App\Enums\ReminderType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Reminder extends Model
{
    use HasFactory;

    /**
     * The most people a reminder can notify besides its owner.
     */
    public const MAX_RECIPIENTS = 3;

    /**
     * The most optional reminders that can come before the final date.
     */
    public const MAX_EARLY_DATES = 4;

    /**
     * Birthday cards go out at this hour, local to the reminder's timezone.
     */
    public const BIRTHDAY_SEND_HOUR = 9;

    protected $fillable = [
        'type',
        'title',
        'message',
        'email',
        'sender_name',
        'birth_day',
        'birth_month',
        'birth_year',
        'layout',
        'timezone',
        'channel',
    ];

    protected $attributes = [
        'type' => 'standard',
        'channel' => 'email',
        'timezone' => 'UTC',
    ];

    protected $casts = [
        'type' => ReminderType::class,
        'channel' => ReminderChannel::class,
        'birth_day' => 'integer',
        'birth_month' => 'integer',
        'birth_year' => 'integer',
        'layout' => BirthdayLayout::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(ReminderRecipient::class);
    }

    /**
     * Every date this reminder is sent on, earliest first; the last one is the final date.
     */
    public function dates(): HasMany
    {
        return $this->hasMany(ReminderDate::class)->orderBy('notify_at');
    }

    public function finalDate(): HasOne
    {
        return $this->hasOne(ReminderDate::class)->where('is_final', true);
    }

    /**
     * The optional reminders sent before the final date.
     *
     * @return Collection<int, ReminderDate>
     */
    public function earlyDates(): Collection
    {
        return $this->dates->reject->is_final->values();
    }

    /**
     * Overall state across all dates: any failure shows first, then anything still to send.
     */
    public function status(): ReminderStatus
    {
        $statuses = $this->dates->pluck('status');

        return match (true) {
            $statuses->contains(ReminderStatus::Failed) => ReminderStatus::Failed,
            $statuses->contains(ReminderStatus::Pending) => ReminderStatus::Pending,
            default => ReminderStatus::Sent,
        };
    }

    /**
     * Replace the extra recipients with the given email addresses.
     *
     * @param  array<int, string>  $emails
     */
    public function syncRecipients(array $emails): void
    {
        $this->recipients()->delete();
        $this->recipients()->createMany(array_map(fn (string $email) => ['email' => $email], $emails));
    }

    /**
     * Replace every date with a fresh, pending schedule.
     *
     * @param  array<int, Carbon>  $earlyDates
     */
    public function syncDates(Carbon $finalDate, array $earlyDates): void
    {
        $this->dates()->delete();

        $this->dates()->createMany([
            ...array_map(fn (Carbon $date) => ['notify_at' => $date], $earlyDates),
            ['notify_at' => $finalDate, 'is_final' => true],
        ]);

        $this->unsetRelation('dates')->unsetRelation('finalDate');
    }

    public function isBirthday(): bool
    {
        return $this->type === ReminderType::Birthday;
    }

    /**
     * The first birthday card moment after the given time, in UTC. A February 29
     * birthday falls on February 28 in other years.
     */
    public function nextBirthday(?Carbon $after = null): Carbon
    {
        $after ??= now();
        $year = $after->copy()->setTimezone($this->timezone)->year;

        do {
            $date = $this->birthdayIn($year++);
        } while (! $date->gt($after));

        return $date;
    }

    /**
     * How old the person turns on the given date, when their birth year is known.
     */
    public function ageOn(Carbon $date): ?int
    {
        if (! $this->birth_year) {
            return null;
        }

        $age = $date->copy()->setTimezone($this->timezone)->year - $this->birth_year;

        return $age > 0 ? $age : null;
    }

    /**
     * After a birthday card goes out, schedule next year's and drop older history,
     * keeping only the card just sent and the next one.
     */
    public function scheduleNextBirthday(ReminderDate $sent): void
    {
        $this->dates()->whereKeyNot($sent->getKey())->where('status', '!=', ReminderStatus::Pending)->delete();
        $this->dates()->create(['notify_at' => $this->nextBirthday($sent->notify_at), 'is_final' => true]);

        $this->unsetRelation('dates')->unsetRelation('finalDate');
    }

    private function birthdayIn(int $year): Carbon
    {
        $date = Carbon::create($year, $this->birth_month, 1, self::BIRTHDAY_SEND_HOUR, 0, 0, $this->timezone);

        return $date->day(min($this->birth_day, $date->daysInMonth))->utc();
    }
}
