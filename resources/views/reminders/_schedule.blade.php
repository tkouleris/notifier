{{-- The final date and the optional reminders before it, entered in the user's timezone. --}}
@php
    $inputFormat = 'Y-m-d\TH:i';
    // A repeating notification also keeps its last sent final date; the latest one is the upcoming occurrence.
    $finalDate = $reminder->dates->where('is_final', true)->last();
    // Times are entered and shown in the timezone from the user's settings.
    $user = auth()->user();
    $finalValue = old('final_at', $finalDate ? $user->toLocal($finalDate->notify_at)->format($inputFormat) : '');
    // Reminders already sent are in the past, so only the ones still to come are editable.
    $reminderValues = old('reminder_dates', $reminder->earlyDates()
        ->where('status', \App\Enums\ReminderStatus::Pending)
        ->map(fn ($date) => $user->toLocal($date->notify_at)->format($inputFormat))
        ->values()
        ->all());
    $repeatable ??= false;
    $selectedRepeat = $repeatable ? old('repeat', $reminder->repeat?->value) : null;
@endphp

@if ($repeatable)
    <label for="repeat">Repeat</label>
    <select id="repeat" name="repeat" data-repeat-select>
        <option value="">Does not repeat</option>
        @foreach (\App\Enums\Repeat::cases() as $repeat)
            <option value="{{ $repeat->value }}" @selected($selectedRepeat === $repeat->value)
                data-max-offsets="{{ json_encode(collect(\App\Enums\OffsetUnit::cases())->mapWithKeys(fn ($unit) => [$unit->value => $repeat->maxOffset($unit)])->filter()) }}">
                {{ $repeat->label() }}
            </option>
        @endforeach
    </select>
    @error('repeat') <div class="error">{{ $message }}</div> @enderror
@endif

<label for="final_at">{{ $finalLabel ?? 'Final date' }}</label>
<input id="final_at" type="datetime-local" name="final_at" required value="{{ $finalValue }}">
<div class="muted">
    @if ($repeatable)
        <span data-repeat-only @if (! $selectedRepeat) hidden @endif>The first occurrence; it repeats from here. ·</span>
    @endif
    Timezone: {{ $user->timezone }} · <a href="{{ route('settings.edit') }}">Change in settings</a>
</div>
@error('final_at') <div class="error">{{ $message }}</div> @enderror

<div data-fixed-dates @if ($selectedRepeat) hidden @endif>
    @include('reminders._optional-list', [
        'name' => 'reminder_dates',
        'legend' => $remindersLegend ?? 'Reminders before the final date',
        'max' => \App\Models\Reminder::MAX_EARLY_DATES,
        'values' => $reminderValues,
        'type' => 'datetime-local',
        'itemLabel' => 'Reminder date',
        'addLabel' => 'Add reminder',
    ])
</div>

@if ($repeatable)
    <div data-offsets @if (! $selectedRepeat) hidden @endif>
        @include('reminders._offset-list', [
            'values' => old('reminder_offsets', $reminder->reminder_offsets ?? []),
        ])
    </div>

    <script>
        // Repeating notifications use reminders counted back from each occurrence instead of fixed dates;
        // only the visible list is submitted, and each repeat allows only the units shorter than its period.
        (function () {
            var select = document.querySelector('[data-repeat-select]');
            var fixed = document.querySelector('[data-fixed-dates]');
            var offsets = document.querySelector('[data-offsets]');

            function limits() {
                var option = select.options[select.selectedIndex];
                return option.dataset.maxOffsets ? JSON.parse(option.dataset.maxOffsets) : {};
            }

            function limitRow(row, max) {
                var unit = row.querySelector('select');
                var amount = row.querySelector('input');
                Array.prototype.forEach.call(unit.options, function (option) {
                    option.hidden = option.disabled = !(option.value in max);
                });
                if (!(unit.value in max)) {
                    unit.value = Object.keys(max)[0] || '';
                }
                amount.max = max[unit.value] || '';
            }

            function refresh() {
                var repeating = select.value !== '';
                var max = limits();
                fixed.hidden = repeating;
                offsets.hidden = !repeating;
                document.querySelectorAll('[data-repeat-only]').forEach(function (el) { el.hidden = !repeating; });
                fixed.querySelectorAll('input').forEach(function (input) { input.disabled = repeating; });
                offsets.querySelectorAll('input, select').forEach(function (input) { input.disabled = !repeating; });
                if (repeating) {
                    offsets.querySelectorAll('[data-row]').forEach(function (row) { limitRow(row, max); });
                }
            }

            select.addEventListener('change', refresh);
            offsets.addEventListener('change', function (event) {
                if (event.target.matches('select')) {
                    limitRow(event.target.closest('[data-row]'), limits());
                }
            });
            // Rows added with "+" get the current limits too.
            new MutationObserver(refresh).observe(offsets.querySelector('[data-rows]'), { childList: true });
            refresh();
        })();
    </script>
@endif
