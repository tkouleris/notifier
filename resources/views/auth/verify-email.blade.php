@extends('layouts.app')

@section('title', 'Verify your email')

@section('content')
    <h1>Verify your email</h1>

    @if (session('status') === 'verification-link-sent')
        <div class="status">A new verification link has been sent to your email address.</div>
    @elseif (session('status') === 'verification-link-failed')
        <div class="error-box">We couldn't send the verification email right now. Please try again in a few minutes.</div>
    @endif

    <p><strong>Your account needs to be verified before you can use {{ config('app.name') }}.</strong></p>

    <p>Thanks for signing up! Please confirm your email address by clicking the link we sent to
        <strong>{{ auth()->user()->email }}</strong>.</p>

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit">Resend verification email</button>
    </form>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Log out</button>
    </form>
@endsection
