@csrf

<label for="title">Title</label>
<input id="title" type="text" name="title" value="{{ old('title', $reminder->title) }}" required maxlength="255" autofocus>
@error('title') <div class="error">{{ $message }}</div> @enderror

<label for="message">Message <span class="muted">(optional)</span></label>
<textarea id="message" name="message" rows="4" maxlength="5000">{{ old('message', $reminder->message) }}</textarea>
@error('message') <div class="error">{{ $message }}</div> @enderror

@php
    $inputFormat = 'Y-m-d\TH:i';
    $finalDate = $reminder->dates->firstWhere('is_final', true);
    $finalValue = old('final_at', $finalDate ? $reminder->toLocal($finalDate->notify_at)->format($inputFormat) : '');
    // Reminders already sent are in the past, so only the ones still to come are editable.
    $reminderValues = old('reminder_dates', $reminder->earlyDates()
        ->where('status', \App\Enums\ReminderStatus::Pending)
        ->map(fn ($date) => $reminder->toLocal($date->notify_at)->format($inputFormat))
        ->values()
        ->all());
@endphp

<label for="final_at">Final date</label>
<input id="final_at" type="datetime-local" name="final_at" required value="{{ $finalValue }}">
<div class="muted">Timezone: <span id="timezone-label">{{ old('timezone', $reminder->exists ? $reminder->timezone : 'UTC') }}</span></div>
@error('final_at') <div class="error">{{ $message }}</div> @enderror
@error('timezone') <div class="error">{{ $message }}</div> @enderror
<input id="timezone" type="hidden" name="timezone" value="{{ old('timezone', $reminder->exists ? $reminder->timezone : 'UTC') }}">

<fieldset class="optional-list">
    <legend>Reminders before the final date <span class="muted">(optional, up to {{ \App\Models\Reminder::MAX_EARLY_DATES }})</span></legend>
    @error('reminder_dates') <div class="error">{{ $message }}</div> @enderror
    @for ($i = 0; $i < \App\Models\Reminder::MAX_EARLY_DATES; $i++)
        <label for="reminder-date-{{ $i }}" class="visually-hidden">Reminder {{ $i + 1 }}</label>
        <input id="reminder-date-{{ $i }}" type="datetime-local" name="reminder_dates[{{ $i }}]" value="{{ $reminderValues[$i] ?? '' }}">
        @error('reminder_dates.'.$i) <div class="error">{{ $message }}</div> @enderror
    @endfor
</fieldset>

<label for="channel">Notify me by</label>
<select id="channel" name="channel" required>
    @foreach (\App\Enums\ReminderChannel::cases() as $channel)
        <option value="{{ $channel->value }}" @selected(old('channel', $reminder->channel?->value) === $channel->value)>
            {{ $channel->label() }}
        </option>
    @endforeach
</select>
@error('channel') <div class="error">{{ $message }}</div> @enderror

@php($recipients = old('recipients', $reminder->recipients->pluck('email')->all()))
<fieldset class="optional-list">
    <legend>Also notify <span class="muted">(optional, up to {{ \App\Models\Reminder::MAX_RECIPIENTS }} people)</span></legend>
    @error('recipients') <div class="error">{{ $message }}</div> @enderror
    @for ($i = 0; $i < \App\Models\Reminder::MAX_RECIPIENTS; $i++)
        <label for="recipient-{{ $i }}" class="visually-hidden">Person {{ $i + 1 }} email</label>
        <input id="recipient-{{ $i }}" type="email" name="recipients[{{ $i }}]" maxlength="255"
               value="{{ $recipients[$i] ?? '' }}" placeholder="name@example.com" autocomplete="off">
        @error('recipients.'.$i) <div class="error">{{ $message }}</div> @enderror
    @endfor
</fieldset>

@if (! $reminder->exists && ! old('timezone'))
    <script>
        // New notifications default to the browser's timezone.
        (function () {
            var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
            if (tz) {
                document.getElementById('timezone').value = tz;
                document.getElementById('timezone-label').textContent = tz;
            }
        })();
    </script>
@endif
