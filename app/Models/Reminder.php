<?php

namespace App\Models;

use App\Enums\AppointmentMode;
use App\Enums\BirthdayLayout;
use App\Enums\OffsetUnit;
use App\Enums\ReminderChannel;
use App\Enums\ReminderStatus;
use App\Enums\ReminderType;
use App\Enums\Repeat;
use App\Enums\Theme;
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

    /**
     * The most people an appointment can be with besides its owner.
     */
    public const MAX_PARTICIPANTS = 5;

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
        'appointment_mode',
        'address',
        'latitude',
        'longitude',
        'phone',
        'meeting_url',
        'timezone',
        'channel',
        'email_theme',
        'repeat',
        'repeat_anchor',
        'reminder_offsets',
    ];

    protected $attributes = [
        'type' => 'standard',
        'channel' => 'email',
        'email_theme' => 'light',
        'timezone' => 'UTC',
    ];

    protected $casts = [
        'type' => ReminderType::class,
        'channel' => ReminderChannel::class,
        'email_theme' => Theme::class,
        'birth_day' => 'integer',
        'birth_month' => 'integer',
        'birth_year' => 'integer',
        'layout' => BirthdayLayout::class,
        'appointment_mode' => AppointmentMode::class,
        'latitude' => 'float',
        'longitude' => 'float',
        'repeat' => Repeat::class,
        'reminder_offsets' => 'array',
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
     * The people an appointment is with.
     */
    public function participants(): HasMany
    {
        return $this->hasMany(AppointmentParticipant::class);
    }

    /**
     * Every date this reminder is sent on, earliest first; the last one is the final date.
     */
    public function dates(): HasMany
    {
        return $this->hasMany(ReminderDate::class)->orderBy('notify_at');
    }

    /**
     * The latest final date: birthdays and repeating notifications also keep the last one sent.
     */
    public function finalDate(): HasOne
    {
        return $this->hasOne(ReminderDate::class)->ofMany(['notify_at' => 'max'], fn ($query) => $query->where('is_final', true));
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
     * Replace an appointment's people.
     *
     * @param  array<int, array{name: string, email: ?string, phone: ?string}>  $people
     */
    public function syncParticipants(array $people): void
    {
        $this->participants()->delete();
        $this->participants()->createMany(array_map(fn (array $person) => [
            'name' => $person['name'],
            'email' => $person['email'],
            'phone' => $person['phone'],
        ], $people));

        $this->unsetRelation('participants');
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

    public function isAppointment(): bool
    {
        return $this->type === ReminderType::Appointment;
    }

    public function repeats(): bool
    {
        return $this->repeat !== null;
    }

    /**
     * The first occurrence of a repeating notification after the given time, in UTC.
     */
    public function occurrenceAfter(Carbon $after): Carbon
    {
        $first = Carbon::createFromFormat('Y-m-d H:i', $this->repeat_anchor, $this->timezone)->second(0);
        $n = 0;

        do {
            $date = $this->repeat->occurrence($first, $n++)->utc();
        } while (! $date->gt($after));

        return $date;
    }

    /**
     * A repeating notification's reminders before the given occurrence, in UTC, earliest
     * first; any already in the past are left out.
     *
     * @return array<int, Carbon>
     */
    public function offsetDates(Carbon $final): array
    {
        $local = $final->copy()->setTimezone($this->timezone);

        return collect($this->reminder_offsets ?? [])
            ->map(fn (array $offset) => OffsetUnit::from($offset['unit'])->subtractFrom($local, $offset['amount'])->utc())
            ->filter->isFuture()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * After a repeating notification's final date goes out, schedule the next occurrence
     * and its reminders, keeping only the final date just sent as history. Occurrences
     * missed while nothing was sending are skipped, so they don't all go out at once.
     */
    public function scheduleNextOccurrence(ReminderDate $sent): void
    {
        $this->deleteHistoryExcept($sent);

        $next = $this->occurrenceAfter($sent->notify_at->max(now()));
        $this->dates()->createMany([
            ...array_map(fn (Carbon $date) => ['notify_at' => $date], $this->offsetDates($next)),
            ['notify_at' => $next, 'is_final' => true],
        ]);

        $this->unsetRelation('dates')->unsetRelation('finalDate');
    }

    /**
     * An OpenStreetMap link to an in-person appointment's place: the point picked
     * on the map, or else a search for the typed address.
     */
    public function mapUrl(): ?string
    {
        if ($this->latitude !== null && $this->longitude !== null) {
            return 'https://www.openstreetmap.org/?'.http_build_query([
                'mlat' => $this->latitude,
                'mlon' => $this->longitude,
            ]).'#map=17/'.$this->latitude.'/'.$this->longitude;
        }

        return $this->address
            ? 'https://www.openstreetmap.org/search?'.http_build_query(['query' => $this->address])
            : null;
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
     * keeping only the card just sent and the next one; a card sent late never schedules
     * one that is already past.
     */
    public function scheduleNextBirthday(ReminderDate $sent): void
    {
        $this->deleteHistoryExcept($sent);
        $this->dates()->create(['notify_at' => $this->nextBirthday($sent->notify_at->max(now())), 'is_final' => true]);

        $this->unsetRelation('dates')->unsetRelation('finalDate');
    }

    private function deleteHistoryExcept(ReminderDate $sent): void
    {
        $this->dates()->whereKeyNot($sent->getKey())->where('status', '!=', ReminderStatus::Pending)->delete();
    }

    private function birthdayIn(int $year): Carbon
    {
        $date = Carbon::create($year, $this->birth_month, 1, self::BIRTHDAY_SEND_HOUR, 0, 0, $this->timezone);

        return $date->day(min($this->birth_day, $date->daysInMonth))->utc();
    }
}
