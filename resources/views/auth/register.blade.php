@extends('layouts.app')

@section('title', 'Register')

@section('content')
    @include('auth._logo')

    <h1>Create an account</h1>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <label for="name">Name</label>
        <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
        @error('name') <div class="error">{{ $message }}</div> @enderror

        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
        @error('email') <div class="error">{{ $message }}</div> @enderror

        <label for="password">Password</label>
        <input id="password" type="password" name="password" required autocomplete="new-password">
        @error('password') <div class="error">{{ $message }}</div> @enderror

        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">

        <input id="timezone" type="hidden" name="timezone" value="{{ old('timezone') }}">

        <button type="submit">Register</button>
    </form>

    <script>
        // Start with the browser's timezone; it can be changed later in settings.
        (function () {
            var input = document.getElementById('timezone');
            var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
            if (tz && ! input.value) {
                input.value = tz;
            }
        })();
    </script>

    <p class="muted">Already registered? <a href="{{ route('login') }}">Log in</a></p>
@endsection
