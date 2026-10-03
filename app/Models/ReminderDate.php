<?php

namespace App\Models;

use App\Enums\ReminderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One moment a reminder is sent: either its final date or one of the earlier reminders.
 */
class ReminderDate extends Model
{
    use HasFactory;

    protected $fillable = [
        'notify_at',
        'is_final',
        'status',
        'sent_at',
    ];

    protected $attributes = [
        'is_final' => false,
        'status' => 'pending',
    ];

    protected $casts = [
        'notify_at' => 'datetime',
        'is_final' => 'boolean',
        'status' => ReminderStatus::class,
        'sent_at' => 'datetime',
    ];

    public function reminder(): BelongsTo
    {
        return $this->belongsTo(Reminder::class);
    }

    /**
     * Pending dates whose time has come.
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where('status', ReminderStatus::Pending)->where('notify_at', '<=', now());
    }
}
