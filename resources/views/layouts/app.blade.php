<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f4f5f7; margin: 0; color: #1f2937; }
        main { max-width: 420px; margin: 4rem auto; background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        label { display: block; margin-top: 1rem; font-size: .9rem; }
        input[type=text], input[type=email], input[type=password] { width: 100%; padding: .5rem; margin-top: .25rem; box-sizing: border-box; border: 1px solid #d1d5db; border-radius: 4px; }
        button { margin-top: 1.25rem; padding: .55rem 1.1rem; background: #111827; color: #fff; border: 0; border-radius: 4px; cursor: pointer; }
        .error { color: #b91c1c; font-size: .85rem; margin-top: .25rem; }
        .status { background: #ecfdf5; color: #065f46; padding: .75rem; border-radius: 4px; margin-bottom: 1rem; }
        .error-box { background: #fef2f2; color: #991b1b; padding: .75rem; border-radius: 4px; margin-bottom: 1rem; }
        .muted { color: #6b7280; font-size: .9rem; }
        main.wide { max-width: 860px; }
        textarea, select, input[type=datetime-local] { width: 100%; padding: .5rem; margin-top: .25rem; box-sizing: border-box; border: 1px solid #d1d5db; border-radius: 4px; font: inherit; }
        a.button { display: inline-block; padding: .55rem 1.1rem; background: #111827; color: #fff; border-radius: 4px; text-decoration: none; }
        button.link { margin: 0; padding: 0; background: none; color: #2563eb; font: inherit; }
        button.link.danger { color: #b91c1c; }
        .header { display: flex; flex-wrap: wrap; gap: 1rem; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .header h1 { margin: 0; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: .65rem .5rem; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        th { font-size: .8rem; text-transform: uppercase; color: #6b7280; }
        .actions { white-space: nowrap; }
        .actions form { display: inline; margin-left: .75rem; }
        .badge { display: inline-block; padding: .15rem .5rem; border-radius: 999px; font-size: .8rem; }
        .badge-pending { background: #eff6ff; color: #1e40af; }
        .badge-sent { background: #ecfdf5; color: #065f46; }
        .badge-failed { background: #fef2f2; color: #991b1b; }
        .logout { margin-top: 2rem; display: flex; gap: 1rem; align-items: center; }
        @media (max-width: 600px) { main { margin: 1rem; padding: 1.25rem; } }
    </style>
</head>
<body>
<main class="@yield('main_class')">
    @yield('content')
</main>
</body>
</html>
