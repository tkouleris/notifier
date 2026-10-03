@extends('layouts.app')

@section('title', 'Settings')

@section('content')
    <h1>Settings</h1>

    @if (session('status'))
        <div class="status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('settings.update') }}">
        @csrf
        @method('PUT')

        @php($selected = old('timezone', $user->timezone))

        <label for="timezone">Timezone</label>
        <select id="timezone" name="timezone" required>
            @unless (in_array($selected, $timezones, true))
                <option value="{{ $selected }}" selected>{{ $selected }}</option>
            @endunless
            @foreach ($timezones as $timezone)
                <option value="{{ $timezone }}" @selected($selected === $timezone)>{{ str_replace('_', ' ', $timezone) }}</option>
            @endforeach
        </select>
        @error('timezone') <div class="error">{{ $message }}</div> @enderror
        <p class="muted">
            The dates and times you pick for your notifications are in this timezone.
            When you change it, notifications not yet sent keep their clock time in the new timezone
            (09:30 stays 09:30). Any that end up in the past are sent right away.
        </p>

        <button type="submit">Save settings</button>
    </form>
@endsection
