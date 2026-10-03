<?php

namespace App\Models;

use App\Enums\ReminderChannel;
use App\Enums\ReminderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Reminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'message',
        'notify_at',
        'timezone',
        'channel',
        'status',
        'sent_at',
    ];

    protected $attributes = [
        'channel' => 'email',
        'status' => 'pending',
        'timezone' => 'UTC',
    ];

    protected $casts = [
        'notify_at' => 'datetime',
        'sent_at' => 'datetime',
        'channel' => ReminderChannel::class,
        'status' => ReminderStatus::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Pending reminders whose time has come.
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where('status', ReminderStatus::Pending)->where('notify_at', '<=', now());
    }

    /**
     * The notify time in the user's own timezone.
     */
    public function localNotifyAt(): Carbon
    {
        return $this->notify_at->copy()->setTimezone($this->timezone);
    }
}
