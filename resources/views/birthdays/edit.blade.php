@extends('layouts.app')

@section('title', 'Edit birthday')

@section('content')
    <p><a href="{{ route('reminders.index') }}">&larr; Back to notifications</a></p>
    <h1>Edit birthday</h1>

    <form method="POST" action="{{ route('birthdays.update', $reminder) }}">
        @method('PUT')
        @include('birthdays._form')
        <button type="submit">Save</button>
    </form>
@endsection
