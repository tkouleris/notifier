<?php

namespace App\Http\Requests;

use App\Enums\OffsetUnit;
use App\Enums\ReminderChannel;
use App\Enums\Repeat;
use App\Enums\Theme;
use App\Models\Reminder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\ExcludeIf;
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

        $offsets = $this->input('reminder_offsets');

        if (is_array($offsets)) {
            $this->merge(['reminder_offsets' => array_filter(
                $offsets,
                fn ($offset) => ! is_array($offset) || ($offset['amount'] ?? null) !== null && ($offset['amount'] ?? null) !== ''
            )]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
            'final_at' => ['required', 'date_format:'.self::DATE_FORMAT],
            // A repeating notification has reminders relative to each occurrence instead of fixed dates.
            'repeat' => ['nullable', Rule::enum(Repeat::class)],
            'reminder_dates' => [$this->excludeIfRepeating(), 'nullable', 'array', 'max:'.Reminder::MAX_EARLY_DATES],
            'reminder_dates.*' => [$this->excludeIfRepeating(), 'date_format:'.self::DATE_FORMAT, 'distinct'],
            'reminder_offsets' => [$this->excludeUnlessRepeating(), 'nullable', 'array', 'max:'.Reminder::MAX_EARLY_DATES],
            'reminder_offsets.*' => [$this->excludeUnlessRepeating(), 'array'],
            'reminder_offsets.*.amount' => [$this->excludeUnlessRepeating(), 'required', 'integer', 'min:1'],
            'reminder_offsets.*.unit' => [$this->excludeUnlessRepeating(), 'required', Rule::enum(OffsetUnit::class)],
            'channel' => ['required', Rule::enum(ReminderChannel::class)],
            'email_theme' => ['required', Rule::enum(Theme::class)],
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
            'repeat' => 'Choose how often the notification repeats.',
            'reminder_offsets.max' => 'You can add at most :max reminders.',
            'reminder_offsets.*.amount' => 'Enter a whole number, 1 or more.',
            'reminder_offsets.*.unit' => 'Choose days, weeks or months.',
            'email_theme' => 'Choose the light or dark email theme.',
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

            if ($this->repeating()) {
                $this->validateOffsets($validator);

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
     * Each reminder of a repeating notification must be shorter than its period, and listed once.
     */
    private function validateOffsets(Validator $validator): void
    {
        $repeat = Repeat::tryFrom($this->input('repeat'));

        if (! $repeat) {
            return;
        }

        $seen = [];

        foreach ($this->input('reminder_offsets', []) as $key => $offset) {
            if ($validator->errors()->has("reminder_offsets.$key.amount") || $validator->errors()->has("reminder_offsets.$key.unit")) {
                continue;
            }

            $unit = OffsetUnit::from($offset['unit']);
            $amount = (int) $offset['amount'];
            $max = $repeat->maxOffset($unit);

            if ($max === null) {
                $validator->errors()->add("reminder_offsets.$key.unit", "A {$repeat->value} notification can't remind {$unit->value}s before.");
            } elseif ($amount > $max) {
                $validator->errors()->add("reminder_offsets.$key.amount", "A {$repeat->value} notification can remind at most {$unit->label($max)} before.");
            } elseif (in_array("$amount {$unit->value}", $seen, true)) {
                $validator->errors()->add("reminder_offsets.$key.amount", 'This reminder is listed more than once.');
            }

            $seen[] = "$amount {$unit->value}";
        }
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
            'email_theme' => $this->validated('email_theme'),
            'repeat' => $this->validated('repeat'),
            // The first occurrence as a local time; every later one is counted from it.
            'repeat_anchor' => $this->validated('repeat')
                ? Carbon::createFromFormat(self::DATE_FORMAT, $this->validated('final_at'))->format('Y-m-d H:i')
                : null,
            'reminder_offsets' => $this->validated('repeat') ? $this->offsets() : null,
        ];
    }

    /**
     * A repeating notification's reminders, the furthest before the occurrence first.
     *
     * @return array<int, array{amount: int, unit: string}>
     */
    public function offsets(): array
    {
        return collect($this->validated('reminder_offsets') ?? [])
            ->map(fn (array $offset) => ['amount' => (int) $offset['amount'], 'unit' => $offset['unit']])
            ->sortBy(fn (array $offset) => OffsetUnit::from($offset['unit'])->subtractFrom(Carbon::create(2000), $offset['amount'])->timestamp)
            ->values()
            ->all();
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
    protected function toUtc(string $value): Carbon
    {
        return Carbon::createFromFormat(self::DATE_FORMAT, $value, $this->user()->timezone)
            ->second(0)
            ->utc();
    }

    /**
     * Whether the submitted notification repeats; appointments never do.
     */
    protected function repeating(): bool
    {
        return $this->filled('repeat');
    }

    private function excludeIfRepeating(): ExcludeIf
    {
        return Rule::excludeIf(fn () => $this->repeating());
    }

    private function excludeUnlessRepeating(): ExcludeIf
    {
        return Rule::excludeIf(fn () => ! $this->repeating());
    }

    protected function withoutBlanks(array $values): array
    {
        return array_filter($values, fn ($value) => $value !== '' && $value !== null);
    }
}
