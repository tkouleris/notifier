{{--
    A repeating notification's reminders, each an amount of days, weeks or months before every occurrence.
    Same markup as _optional-list, so _optional-list-script adds and removes its rows.

    Expects: $values, a list of ['amount' => ..., 'unit' => ...].
--}}
@php
    $max = \App\Models\Reminder::MAX_EARLY_DATES;
    $rows = collect($values)->filter(fn ($offset, $key) => is_array($offset) && (filled($offset['amount'] ?? null) || $errors->has("reminder_offsets.$key.*")));
    $nextKey = $rows->keys()->map(fn ($key) => (int) $key)->max() + 1;
@endphp

<fieldset class="optional-list" data-optional-list data-max="{{ $max }}" data-next-key="{{ $nextKey }}">
    <legend>Reminders before each occurrence <span class="muted">(optional, up to {{ $max }})</span></legend>
    @error('reminder_offsets') <div class="error">{{ $message }}</div> @enderror

    <div data-rows>
        @foreach ($rows as $key => $offset)
            @include('reminders._offset-row', ['key' => $key, 'amount' => $offset['amount'] ?? '', 'unit' => $offset['unit'] ?? null])
        @endforeach
    </div>

    <template>
        @include('reminders._offset-row', ['key' => '__KEY__', 'amount' => '', 'unit' => null])
    </template>

    <button type="button" class="link add-row" data-add @if ($rows->count() >= $max) hidden @endif>+ Add reminder</button>
</fieldset>
