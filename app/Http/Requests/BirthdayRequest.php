<?php

namespace App\Http\Requests;

use App\Enums\BirthdayLayout;
use App\Enums\ReminderChannel;
use App\Enums\ReminderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class BirthdayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'sender_name' => ['required', 'string', 'max:255'],
            'day' => ['required', 'integer', 'between:1,31'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:'.now()->year],
            'message' => ['nullable', 'string', 'max:5000'],
            'layout' => ['required', new Enum(BirthdayLayout::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => 'Enter a valid email address.',
            'layout' => 'Choose one of the card layouts.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->hasAny(['day', 'month', 'year'])) {
                return;
            }

            // Without a year, February 29 is allowed by checking against a leap year.
            if (! checkdate((int) $this->input('month'), (int) $this->input('day'), (int) ($this->input('year') ?? 2000))) {
                $validator->errors()->add('day', 'This day does not exist in that month.');
            }
        });
    }

    /**
     * Attributes ready to be saved on the model; the person's name is stored as the title.
     */
    public function reminderData(): array
    {
        $year = $this->validated('year');

        return [
            'type' => ReminderType::Birthday,
            'title' => $this->validated('name'),
            'email' => $this->validated('email'),
            'sender_name' => $this->validated('sender_name'),
            'birth_day' => (int) $this->validated('day'),
            'birth_month' => (int) $this->validated('month'),
            'birth_year' => $year === null ? null : (int) $year,
            'message' => $this->validated('message'),
            'layout' => BirthdayLayout::from($this->validated('layout')),
            'timezone' => $this->user()->timezone,
            'channel' => ReminderChannel::Email,
        ];
    }
}
