@extends('layouts.app')

@section('title', 'New birthday')

@section('content')
    <p><a href="{{ route('reminders.index') }}">&larr; Back to notifications</a></p>
    <h1>New birthday</h1>

    <form method="POST" action="{{ route('birthdays.store') }}">
        @include('birthdays._form')
        <button type="submit">Save</button>
    </form>
@endsection
