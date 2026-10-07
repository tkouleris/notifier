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

<p class="muted">
    The card is emailed every year on the birthday at {{ sprintf('%02d:00', \App\Models\Reminder::BIRTHDAY_SEND_HOUR) }}
    ({{ auth()->user()->timezone }} · <a href="{{ route('settings.edit') }}">change in settings</a>).
</p>
