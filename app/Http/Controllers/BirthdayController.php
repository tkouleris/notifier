<?php

namespace App\Http\Controllers;

use App\Enums\ReminderType;
use App\Http\Requests\BirthdayRequest;
use App\Models\Reminder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Birthday notifications: a card emailed to the person every year on their birthday.
 * Listing and deleting go through ReminderController like any other notification.
 */
class BirthdayController extends Controller
{
    public function create(): View
    {
        return view('birthdays.create', ['reminder' => new Reminder(['type' => ReminderType::Birthday])]);
    }

    public function store(BirthdayRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $reminder = $request->user()->reminders()->create($request->reminderData());
            $reminder->syncDates($reminder->nextBirthday(), []);
        });

        return redirect()->route('reminders.index')->with('status', 'Birthday saved.');
    }

    public function edit(Reminder $reminder): View
    {
        $this->authorize('update', $reminder);
        abort_unless($reminder->isBirthday(), 404);

        return view('birthdays.edit', compact('reminder'));
    }

    public function update(BirthdayRequest $request, Reminder $reminder): RedirectResponse
    {
        $this->authorize('update', $reminder);
        abort_unless($reminder->isBirthday(), 404);

        DB::transaction(function () use ($request, $reminder) {
            $reminder->update($request->reminderData());
            $reminder->syncDates($reminder->nextBirthday(), []);
        });

        return redirect()->route('reminders.index')->with('status', 'Birthday updated.');
    }
}
