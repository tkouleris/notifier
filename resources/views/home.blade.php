@extends('layouts.app')

@section('title', 'Home')

@section('content')
    @if (request()->boolean('verified'))
        <div class="status">Your email address has been verified.</div>
    @endif

    <h1>Welcome, {{ auth()->user()->name }}</h1>
    <p class="muted">You are logged in as {{ auth()->user()->email }}.</p>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Log out</button>
    </form>
@endsection
