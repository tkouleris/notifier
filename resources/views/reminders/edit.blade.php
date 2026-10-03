@extends('layouts.app')

@section('title', 'Edit notification')

@section('content')
    <p><a href="{{ route('reminders.index') }}">&larr; Back to notifications</a></p>
    <h1>Edit notification</h1>

    @if ($reminder->status !== \App\Enums\ReminderStatus::Pending)
        <p class="muted">This notification is {{ strtolower($reminder->status->label()) }}. Saving it will schedule it again.</p>
    @endif

    <form method="POST" action="{{ route('reminders.update', $reminder) }}">
        @method('PUT')
        @include('reminders._form')
        <button type="submit">Save</button>
    </form>
@endsection
