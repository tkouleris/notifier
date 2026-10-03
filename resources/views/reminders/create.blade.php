@extends('layouts.app')

@section('title', 'New notification')

@section('content')
    <p><a href="{{ route('reminders.index') }}">&larr; Back to notifications</a></p>
    <h1>New notification</h1>

    <form method="POST" action="{{ route('reminders.store') }}">
        @include('reminders._form')
        <button type="submit">Schedule</button>
    </form>
@endsection
