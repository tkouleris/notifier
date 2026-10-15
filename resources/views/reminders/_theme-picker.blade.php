{{-- The light/dark email theme choice, with a preview link for each. --}}
@php($selectedTheme = old('email_theme', $reminder->email_theme?->value ?? \App\Enums\Theme::Light->value))
<fieldset class="theme-picker">
    <legend>Email theme</legend>
    @foreach (\App\Enums\Theme::cases() as $theme)
        <label class="theme-option theme-option-{{ $theme->value }}">
            <input type="radio" name="email_theme" value="{{ $theme->value }}" @checked($selectedTheme === $theme->value) required>
            <span class="theme-swatch" aria-hidden="true"></span>
            <strong>{{ $theme->label() }}</strong>
            <a href="{{ route('reminders.preview', $theme) }}" target="_blank" rel="noopener">Preview</a>
        </label>
    @endforeach
</fieldset>
@error('email_theme') <div class="error">{{ $message }}</div> @enderror

@push('styles')
    <style>
        fieldset.theme-picker { border: 0; padding: 0; margin: 1rem 0 0; display: flex; flex-wrap: wrap; gap: .5rem; }
        fieldset.theme-picker legend { padding: 0; margin-bottom: .25rem; font-size: .9rem; }
        .theme-option { flex: 1; display: flex; gap: .6rem; align-items: center; margin: 0; padding: .6rem .75rem; border: 1px solid var(--border); border-radius: 6px; cursor: pointer; }
        .theme-option:has(input:checked) { border-color: var(--link); box-shadow: 0 0 0 1px var(--link); }
        .theme-option input { margin: 0; }
        .theme-option strong { flex: 1; font-weight: normal; }
        .theme-option a { font-size: .85rem; }
        .theme-swatch { width: 1.75rem; height: 1.75rem; flex: none; border-radius: 5px; box-sizing: border-box; }
        /* Each swatch shows its email's page, card and accent colours, the same in both app themes. */
        .theme-option-light .theme-swatch { background: linear-gradient(#1d6fb8 0 4px, #fff 4px) content-box, #f4f5f7; padding: 4px; border: 1px solid #d1d5db; }
        .theme-option-dark .theme-swatch { background: linear-gradient(#f28c28 0 4px, #1a1d23 4px) content-box, #0f1115; padding: 4px; border: 1px solid #374151; }
    </style>
@endpush
