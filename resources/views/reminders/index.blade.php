@extends('layouts.app')

@section('title', 'Notifications')
@section('main_class', 'wide')

@section('content')
    <div class="header">
        <h1>My notifications</h1>
        <div class="header-actions">
            <a class="button" href="{{ route('birthdays.create') }}">
                <svg class="icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8"/><path d="M4 16s.5-1 2-1 2.5 2 4 2 2.5-2 4-2 2.5 2 4 2 2-1 2-1"/><path d="M2 21h20"/><path d="M7 8v3"/><path d="M12 8v3"/><path d="M17 8v3"/><path d="M7 4h.01"/><path d="M12 4h.01"/><path d="M17 4h.01"/></svg>
                Birthday
            </a>
            <a class="button" href="{{ route('appointments.create') }}">
                <svg class="icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/></svg>
                Appointment
            </a>
            <a class="button" href="{{ route('reminders.create') }}">
                <svg class="icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                Notification
            </a>
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
                                @elseif ($reminder->isAppointment())
                                    <div class="muted">
                                        📅 {{ $reminder->appointment_mode?->label() ?? 'Appointment' }}
                                        @if ($reminder->address) · {{ \Illuminate\Support\Str::limit($reminder->address, 60) }} @endif
                                        @if ($reminder->participants->isNotEmpty()) · with {{ $reminder->participants->pluck('name')->join(', ') }} @endif
                                    </div>
                                @elseif ($reminder->message)
                                    <div class="muted">{{ \Illuminate\Support\Str::limit($reminder->message, 80) }}</div>
                                @endif
                            </td>
                            <td class="dates">
                                @foreach ($reminder->dates as $date)
                                    <div @class(['muted' => !$date->is_final])>
                                        {{ $reminder->isBirthday() ? 'Card' : ($date->is_final ? ($reminder->isAppointment() ? 'Appointment' : 'Final') : 'Reminder') }}:
                                        {{ auth()->user()->toLocal($date->notify_at)->format('M j, Y H:i') }}
                                    </div>
                                @endforeach
                            </td>
                            <td>{{ $reminder->channel->label() }}</td>
                            @php($status = $reminder->status())
                            <td><span class="badge badge-{{ $status->value }}">{{ $status->label() }}</span></td>
                            <td class="actions">
                                <a
                                    href="{{ route(match (true) { $reminder->isBirthday() => 'birthdays.edit', $reminder->isAppointment() => 'appointments.edit', default => 'reminders.edit' }, $reminder) }}">Edit</a>
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
