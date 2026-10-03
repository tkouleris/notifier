<?php

namespace App\Models;

use App\Enums\ReminderChannel;
use App\Enums\ReminderStatus;
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

    protected $fillable = [
        'title',
        'message',
        'timezone',
        'channel',
    ];

    protected $attributes = [
        'channel' => 'email',
        'timezone' => 'UTC',
    ];

    protected $casts = [
        'channel' => ReminderChannel::class,
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
}
