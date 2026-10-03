{{--
    A list of optional inputs that starts with only the filled-in rows; "+" adds a row (up to $max)
    and each row can be removed. Rows keep their original keys so validation errors stay on the right row.

    Expects: $name, $legend, $max, $values, $type, $itemLabel, $addLabel, and optionally $attributes.
--}}
@php
    $attributes ??= '';
    // Show rows that have a value, plus any blank row that failed validation.
    $rows = collect($values)->filter(fn ($value, $key) => filled($value) || $errors->has("$name.$key"));
    $nextKey = $rows->keys()->map(fn ($key) => (int) $key)->max() + 1;
@endphp

<fieldset class="optional-list" data-optional-list data-max="{{ $max }}" data-next-key="{{ $nextKey }}">
    <legend>{{ $legend }} <span class="muted">(optional, up to {{ $max }})</span></legend>
    @error($name) <div class="error">{{ $message }}</div> @enderror

    <div data-rows>
        @foreach ($rows as $key => $value)
            <div class="list-row" data-row>
                <div class="list-row-fields">
                    <label for="{{ $name }}-{{ $key }}" class="visually-hidden">{{ $itemLabel }}</label>
                    <input id="{{ $name }}-{{ $key }}" type="{{ $type }}" name="{{ $name }}[{{ $key }}]" value="{{ $value }}" {!! $attributes !!}>
                    <button type="button" class="link danger" data-remove>Remove</button>
                </div>
                @error("$name.$key") <div class="error">{{ $message }}</div> @enderror
            </div>
        @endforeach
    </div>

    <template>
        <div class="list-row" data-row>
            <div class="list-row-fields">
                <label for="{{ $name }}-__KEY__" class="visually-hidden">{{ $itemLabel }}</label>
                <input id="{{ $name }}-__KEY__" type="{{ $type }}" name="{{ $name }}[__KEY__]" value="" {!! $attributes !!}>
                <button type="button" class="link danger" data-remove>Remove</button>
            </div>
        </div>
    </template>

    <button type="button" class="link add-row" data-add @if ($rows->count() >= $max) hidden @endif>+ {{ $addLabel }}</button>
</fieldset>
