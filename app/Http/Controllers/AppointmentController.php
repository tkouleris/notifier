<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentMode;
use App\Enums\ReminderType;
use App\Http\Requests\AppointmentRequest;
use App\Models\Reminder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Appointments: a meeting with other people, in person, by phone or online. Everyone
 * is emailed on its dates. Listing and deleting go through ReminderController.
 */
class AppointmentController extends Controller
{
    public function create(): View
    {
        return view('appointments.create', ['reminder' => new Reminder([
            'type' => ReminderType::Appointment,
            'appointment_mode' => AppointmentMode::InPerson,
        ])]);
    }

    public function store(AppointmentRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $reminder = $request->user()->reminders()->create($request->reminderData());
            $reminder->syncDates($request->finalDate(), $request->earlyDates());
            $reminder->syncParticipants($request->participants());
        });

        return redirect()->route('reminders.index')->with('status', 'Appointment scheduled.');
    }

    public function edit(Reminder $reminder): View
    {
        $this->authorize('update', $reminder);
        abort_unless($reminder->isAppointment(), 404);

        $reminder->load('dates', 'recipients');

        return view('appointments.edit', compact('reminder'));
    }

    public function update(AppointmentRequest $request, Reminder $reminder): RedirectResponse
    {
        $this->authorize('update', $reminder);
        abort_unless($reminder->isAppointment(), 404);

        // Like other notifications, saving replaces the whole schedule with pending dates.
        DB::transaction(function () use ($request, $reminder) {
            $reminder->update($request->reminderData());
            $reminder->syncDates($request->finalDate(), $request->earlyDates());
            $reminder->syncParticipants($request->participants());
        });

        return redirect()->route('reminders.index')->with('status', 'Appointment updated.');
    }
}
