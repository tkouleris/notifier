@csrf

<label for="title">Title</label>
<input id="title" type="text" name="title" value="{{ old('title', $reminder->title) }}" required maxlength="255" autofocus>
@error('title') <div class="error">{{ $message }}</div> @enderror

<label for="message">Message <span class="muted">(optional)</span></label>
<textarea id="message" name="message" rows="4" maxlength="5000">{{ old('message', $reminder->message) }}</textarea>
@error('message') <div class="error">{{ $message }}</div> @enderror

@include('reminders._schedule')

<label for="channel">Notify me by</label>
<select id="channel" name="channel" required>
    @foreach (\App\Enums\ReminderChannel::cases() as $channel)
        <option value="{{ $channel->value }}" @selected(old('channel', $reminder->channel?->value) === $channel->value)>
            {{ $channel->label() }}
        </option>
    @endforeach
</select>
@error('channel') <div class="error">{{ $message }}</div> @enderror

@include('reminders._theme-picker')

@include('reminders._optional-list', [
    'name' => 'recipients',
    'legend' => 'Also notify',
    'max' => \App\Models\Reminder::MAX_RECIPIENTS,
    'values' => old('recipients', $reminder->recipients->pluck('email')->all()),
    'type' => 'email',
    'attributes' => 'maxlength="255" placeholder="name@example.com" autocomplete="off"',
    'itemLabel' => 'Email of person to notify',
    'addLabel' => 'Add person',
])

@include('reminders._optional-list-script')
