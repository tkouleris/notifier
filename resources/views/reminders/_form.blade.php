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

@include('reminders._optional-list', [
    'name' => 'reminder_dates',
    'legend' => 'Reminders before the final date',
    'max' => \App\Models\Reminder::MAX_EARLY_DATES,
    'values' => $reminderValues,
    'type' => 'datetime-local',
    'itemLabel' => 'Reminder date',
    'addLabel' => 'Add reminder',
])

<label for="channel">Notify me by</label>
<select id="channel" name="channel" required>
    @foreach (\App\Enums\ReminderChannel::cases() as $channel)
        <option value="{{ $channel->value }}" @selected(old('channel', $reminder->channel?->value) === $channel->value)>
            {{ $channel->label() }}
        </option>
    @endforeach
</select>
@error('channel') <div class="error">{{ $message }}</div> @enderror

@include('reminders._optional-list', [
    'name' => 'recipients',
    'legend' => 'Also notify',
    'max' => \App\Models\Reminder::MAX_RECIPIENTS,
    'values' => old('recipients', $reminder->recipients->pluck('email')->all()),
    'type' => 'email',
    'attributes' => 'maxlength="255" placeholder="name@example.com" autocomplete="off"',
    'itemLabel' => 'Email of person to notify',
    'addLabel' => 'Add person',
])

<script>
    // "+" adds a row to an optional list (up to its maximum); "Remove" drops one.
    document.querySelectorAll('[data-optional-list]').forEach(function (list) {
        var rows = list.querySelector('[data-rows]');
        var template = list.querySelector('template');
        var addButton = list.querySelector('[data-add]');
        var max = Number(list.dataset.max);
        var nextKey = Number(list.dataset.nextKey);

        function refresh() {
            addButton.hidden = rows.querySelectorAll('[data-row]').length >= max;
        }

        addButton.addEventListener('click', function () {
            var html = template.innerHTML.replace(/__KEY__/g, String(nextKey++));
            rows.insertAdjacentHTML('beforeend', html);
            rows.lastElementChild.querySelector('input').focus();
            refresh();
        });

        rows.addEventListener('click', function (event) {
            if (event.target.closest('[data-remove]')) {
                event.target.closest('[data-row]').remove();
                refresh();
                addButton.focus();
            }
        });
    });
</script>

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
