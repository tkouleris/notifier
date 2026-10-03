@php($theme = auth()->user()?->theme ?? \App\Enums\Theme::Light)
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $theme->value }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f5f7;
            --surface: #fff;
            --text: #1f2937;
            --muted: #6b7280;
            --border: #d1d5db;
            --divider: #e5e7eb;
            --shadow: rgba(0,0,0,.1);
            --primary-bg: #111827;
            --primary-text: #fff;
            --link: #2563eb;
            --danger: #b91c1c;
            --success-bg: #ecfdf5;
            --success-text: #065f46;
            --danger-bg: #fef2f2;
            --danger-text: #991b1b;
            --info-bg: #eff6ff;
            --info-text: #1e40af;
        }
        [data-theme=dark] {
            color-scheme: dark;
            --bg: #0f1115;
            --surface: #1a1d23;
            --text: #e5e7eb;
            --muted: #9ca3af;
            --border: #374151;
            --divider: #2a2f37;
            --shadow: rgba(0,0,0,.5);
            --primary-bg: #e5e7eb;
            --primary-text: #111827;
            --link: #60a5fa;
            --danger: #f87171;
            --success-bg: #052e22;
            --success-text: #6ee7b7;
            --danger-bg: #3b1212;
            --danger-text: #fca5a5;
            --info-bg: #172554;
            --info-text: #93c5fd;
        }
        body { font-family: system-ui, sans-serif; background: var(--bg); margin: 0; color: var(--text); }
        a { color: var(--link); }
        main { max-width: 420px; margin: 4rem auto; background: var(--surface); padding: 2rem; border-radius: 8px; box-shadow: 0 1px 3px var(--shadow); }
        label { display: block; margin-top: 1rem; font-size: .9rem; }
        input[type=text], input[type=email], input[type=password], input[type=datetime-local], input[type=date], textarea, select { width: 100%; padding: .5rem; margin-top: .25rem; box-sizing: border-box; border: 1px solid var(--border); border-radius: 4px; font: inherit; background: var(--surface); color: var(--text); }
        button { margin-top: 1.25rem; padding: .55rem 1.1rem; background: var(--primary-bg); color: var(--primary-text); border: 0; border-radius: 4px; cursor: pointer; }
        .error { color: var(--danger); font-size: .85rem; margin-top: .25rem; }
        .status { background: var(--success-bg); color: var(--success-text); padding: .75rem; border-radius: 4px; margin-bottom: 1rem; }
        .error-box { background: var(--danger-bg); color: var(--danger-text); padding: .75rem; border-radius: 4px; margin-bottom: 1rem; }
        .muted { color: var(--muted); font-size: .9rem; }
        main.wide { max-width: 860px; }
        a.button { display: inline-block; padding: .55rem 1.1rem; background: var(--primary-bg); color: var(--primary-text); border-radius: 4px; text-decoration: none; }
        button.link { margin: 0; padding: 0; background: none; color: var(--link); font: inherit; }
        button.link.danger { color: var(--danger); }
        .header { display: flex; flex-wrap: wrap; gap: 1rem; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .header h1 { margin: 0; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: .65rem .5rem; border-bottom: 1px solid var(--divider); vertical-align: top; }
        th { font-size: .8rem; text-transform: uppercase; color: var(--muted); }
        .actions { white-space: nowrap; }
        .actions form { display: inline; margin-left: .75rem; }
        .badge { display: inline-block; padding: .15rem .5rem; border-radius: 999px; font-size: .8rem; }
        .badge-pending { background: var(--info-bg); color: var(--info-text); }
        .badge-sent { background: var(--success-bg); color: var(--success-text); }
        .badge-failed { background: var(--danger-bg); color: var(--danger-text); }
        fieldset.optional-list { border: 0; padding: 0; margin: 1rem 0 0; }
        fieldset.optional-list legend { padding: 0; font-size: .9rem; }
        .list-row { margin-top: .5rem; }
        .list-row-fields { display: flex; gap: .75rem; align-items: center; }
        .list-row-fields input { margin-top: 0; flex: 1; min-width: 0; }
        button.add-row { margin-top: .5rem; }
        button[hidden] { display: none; }
        .visually-hidden { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
        .navbar { display: flex; flex-wrap: wrap; gap: .75rem 1.5rem; align-items: center; padding: .75rem 1.5rem; background: var(--surface); border-bottom: 1px solid var(--divider); }
        .navbar .brand, .brand { display: inline-flex; align-items: center; gap: .5rem; font-weight: 600; color: var(--text); text-decoration: none; }
        .brand img { border-radius: 6px; }
        .auth-logo { display: block; width: max-content; margin: 0 auto 1.25rem; }
        .auth-logo img { display: block; border-radius: 12px; }
        .navbar ul { display: flex; flex-wrap: wrap; gap: 1rem; list-style: none; margin: 0; padding: 0; }
        .navbar ul a { color: var(--muted); text-decoration: none; }
        .navbar ul a:hover, .navbar ul a[aria-current=page] { color: var(--text); }
        .navbar ul a[aria-current=page] { font-weight: 600; }
        .navbar-end { display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; margin-left: auto; }
        .navbar-end form { margin: 0; }
        hr.section { border: 0; border-top: 1px solid var(--divider); margin: 2rem 0 1.5rem; }
        .theme-switch { display: flex; }
        .theme-switch fieldset { display: inline-flex; border: 1px solid var(--border); border-radius: 999px; padding: 2px; margin: 0; }
        .theme-switch button { margin: 0; padding: .2rem .75rem; border-radius: 999px; font-size: .8rem; background: none; color: var(--muted); }
        .theme-switch button[aria-pressed=true] { background: var(--primary-bg); color: var(--primary-text); cursor: default; }
        @media (max-width: 600px) { main { margin: 1rem; padding: 1.25rem; } .navbar { padding: .75rem 1rem; } .navbar-end { margin-left: 0; } }
    </style>
    @stack('styles')
</head>
<body>
@auth
    <nav class="navbar" aria-label="Main">
        <a class="brand" href="{{ route('reminders.index') }}">
            <img src="{{ asset('images/logo-mark.png') }}" alt="" width="32" height="32">
            {{ config('app.name') }}
        </a>
        <ul>
            @foreach (['reminders.index' => 'Notifications', 'profile.edit' => 'Profile', 'settings.edit' => 'Settings'] as $route => $label)
                <li><a href="{{ route($route) }}" @if (request()->routeIs(\Illuminate\Support\Str::before($route, '.').'.*')) aria-current="page" @endif>{{ $label }}</a></li>
            @endforeach
        </ul>
        <div class="navbar-end">
            <form method="POST" action="{{ route('theme.update') }}" class="theme-switch">
                @csrf
                @method('PUT')
                <fieldset aria-label="Color theme">
                    @foreach (\App\Enums\Theme::cases() as $option)
                        <button type="submit" name="theme" value="{{ $option->value }}"
                                aria-pressed="{{ $theme === $option ? 'true' : 'false' }}">{{ $option->label() }}</button>
                    @endforeach
                </fieldset>
            </form>
            <span class="muted">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="link">Log out</button>
            </form>
        </div>
    </nav>
@endauth
<main class="@yield('main_class')">
    @yield('content')
</main>
</body>
</html>
