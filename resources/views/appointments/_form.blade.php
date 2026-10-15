@csrf
<input type="hidden" name="channel" value="{{ \App\Enums\ReminderChannel::Email->value }}">

<label for="title">Title</label>
<input id="title" type="text" name="title" value="{{ old('title', $reminder->title) }}" required maxlength="255" autofocus
       placeholder="e.g. Coffee with Maria">
@error('title') <div class="error">{{ $message }}</div> @enderror

@include('reminders._schedule', [
    'finalLabel' => 'Appointment date',
    'remindersLegend' => 'Reminders before the appointment',
])

@php
    $selectedMode = old('appointment_mode', $reminder->appointment_mode?->value ?? \App\Enums\AppointmentMode::InPerson->value);
    // Start with one empty row so there is always somewhere to type the first person.
    $people = collect(old('participants', $reminder->participants->map->only('name', 'email', 'phone')->all()))
        ->filter(fn ($person, $key) => is_array($person) && (filled($person['name'] ?? null) || filled($person['email'] ?? null) || filled($person['phone'] ?? null) || $errors->has("participants.$key.*")));
    if ($people->isEmpty()) {
        $people = collect([['name' => '', 'email' => '', 'phone' => '']]);
    }
    $nextPersonKey = $people->keys()->map(fn ($key) => (int) $key)->max() + 1;
    $maxPeople = \App\Models\Reminder::MAX_PARTICIPANTS;
@endphp

<fieldset class="mode-picker">
    <legend>How will you meet?</legend>
    @foreach (\App\Enums\AppointmentMode::cases() as $mode)
        <label class="mode-option">
            <input type="radio" name="appointment_mode" value="{{ $mode->value }}" @checked($selectedMode === $mode->value) required>
            <span aria-hidden="true">{{ ['in_person' => '📍', 'phone' => '📞', 'online' => '💻'][$mode->value] }}</span>
            <strong>{{ $mode->label() }}</strong>
        </label>
    @endforeach
</fieldset>
@error('appointment_mode') <div class="error">{{ $message }}</div> @enderror

<div data-mode-section="in_person">
    <label for="address">Address</label>
    <div class="address-row">
        <input id="address" type="text" name="address" value="{{ old('address', $reminder->address) }}" maxlength="255"
               autocomplete="off" placeholder="Type an address, or click on the map">
        <button type="button" class="secondary" data-find-address>Find on map</button>
    </div>
    @error('address') <div class="error">{{ $message }}</div> @enderror
    <input type="hidden" name="latitude" value="{{ old('latitude', $reminder->latitude) }}" data-latitude>
    <input type="hidden" name="longitude" value="{{ old('longitude', $reminder->longitude) }}" data-longitude>
    <div id="appointment-map" class="appointment-map" role="application" aria-label="Map: click to pick the place"></div>
    <div class="muted" data-map-status>Click on the map to pick the place; the address fills in by itself.</div>
</div>

<div data-mode-section="phone">
    <label for="phone">Phone number <span class="muted">(optional)</span></label>
    <input id="phone" type="tel" name="phone" value="{{ old('phone', $reminder->phone) }}" maxlength="50" autocomplete="off">
    @error('phone') <div class="error">{{ $message }}</div> @enderror
</div>

<div data-mode-section="online">
    <label for="meeting_url">Meeting link <span class="muted">(optional)</span></label>
    <input id="meeting_url" type="url" name="meeting_url" value="{{ old('meeting_url', $reminder->meeting_url) }}" maxlength="2048"
           placeholder="https://meet.example.com/..." autocomplete="off">
    @error('meeting_url') <div class="error">{{ $message }}</div> @enderror
</div>

