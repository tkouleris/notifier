@extends('layouts.app')

@section('title', 'Log in')

@section('content')
    <h1>Log in</h1>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        @error('email') <div class="error">{{ $message }}</div> @enderror

        <label for="password">Password</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">
        @error('password') <div class="error">{{ $message }}</div> @enderror

        <label><input type="checkbox" name="remember"> Remember me</label>

        <button type="submit">Log in</button>
    </form>

    <p class="muted">No account yet? <a href="{{ route('register') }}">Register</a></p>
@endsection
