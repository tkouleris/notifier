@extends('layouts.app')

@section('title', 'Edit notification')

@section('content')
    <p><a href="{{ route('reminders.index') }}">&larr; Back to notifications</a></p>
    <h1>Edit notification</h1>

    @if ($reminder->dates->contains(fn ($date) => $date->status !== \App\Enums\ReminderStatus::Pending))
        <p class="muted">Dates that have already been sent are not shown. Saving replaces the whole schedule with the dates below.</p>
    @endif

    <form method="POST" action="{{ route('reminders.update', $reminder) }}">
        @method('PUT')
        @include('reminders._form')
        <button type="submit">Save</button>
    </form>
@endsection
