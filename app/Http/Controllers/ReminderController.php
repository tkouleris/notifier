<?php

namespace App\Http\Controllers;

use App\Enums\ReminderStatus;
use App\Http\Requests\ReminderRequest;
use App\Models\Reminder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReminderController extends Controller
{
    public function index(Request $request): View
    {
        $reminders = $request->user()->reminders()
            ->with('recipients')
            ->orderByRaw('case when status = ? then 0 else 1 end', [ReminderStatus::Pending->value])
            ->orderBy('notify_at')
            ->paginate(15);

        return view('reminders.index', compact('reminders'));
    }

    public function create(): View
    {
        return view('reminders.create', ['reminder' => new Reminder()]);
    }

    public function store(ReminderRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $reminder = $request->user()->reminders()->create($request->reminderData());
            $reminder->syncRecipients($request->recipientEmails());
        });

        return redirect()->route('reminders.index')->with('status', 'Notification scheduled.');
    }

    public function edit(Reminder $reminder): View
    {
        $this->authorize('update', $reminder);

        return view('reminders.edit', compact('reminder'));
    }

    public function update(ReminderRequest $request, Reminder $reminder): RedirectResponse
    {
        $this->authorize('update', $reminder);

        // A rescheduled reminder (always in the future, per validation) goes back in the queue.
        DB::transaction(function () use ($request, $reminder) {
            $reminder->update($request->reminderData() + [
                'status' => ReminderStatus::Pending,
                'sent_at' => null,
            ]);
            $reminder->syncRecipients($request->recipientEmails());
        });

        return redirect()->route('reminders.index')->with('status', 'Notification updated.');
    }

    public function destroy(Reminder $reminder): RedirectResponse
    {
        $this->authorize('delete', $reminder);

        $reminder->delete();

        return redirect()->route('reminders.index')->with('status', 'Notification deleted.');
    }
}
