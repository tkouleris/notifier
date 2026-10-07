@extends('layouts.app')

@section('title', 'Notifications')
@section('main_class', 'wide')

@section('content')
    <div class="header">
        <h1>My notifications</h1>
        <div class="header-actions">
            <a class="button" href="{{ route('birthdays.create') }}">New birthday</a>
            <a class="button" href="{{ route('reminders.create') }}">New notification</a>
        </div>
    </div>

    @if (request()->boolean('verified'))
        <div class="status">Your email address has been verified.</div>
    @endif

    @if (session('status'))
        <div class="status">{{ session('status') }}</div>
    @endif

    @if ($reminders->isEmpty())
        <p class="muted">You have no notifications yet. Create one and we'll remind you when it's time.</p>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Dates</th>
                        <th>Channel</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($reminders as $reminder)
                        <tr>
                            <td>
                                <strong>{{ $reminder->title }}</strong>
                                @if ($reminder->isBirthday())
                                    <div class="muted">
                                        🎂 Birthday
                                        {{ \Illuminate\Support\Carbon::create(2000, $reminder->birth_month, $reminder->birth_day)->format('F j') }}{{ $reminder->birth_year ? ', ' . $reminder->birth_year : '' }}
                                        · every year to {{ $reminder->email }}
                                    </div>
                                @elseif ($reminder->message)
                                    <div class="muted">{{ \Illuminate\Support\Str::limit($reminder->message, 80) }}</div>
                                @endif
                            </td>
                            <td class="dates">
                                @foreach ($reminder->dates as $date)
                                    <div @class(['muted' => !$date->is_final])>
                                        {{ $reminder->isBirthday() ? 'Card' : ($date->is_final ? 'Final' : 'Reminder') }}:
                                        {{ auth()->user()->toLocal($date->notify_at)->format('M j, Y H:i') }}
                                    </div>
                                @endforeach
                            </td>
                            <td>{{ $reminder->channel->label() }}</td>
                            @php($status = $reminder->status())
                            <td><span class="badge badge-{{ $status->value }}">{{ $status->label() }}</span></td>
                            <td class="actions">
                                <a
                                    href="{{ route($reminder->isBirthday() ? 'birthdays.edit' : 'reminders.edit', $reminder) }}">Edit</a>
                                <form method="POST" action="{{ route('reminders.destroy', $reminder) }}"
                                    onsubmit="return confirm('Delete this notification?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="link danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $reminders->links('pagination::simple-default') }}
        <p class="muted">Times are shown in {{ auth()->user()->timezone }}.</p>
    @endif
@endsection
