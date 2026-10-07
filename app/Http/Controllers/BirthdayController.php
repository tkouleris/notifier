<?php

namespace App\Http\Controllers;

use App\Enums\BirthdayLayout;
use App\Enums\ReminderType;
use App\Http\Requests\BirthdayRequest;
use App\Models\Reminder;
use App\Models\ReminderDate;
use App\Notifications\ReminderDue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
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

    /**
     * Show a card layout as the email would look, filled with sample details.
     */
    public function preview(Request $request, BirthdayLayout $layout): Response
    {
        $reminder = new Reminder([
            'type' => ReminderType::Birthday,
            'title' => 'Alex',
            'birth_year' => now()->year - 30,
            'message' => "Can't wait to celebrate with you tonight!",
            'layout' => $layout,
            'timezone' => $request->user()->timezone,
        ]);
        $reminder->setRelation('user', $request->user());

        $date = new ReminderDate(['notify_at' => now(), 'is_final' => true]);
        $date->setRelation('reminder', $reminder);

        $mail = (new ReminderDue($date))->toMail(Notification::route('mail', 'alex@example.com'));

        return response($mail->render());
    }
}
