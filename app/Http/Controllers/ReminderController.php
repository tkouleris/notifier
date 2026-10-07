<?php

namespace App\Http\Controllers;

use App\Enums\ReminderStatus;
use App\Http\Requests\ReminderRequest;
use App\Models\Reminder;
use App\Models\ReminderDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReminderController extends Controller
{
    public function index(Request $request): View
    {
        // Notifications with dates still to send come first, then by final date
        // (a birthday keeps last year's card too, so take the latest).
        $reminders = $request->user()->reminders()
            ->with('dates')
            ->withExists(['dates as has_pending_dates' => fn ($query) => $query->where('status', ReminderStatus::Pending)])
            ->addSelect(['final_at' => ReminderDate::select('notify_at')
                ->whereColumn('reminder_id', 'reminders.id')
                ->where('is_final', true)
                ->orderByDesc('notify_at')
                ->limit(1)])
            ->orderByDesc('has_pending_dates')
            ->orderBy('final_at')
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
            $reminder->syncDates($request->finalDate(), $request->earlyDates());
            $reminder->syncRecipients($request->recipientEmails());
        });

        return redirect()->route('reminders.index')->with('status', 'Notification scheduled.');
    }

    public function edit(Reminder $reminder): View|RedirectResponse
    {
        $this->authorize('update', $reminder);

        if ($reminder->isBirthday()) {
            return redirect()->route('birthdays.edit', $reminder);
        }

        $reminder->load('dates', 'recipients');

        return view('reminders.edit', compact('reminder'));
    }

    public function update(ReminderRequest $request, Reminder $reminder): RedirectResponse
    {
        $this->authorize('update', $reminder);
        abort_if($reminder->isBirthday(), 404);

        // Saving replaces the whole schedule; every date (all in the future, per validation) is pending again.
        DB::transaction(function () use ($request, $reminder) {
            $reminder->update($request->reminderData());
            $reminder->syncDates($request->finalDate(), $request->earlyDates());
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
