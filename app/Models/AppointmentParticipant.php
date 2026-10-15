<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone an appointment is with; emailed on its dates only when they have an email.
 */
class AppointmentParticipant extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
    ];

    public function reminder(): BelongsTo
    {
        return $this->belongsTo(Reminder::class);
    }
}
