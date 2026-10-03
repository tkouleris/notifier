<?php

namespace App\Http\Requests;

use App\Enums\ReminderChannel;
use App\Models\Reminder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise the extra recipients and drop the blank fields, keeping the keys
     * so errors still line up with the inputs they came from.
     */
    protected function prepareForValidation(): void
    {
        $recipients = $this->input('recipients');

        if (! is_array($recipients)) {
            return;
        }

        $recipients = array_map(fn ($email) => is_string($email) ? strtolower(trim($email)) : $email, $recipients);

        $this->merge([
            'recipients' => array_filter($recipients, fn ($email) => $email !== '' && $email !== null),
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
            'notify_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'timezone' => ['required', 'timezone:all'],
            'channel' => ['required', Rule::enum(ReminderChannel::class)],
            'recipients' => ['nullable', 'array', 'max:'.Reminder::MAX_RECIPIENTS],
            'recipients.*' => [
                'string',
                'email',
                'max:255',
                'distinct',
                Rule::notIn([strtolower($this->user()->email)]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'recipients.max' => 'You can notify at most :max other people.',
            'recipients.*.email' => 'Enter a valid email address.',
            'recipients.*.distinct' => 'This email address is listed more than once.',
            'recipients.*.not_in' => 'You are already notified; enter someone else\'s address.',
        ];
    }

    /**
     * The validated extra recipient email addresses.
     *
     * @return array<int, string>
     */
    public function recipientEmails(): array
    {
        return array_values($this->validated('recipients') ?? []);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->hasAny(['notify_at', 'timezone'])) {
                return;
            }

            if (! $this->notifyAtUtc()->isFuture()) {
                $validator->errors()->add('notify_at', 'The notification time must be in the future.');
            }
        });
    }

    /**
     * The submitted local time (in the user's timezone) converted to UTC.
     */
    public function notifyAtUtc(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d\TH:i', $this->input('notify_at'), $this->input('timezone'))
            ->second(0)
            ->utc();
    }

    /**
     * Attributes ready to be saved on the model.
     */
    public function reminderData(): array
    {
        return [
            'title' => $this->validated('title'),
            'message' => $this->validated('message'),
            'notify_at' => $this->notifyAtUtc(),
            'timezone' => $this->validated('timezone'),
            'channel' => $this->validated('channel'),
        ];
    }
}
