@extends('layouts.app')

@section('title', 'Profile')

@section('content')
    <h1>Profile</h1>

    @if (session('status'))
        <div class="status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('profile.update') }}">
        @csrf
        @method('PUT')

        <label for="name">Username</label>
        <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="255" autocomplete="name">
        @error('name') <div class="error">{{ $message }}</div> @enderror

        <label for="birthday">Birthday <span class="muted">(optional)</span></label>
        <input id="birthday" type="date" name="birthday" value="{{ old('birthday', $user->birthday?->format('Y-m-d')) }}" max="{{ now()->subDay()->format('Y-m-d') }}" autocomplete="bday">
        @error('birthday') <div class="error">{{ $message }}</div> @enderror

        <p class="muted">Email: {{ $user->email }}</p>

        <button type="submit">Save profile</button>
    </form>

    <hr class="section">

    <h2>Change password</h2>

    <form method="POST" action="{{ route('profile.password') }}">
        @csrf
        @method('PUT')

        <label for="current_password">Current password</label>
        <input id="current_password" type="password" name="current_password" required autocomplete="current-password">
        @error('current_password', 'password') <div class="error">{{ $message }}</div> @enderror

        <label for="password">New password</label>
        <input id="password" type="password" name="password" required autocomplete="new-password">
        @error('password', 'password') <div class="error">{{ $message }}</div> @enderror

        <label for="password_confirmation">Confirm new password</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">

        <button type="submit">Change password</button>
    </form>
@endsection
