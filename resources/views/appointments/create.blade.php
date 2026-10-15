@extends('layouts.app')

@section('title', 'New appointment')

@section('content')
    <p><a href="{{ route('reminders.index') }}">&larr; Back to notifications</a></p>
    <h1>New appointment</h1>

    <form method="POST" action="{{ route('appointments.store') }}">
        @include('appointments._form')
        <button type="submit">Schedule</button>
    </form>
@endsection
