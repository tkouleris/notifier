@extends('layouts.app')

@section('title', 'Notifications')
@section('main_class', 'wide')

@section('content')
    <div class="header">
        <h1>My notifications</h1>
        <a class="button" href="{{ route('reminders.create') }}">New notification</a>
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
                        <th>Notify at</th>
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
                                @if ($reminder->message)
                                    <div class="muted">{{ \Illuminate\Support\Str::limit($reminder->message, 80) }}</div>
                                @endif
                                @if ($reminder->recipients->isNotEmpty())
                                    <div class="muted">Also notifies {{ $reminder->recipients->pluck('email')->join(', ') }}</div>
                                @endif
                            </td>
                            <td>
                                {{ $reminder->localNotifyAt()->format('M j, Y H:i') }}
                                <div class="muted">{{ $reminder->timezone }}</div>
                            </td>
                            <td>{{ $reminder->channel->label() }}</td>
                            <td><span class="badge badge-{{ $reminder->status->value }}">{{ $reminder->status->label() }}</span></td>
                            <td class="actions">
                                <a href="{{ route('reminders.edit', $reminder) }}">Edit</a>
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
    @endif

    <form method="POST" action="{{ route('logout') }}" class="logout">
        @csrf
        <span class="muted">{{ auth()->user()->email }}</span>
        <button type="submit" class="link">Log out</button>
    </form>
@endsection
