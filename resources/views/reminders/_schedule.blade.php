{{-- The final date and the optional reminders before it, entered in the user's timezone. --}}
@php
    $inputFormat = 'Y-m-d\TH:i';
    $finalDate = $reminder->dates->firstWhere('is_final', true);
    // Times are entered and shown in the timezone from the user's settings.
    $user = auth()->user();
    $finalValue = old('final_at', $finalDate ? $user->toLocal($finalDate->notify_at)->format($inputFormat) : '');
    // Reminders already sent are in the past, so only the ones still to come are editable.
    $reminderValues = old('reminder_dates', $reminder->earlyDates()
        ->where('status', \App\Enums\ReminderStatus::Pending)
        ->map(fn ($date) => $user->toLocal($date->notify_at)->format($inputFormat))
        ->values()
        ->all());
@endphp

<label for="final_at">{{ $finalLabel ?? 'Final date' }}</label>
<input id="final_at" type="datetime-local" name="final_at" required value="{{ $finalValue }}">
<div class="muted">Timezone: {{ $user->timezone }} · <a href="{{ route('settings.edit') }}">Change in settings</a></div>
@error('final_at') <div class="error">{{ $message }}</div> @enderror

@include('reminders._optional-list', [
    'name' => 'reminder_dates',
    'legend' => $remindersLegend ?? 'Reminders before the final date',
    'max' => \App\Models\Reminder::MAX_EARLY_DATES,
    'values' => $reminderValues,
    'type' => 'datetime-local',
    'itemLabel' => 'Reminder date',
    'addLabel' => 'Add reminder',
])
