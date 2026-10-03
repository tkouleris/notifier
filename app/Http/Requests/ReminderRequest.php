<?php

namespace App\Http\Requests;

use App\Enums\ReminderChannel;
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

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
            'notify_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'timezone' => ['required', 'timezone:all'],
            'channel' => ['required', Rule::enum(ReminderChannel::class)],
        ];
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
