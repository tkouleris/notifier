{{-- One row of _offset-list. Expects: $key, $amount, $unit. --}}
<div class="list-row" data-row>
    <div class="list-row-fields">
        <label for="reminder_offsets-{{ $key }}-amount" class="visually-hidden">How many</label>
        <input id="reminder_offsets-{{ $key }}-amount" type="number" name="reminder_offsets[{{ $key }}][amount]" value="{{ $amount }}" min="1" class="offset-amount">
        <label for="reminder_offsets-{{ $key }}-unit" class="visually-hidden">Days, weeks or months</label>
        <select id="reminder_offsets-{{ $key }}-unit" name="reminder_offsets[{{ $key }}][unit]">
            @foreach (\App\Enums\OffsetUnit::cases() as $option)
                <option value="{{ $option->value }}" @selected($unit === $option->value)>{{ $option->value }}s</option>
            @endforeach
        </select>
        <span class="muted">before</span>
        <button type="button" class="link danger" data-remove>Remove</button>
    </div>
    @error("reminder_offsets.$key.amount") <div class="error">{{ $message }}</div> @enderror
    @error("reminder_offsets.$key.unit") <div class="error">{{ $message }}</div> @enderror
</div>
