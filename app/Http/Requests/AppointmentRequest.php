<?php

namespace App\Http\Requests;

use App\Enums\AppointmentMode;
use App\Enums\ReminderType;
use App\Models\Reminder;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * An appointment is scheduled like a standard notification (its time is the final date),
 * plus how it happens and the people it is with; those with an email are emailed too.
 */
class AppointmentRequest extends ReminderRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $participants = $this->input('participants');

        if (is_array($participants)) {
            // Strings arrive trimmed, with blanks as null (see the global middleware).
            $participants = array_map(fn ($person) => is_array($person) ? [
                'name' => $person['name'] ?? null,
                'email' => is_string($person['email'] ?? null) ? strtolower($person['email']) : ($person['email'] ?? null),
                'phone' => $person['phone'] ?? null,
            ] : $person, $participants);

            // Rows left completely empty are dropped; half-filled ones stay so their error shows.
            $this->merge(['participants' => array_filter(
                $participants,
                fn ($person) => ! is_array($person) || filled($person['name']) || filled($person['email']) || filled($person['phone'])
            )]);
        }
    }

    public function rules(): array
    {
        return [
            ...Arr::except(parent::rules(), ['recipients', 'recipients.*']),
            'appointment_mode' => ['required', Rule::enum(AppointmentMode::class)],
            'participants' => ['required', 'array', 'max:'.Reminder::MAX_PARTICIPANTS],
            'participants.*' => ['array'],
            'participants.*.name' => ['required', 'string', 'max:255'],
            'participants.*.email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                'distinct',
                Rule::notIn([strtolower($this->user()->email)]),
            ],
            'participants.*.phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'required_if:appointment_mode,'.AppointmentMode::InPerson->value, 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'phone' => ['nullable', 'string', 'max:50'],
            'meeting_url' => ['nullable', 'url', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'final_at.required' => 'Choose when the appointment is.',
            'appointment_mode' => 'Choose whether the appointment is in person, by phone or online.',
            'participants.required' => 'Add at least one person you are meeting.',
            'participants.max' => 'You can add at most :max people.',
            'participants.*.name.required' => 'Enter the person\'s name.',
            'participants.*.email.required' => 'Enter the person\'s email address.',
            'participants.*.email.email' => 'Enter a valid email address.',
            'participants.*.email.distinct' => 'This email address is listed more than once.',
            'participants.*.email.not_in' => 'You are already notified; enter someone else\'s address.',
            'address.required_if' => 'Enter the address, or pick the place on the map.',
            'meeting_url.url' => 'Enter a valid link, starting with https://.',
        ];
    }

    /**
     * Attributes ready to be saved on the model; only the chosen mode's details are kept.
     */
    public function reminderData(): array
    {
        $mode = AppointmentMode::from($this->validated('appointment_mode'));
        $inPerson = $mode === AppointmentMode::InPerson;
        $hasPoint = $inPerson && $this->validated('latitude') !== null && $this->validated('longitude') !== null;

        return [
            ...parent::reminderData(),
            'type' => ReminderType::Appointment,
            'appointment_mode' => $mode,
            'address' => $inPerson ? $this->validated('address') : null,
            'latitude' => $hasPoint ? (float) $this->validated('latitude') : null,
            'longitude' => $hasPoint ? (float) $this->validated('longitude') : null,
            'phone' => $mode === AppointmentMode::Phone ? $this->validated('phone') : null,
            'meeting_url' => $mode === AppointmentMode::Online ? $this->validated('meeting_url') : null,
        ];
    }

    /**
     * The validated people the appointment is with.
     *
     * @return array<int, array{name: string, email: ?string, phone: ?string}>
     */
    public function participants(): array
    {
        return array_values(array_map(fn (array $person) => [
            'name' => $person['name'],
            'email' => $person['email'] ?? null,
            'phone' => $person['phone'] ?? null,
        ], $this->validated('participants')));
    }
}
