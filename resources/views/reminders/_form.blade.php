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

<label for="final_at">Final date</label>
<input id="final_at" type="datetime-local" name="final_at" required value="{{ $finalValue }}">
<div class="muted">Timezone: {{ $user->timezone }} · <a href="{{ route('settings.edit') }}">Change in settings</a></div>
@error('final_at') <div class="error">{{ $message }}</div> @enderror

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

@php($selectedTheme = old('email_theme', $reminder->email_theme?->value ?? \App\Enums\Theme::Light->value))
<fieldset class="theme-picker">
    <legend>Email theme</legend>
    @foreach (\App\Enums\Theme::cases() as $theme)
        <label class="theme-option theme-option-{{ $theme->value }}">
            <input type="radio" name="email_theme" value="{{ $theme->value }}" @checked($selectedTheme === $theme->value) required>
            <span class="theme-swatch" aria-hidden="true"></span>
            <strong>{{ $theme->label() }}</strong>
            <a href="{{ route('reminders.preview', $theme) }}" target="_blank" rel="noopener">Preview</a>
        </label>
    @endforeach
</fieldset>
@error('email_theme') <div class="error">{{ $message }}</div> @enderror

@push('styles')
    <style>
        fieldset.theme-picker { border: 0; padding: 0; margin: 1rem 0 0; display: flex; flex-wrap: wrap; gap: .5rem; }
        fieldset.theme-picker legend { padding: 0; margin-bottom: .25rem; font-size: .9rem; }
        .theme-option { flex: 1; display: flex; gap: .6rem; align-items: center; margin: 0; padding: .6rem .75rem; border: 1px solid var(--border); border-radius: 6px; cursor: pointer; }
        .theme-option:has(input:checked) { border-color: var(--link); box-shadow: 0 0 0 1px var(--link); }
        .theme-option input { margin: 0; }
        .theme-option strong { flex: 1; font-weight: normal; }
        .theme-option a { font-size: .85rem; }
        .theme-swatch { width: 1.75rem; height: 1.75rem; flex: none; border-radius: 5px; box-sizing: border-box; }
        /* Each swatch shows its email's page, card and accent colours, the same in both app themes. */
        .theme-option-light .theme-swatch { background: linear-gradient(#1d6fb8 0 4px, #fff 4px) content-box, #f4f5f7; padding: 4px; border: 1px solid #d1d5db; }
        .theme-option-dark .theme-swatch { background: linear-gradient(#f28c28 0 4px, #1a1d23 4px) content-box, #0f1115; padding: 4px; border: 1px solid #374151; }
    </style>
@endpush

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