<fieldset class="optional-list" data-optional-list data-max="{{ $maxPeople }}" data-next-key="{{ $nextPersonKey }}">
    <legend>With <span class="muted">(up to {{ $maxPeople }} people; email and phone are optional, and those with an email get the reminders too)</span></legend>
    @error('participants') <div class="error">{{ $message }}</div> @enderror

    <div data-rows>
        @foreach ($people as $key => $person)
            <div class="list-row" data-row>
                <div class="person-fields">
                    <label for="participants-{{ $key }}-name" class="visually-hidden">Name</label>
                    <input id="participants-{{ $key }}-name" class="person-name" type="text" name="participants[{{ $key }}][name]" value="{{ $person['name'] ?? '' }}" maxlength="255" placeholder="Name" autocomplete="off">
                    <button type="button" class="link danger" data-remove>Remove</button>
                    <label for="participants-{{ $key }}-email" class="visually-hidden">Email (optional)</label>
                    <input id="participants-{{ $key }}-email" type="email" name="participants[{{ $key }}][email]" value="{{ $person['email'] ?? '' }}" maxlength="255" placeholder="Email (optional)" autocomplete="off">
                    <label for="participants-{{ $key }}-phone" class="visually-hidden">Phone (optional)</label>
                    <input id="participants-{{ $key }}-phone" type="tel" name="participants[{{ $key }}][phone]" value="{{ $person['phone'] ?? '' }}" maxlength="50" placeholder="Phone (optional)" autocomplete="off">
                </div>
                @error("participants.$key.name") <div class="error">{{ $message }}</div> @enderror
                @error("participants.$key.email") <div class="error">{{ $message }}</div> @enderror
                @error("participants.$key.phone") <div class="error">{{ $message }}</div> @enderror
            </div>
        @endforeach
    </div>

    <template>
        <div class="list-row" data-row>
            <div class="person-fields">
                <label for="participants-__KEY__-name" class="visually-hidden">Name</label>
                <input id="participants-__KEY__-name" class="person-name" type="text" name="participants[__KEY__][name]" value="" maxlength="255" placeholder="Name" autocomplete="off">
                <button type="button" class="link danger" data-remove>Remove</button>
                <label for="participants-__KEY__-email" class="visually-hidden">Email (optional)</label>
                <input id="participants-__KEY__-email" type="email" name="participants[__KEY__][email]" value="" maxlength="255" placeholder="Email (optional)" autocomplete="off">
                <label for="participants-__KEY__-phone" class="visually-hidden">Phone (optional)</label>
                <input id="participants-__KEY__-phone" type="tel" name="participants[__KEY__][phone]" value="" maxlength="50" placeholder="Phone (optional)" autocomplete="off">
            </div>
        </div>
    </template>

    <button type="button" class="link add-row" data-add @if ($people->count() >= $maxPeople) hidden @endif>+ Add person</button>
</fieldset>

<label for="message">Notes <span class="muted">(optional)</span></label>
<textarea id="message" name="message" rows="3" maxlength="5000">{{ old('message', $reminder->message) }}</textarea>
@error('message') <div class="error">{{ $message }}</div> @enderror

@include('reminders._theme-picker')

@include('reminders._optional-list-script')

@push('styles')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    <style>
        fieldset.mode-picker { border: 0; padding: 0; margin: 1rem 0 0; display: flex; flex-wrap: wrap; gap: .5rem; }
        fieldset.mode-picker legend { padding: 0; margin-bottom: .25rem; font-size: .9rem; }
        .mode-option { flex: 1; display: flex; gap: .5rem; align-items: center; margin: 0; padding: .6rem .75rem; border: 1px solid var(--border); border-radius: 6px; cursor: pointer; white-space: nowrap; }
        .mode-option:has(input:checked) { border-color: var(--link); box-shadow: 0 0 0 1px var(--link); }
        .mode-option input { margin: 0; }
        .mode-option strong { font-weight: normal; }
        [data-mode-section][hidden] { display: none; }
        .address-row { display: flex; gap: .5rem; align-items: stretch; }
        .address-row input { flex: 1; min-width: 0; }
        .address-row button { margin-top: .25rem; white-space: nowrap; }
        button.secondary { background: none; color: var(--text); border: 1px solid var(--border); }
        .appointment-map { height: 280px; margin: .75rem 0 .25rem; border: 1px solid var(--border); border-radius: 6px; z-index: 0; }
        [data-theme=dark] .appointment-map .leaflet-tile-pane { filter: brightness(.8) contrast(1.1); }
        .person-fields { display: grid; grid-template-columns: 1fr 1fr auto; gap: .5rem .75rem; align-items: center; }
        .person-fields input { margin-top: 0; min-width: 0; }
        .person-fields .person-name { grid-column: 1 / 3; }
        .person-fields input[type=email] { grid-column: 1; }
        .person-fields input[type=tel] { grid-column: 2; }
        .list-row + .list-row { padding-top: .5rem; border-top: 1px solid var(--divider); }
        @media (max-width: 600px) { .person-fields { grid-template-columns: 1fr auto; } .person-fields .person-name { grid-column: 1; } .person-fields input[type=email], .person-fields input[type=tel] { grid-column: 1 / -1; } }
    </style>
