@extends('layouts.app')

@section('title', 'Edit appointment')

@section('content')
    <p><a href="{{ route('reminders.index') }}">&larr; Back to notifications</a></p>
    <h1>Edit appointment</h1>

    <form method="POST" action="{{ route('appointments.update', $reminder) }}">
        @method('PUT')
        @include('appointments._form')
        <button type="submit">Save</button>
    </form>
@endsection
