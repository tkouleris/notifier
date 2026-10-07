@csrf

<label for="name">Name</label>
<input id="name" type="text" name="name" value="{{ old('name', $reminder->title) }}" required maxlength="255" autofocus>
@error('name') <div class="error">{{ $message }}</div> @enderror

<label for="email">Email</label>
<input id="email" type="email" name="email" value="{{ old('email', $reminder->email) }}" required maxlength="255" placeholder="name@example.com" autocomplete="off">
<div class="muted">The birthday card is sent to this address.</div>
@error('email') <div class="error">{{ $message }}</div> @enderror

<div class="date-parts">
    <div>
        <label for="day">Day</label>
        <select id="day" name="day" required>
            <option value=""></option>
            @foreach (range(1, 31) as $day)
                <option value="{{ $day }}" @selected((int) old('day', $reminder->birth_day) === $day)>{{ $day }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="month">Month</label>
        <select id="month" name="month" required>
            <option value=""></option>
            @foreach (range(1, 12) as $month)
                <option value="{{ $month }}" @selected((int) old('month', $reminder->birth_month) === $month)>
                    {{ \Illuminate\Support\Carbon::create(2000, $month)->format('F') }}
                </option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="year">Year <span class="muted">(optional)</span></label>
        <input id="year" type="number" name="year" value="{{ old('year', $reminder->birth_year) }}" min="1900" max="{{ now()->year }}">
    </div>
</div>
@error('day') <div class="error">{{ $message }}</div> @enderror
@error('month') <div class="error">{{ $message }}</div> @enderror
@error('year') <div class="error">{{ $message }}</div> @enderror

<label for="message">Personal message <span class="muted">(optional)</span></label>
<textarea id="message" name="message" rows="4" maxlength="5000">{{ old('message', $reminder->message) }}</textarea>
@error('message') <div class="error">{{ $message }}</div> @enderror

@php($selectedLayout = old('layout', $reminder->layout?->value ?? \App\Enums\BirthdayLayout::Balloons->value))
<fieldset class="layout-picker">
    <legend>Card layout</legend>
    @foreach (\App\Enums\BirthdayLayout::cases() as $layout)
        <label class="layout-option layout-{{ $layout->value }}">
            <input type="radio" name="layout" value="{{ $layout->value }}" @checked($selectedLayout === $layout->value) required>
            <span class="layout-swatch" aria-hidden="true"></span>
            <span class="layout-text">
                <strong>{{ $layout->label() }}</strong>
                <span class="muted">{{ $layout->description() }}</span>
            </span>
            <a href="{{ route('birthdays.preview', $layout) }}" target="_blank" rel="noopener">Preview</a>
        </label>
    @endforeach
</fieldset>
@error('layout') <div class="error">{{ $message }}</div> @enderror

@push('styles')
    <style>
        fieldset.layout-picker { border: 0; padding: 0; margin: 1rem 0 0; }
        fieldset.layout-picker legend { padding: 0; font-size: .9rem; }
        .layout-option { display: flex; gap: .75rem; align-items: center; margin-top: .5rem; padding: .6rem .75rem; border: 1px solid var(--border); border-radius: 6px; cursor: pointer; }
        .layout-option:has(input:checked) { border-color: var(--link); box-shadow: 0 0 0 1px var(--link); }
        .layout-option input { margin: 0; }
        .layout-text { flex: 1; display: flex; flex-direction: column; gap: .1rem; }
        .layout-text .muted { font-size: .8rem; }
        .layout-option a { font-size: .85rem; }
        .layout-swatch { width: 2.25rem; height: 2.25rem; flex: none; border-radius: 6px; }
        /* Each swatch hints at its email's colours, which stay the same in both themes. */
        .layout-balloons .layout-swatch { background: radial-gradient(circle at 30% 35%, #f9a8d4 0 22%, transparent 23%), radial-gradient(circle at 68% 60%, #7dd3fc 0 22%, transparent 23%), #e0f2fe; }
        .layout-confetti .layout-swatch { background: radial-gradient(circle at 25% 30%, #facc15 0 10%, transparent 11%), radial-gradient(circle at 70% 25%, #22d3ee 0 10%, transparent 11%), radial-gradient(circle at 50% 70%, #a3e635 0 10%, transparent 11%), linear-gradient(135deg, #db2777, #7c3aed); }
        .layout-cake .layout-swatch { background: #fffdf8; border: 2px double #c9a227; box-sizing: border-box; }
    </style>
@endpush

<p class="muted">
    The card is emailed every year on the birthday at {{ sprintf('%02d:00', \App\Models\Reminder::BIRTHDAY_SEND_HOUR) }}
    ({{ auth()->user()->timezone }} · <a href="{{ route('settings.edit') }}">change in settings</a>).
</p>