@endpush

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
    (function () {
        var form = document.currentScript.closest('form');
        var sections = form.querySelectorAll('[data-mode-section]');
        var modeInputs = form.querySelectorAll('input[name=appointment_mode]');
        var map = null;

        // Only the chosen mode's fields are shown and submitted.
        function showMode() {
            var checked = form.querySelector('input[name=appointment_mode]:checked');
            var mode = checked ? checked.value : null;

            sections.forEach(function (section) {
                var active = section.dataset.modeSection === mode;
                section.hidden = !active;
                section.querySelectorAll('input, button').forEach(function (field) { field.disabled = !active; });
            });

            if (mode === 'in_person') {
                initMap();
                map.invalidateSize();
            }
        }

        modeInputs.forEach(function (input) { input.addEventListener('change', showMode); });

        // In person: the place can be typed and found, or picked on the map, which fills in the address.
        var address = form.querySelector('#address');
        var latitude = form.querySelector('[data-latitude]');
        var longitude = form.querySelector('[data-longitude]');
        var status = form.querySelector('[data-map-status]');
        var findButton = form.querySelector('[data-find-address]');
        var marker = null;
        var nominatim = 'https://nominatim.openstreetmap.org/';
        var language = document.documentElement.lang || navigator.language;

        function initMap() {
            if (map || typeof L === 'undefined') {
                return;
            }

            var hasPoint = latitude.value !== '' && longitude.value !== '';
            map = L.map('appointment-map').setView(hasPoint ? [latitude.value, longitude.value] : [38.5, 23.8], hasPoint ? 16 : 6);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            }).addTo(map);

            if (hasPoint) {
                placeMarker(L.latLng(latitude.value, longitude.value));
            }

            map.on('click', function (event) {
                placeMarker(event.latlng);
                pick(event.latlng);
            });
        }

        function placeMarker(latlng) {
            if (marker) {
                marker.setLatLng(latlng);
                return;
            }

            marker = L.marker(latlng, { draggable: true }).addTo(map);
            marker.on('dragend', function () { pick(marker.getLatLng()); });
        }

        function setPoint(latlng) {
            latitude.value = latlng ? latlng.lat.toFixed(7) : '';
            longitude.value = latlng ? latlng.lng.toFixed(7) : '';
        }

        function lookup(path, params) {
            params.format = 'jsonv2';
            params['accept-language'] = language;

            return fetch(nominatim + path + '?' + new URLSearchParams(params), { headers: { Accept: 'application/json' } })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error(response.statusText);
                    }
                    return response.json();
                });
        }

        // A point picked on the map: keep it and look up its address.
        function pick(latlng) {
            setPoint(latlng);
            status.textContent = 'Looking up the address…';

            lookup('reverse', { lat: latlng.lat, lon: latlng.lng })
                .then(function (place) {
                    if (place && place.display_name) {
                        address.value = place.display_name;
                        status.textContent = 'Address filled in from the map. You can still edit it.';
                    } else {
                        status.textContent = 'No address found here; type it in.';
                    }
                })
                .catch(function () { status.textContent = 'Could not look up the address; type it in.'; });
        }

        // A typed address: find it and show it on the map.
        function find() {
            var query = address.value.trim();
            if (query === '' || !map) {
                return;
            }

            status.textContent = 'Searching…';

            lookup('search', { q: query, limit: 1 })
                .then(function (places) {
                    if (!places.length) {
                        status.textContent = 'Address not found on the map; it will be saved as typed.';
                        return;
                    }

                    var latlng = L.latLng(places[0].lat, places[0].lon);
                    placeMarker(latlng);
                    setPoint(latlng);
                    map.setView(latlng, 16);
                    status.textContent = 'Found on the map. Drag the pin if it is not quite right.';
                })
                .catch(function () { status.textContent = 'Could not search the map; the address will be saved as typed.'; });
        }

        findButton.addEventListener('click', find);

        address.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                find();
            }
        });

        // Typing by hand means the old pin may no longer match.
        address.addEventListener('input', function () {
            setPoint(null);
            if (marker) {
                marker.remove();
                marker = null;
            }
        });

        showMode();
    })();
</script>
