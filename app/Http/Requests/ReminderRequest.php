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
    private const DATE_FORMAT = 'Y-m-d\TH:i';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise the optional lists and drop their blank fields, keeping the keys
     * so errors still line up with the inputs they came from.
     */
    protected function prepareForValidation(): void
    {
        $recipients = $this->input('recipients');

        if (is_array($recipients)) {
            $recipients = array_map(fn ($email) => is_string($email) ? strtolower(trim($email)) : $email, $recipients);
            $this->merge(['recipients' => $this->withoutBlanks($recipients)]);
        }

        $reminderDates = $this->input('reminder_dates');

        if (is_array($reminderDates)) {
            $this->merge(['reminder_dates' => $this->withoutBlanks($reminderDates)]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
            'final_at' => ['required', 'date_format:'.self::DATE_FORMAT],
            'reminder_dates' => ['nullable', 'array', 'max:'.Reminder::MAX_EARLY_DATES],
            'reminder_dates.*' => ['date_format:'.self::DATE_FORMAT, 'distinct'],
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
            'reminder_dates.max' => 'You can add at most :max reminders.',
            'reminder_dates.*.date_format' => 'Enter a valid date and time.',
            'reminder_dates.*.distinct' => 'This reminder date is listed more than once.',
            'recipients.max' => 'You can notify at most :max other people.',
            'recipients.*.email' => 'Enter a valid email address.',
            'recipients.*.distinct' => 'This email address is listed more than once.',
            'recipients.*.not_in' => 'You are already notified; enter someone else\'s address.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('final_at')) {
                return;
            }

            $final = $this->toUtc($this->input('final_at'));

            if (! $final->isFuture()) {
                $validator->errors()->add('final_at', 'The final date must be in the future.');

                return;
            }

            foreach ($this->input('reminder_dates', []) as $key => $value) {
                if ($validator->errors()->has("reminder_dates.$key")) {
                    continue;
                }

                $date = $this->toUtc($value);

                if (! $date->isFuture()) {
                    $validator->errors()->add("reminder_dates.$key", 'Reminders must be in the future.');
                } elseif (! $date->lt($final)) {
                    $validator->errors()->add("reminder_dates.$key", 'Reminders must come before the final date.');
                }
            }
        });
    }

    /**
     * Attributes ready to be saved on the model.
     */
    public function reminderData(): array
    {
        return [
            'title' => $this->validated('title'),
            'message' => $this->validated('message'),
            'timezone' => $this->user()->timezone,
            'channel' => $this->validated('channel'),
        ];
    }

    /**
     * The final date in UTC.
     */
    public function finalDate(): Carbon
    {
        return $this->toUtc($this->validated('final_at'));
    }

    /**
     * The optional earlier reminders in UTC, earliest first.
     *
     * @return array<int, Carbon>
     */
    public function earlyDates(): array
    {
        return collect($this->validated('reminder_dates') ?? [])
            ->map(fn (string $value) => $this->toUtc($value))
            ->sort()
            ->values()
            ->all();
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

    /**
     * A submitted local time (in the timezone from the user's settings) converted to UTC.
     */
    private function toUtc(string $value): Carbon
    {
        return Carbon::createFromFormat(self::DATE_FORMAT, $value, $this->user()->timezone)
            ->second(0)
            ->utc();
    }

    private function withoutBlanks(array $values): array
    {
        return array_filter($values, fn ($value) => $value !== '' && $value !== null);
    }
}
