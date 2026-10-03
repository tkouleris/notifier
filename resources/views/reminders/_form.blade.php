@csrf

<label for="title">Title</label>
<input id="title" type="text" name="title" value="{{ old('title', $reminder->title) }}" required maxlength="255" autofocus>
@error('title') <div class="error">{{ $message }}</div> @enderror

<label for="message">Message <span class="muted">(optional)</span></label>
<textarea id="message" name="message" rows="4" maxlength="5000">{{ old('message', $reminder->message) }}</textarea>
@error('message') <div class="error">{{ $message }}</div> @enderror

<label for="notify_at">Notify me at</label>
<input id="notify_at" type="datetime-local" name="notify_at" required
       value="{{ old('notify_at', $reminder->notify_at ? $reminder->localNotifyAt()->format('Y-m-d\TH:i') : '') }}">
<div class="muted">Timezone: <span id="timezone-label">{{ old('timezone', $reminder->exists ? $reminder->timezone : 'UTC') }}</span></div>
@error('notify_at') <div class="error">{{ $message }}</div> @enderror
@error('timezone') <div class="error">{{ $message }}</div> @enderror
<input id="timezone" type="hidden" name="timezone" value="{{ old('timezone', $reminder->exists ? $reminder->timezone : 'UTC') }}">

<label for="channel">Notify me by</label>
<select id="channel" name="channel" required>
    @foreach (\App\Enums\ReminderChannel::cases() as $channel)
        <option value="{{ $channel->value }}" @selected(old('channel', $reminder->channel?->value) === $channel->value)>
            {{ $channel->label() }}
        </option>
    @endforeach
</select>
@error('channel') <div class="error">{{ $message }}</div> @enderror

@if (! $reminder->exists && ! old('timezone'))
    <script>
        // New notifications default to the browser's timezone.
        (function () {
            var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
            if (tz) {
                document.getElementById('timezone').value = tz;
                document.getElementById('timezone-label').textContent = tz;
            }
        })();
    </script>
@endif
