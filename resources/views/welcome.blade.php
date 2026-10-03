@extends('layouts.app')

@section('title', 'Never miss what matters')
@section('main_class', 'landing')

@push('styles')
    <style>
        main.landing { max-width: 960px; margin: 0 auto; padding: 0 1.5rem 4rem; background: none; box-shadow: none; }
        .landing-top { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 0; }
        .landing-top .brand { font-weight: 700; font-size: 1.1rem; color: var(--text); text-decoration: none; }
        .landing-top nav { display: flex; gap: 1rem; align-items: center; }
        .hero { display: grid; grid-template-columns: 1.15fr 1fr; gap: 3rem; align-items: center; padding: 3rem 0 4rem; }
        .eyebrow { display: inline-block; font-size: .8rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--info-text); background: var(--info-bg); padding: .3rem .7rem; border-radius: 999px; }
        .hero h1 { font-size: clamp(2rem, 5vw, 3rem); line-height: 1.1; margin: 1rem 0; letter-spacing: -.02em; }
        .hero .lead { font-size: 1.15rem; line-height: 1.6; color: var(--muted); margin: 0 0 2rem; }
        .cta { display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; }
        .cta a.button { padding: .75rem 1.4rem; font-weight: 600; }
        .timeline { background: var(--surface); border-radius: 12px; padding: 1.5rem; box-shadow: 0 10px 30px var(--shadow); }
        .timeline h2 { font-size: 1.05rem; margin: 0 0 .25rem; }
        .timeline ol { list-style: none; margin: 1.25rem 0 0; padding: 0 0 0 1.25rem; border-left: 2px solid var(--divider); }
        .timeline li { position: relative; padding: 0 0 1.1rem 1rem; }
        .timeline li:last-child { padding-bottom: 0; }
        .timeline li::before { content: ""; position: absolute; left: calc(-1.25rem - 7px); top: .3rem; width: 12px; height: 12px; border-radius: 50%; background: var(--surface); border: 2px solid var(--border); }
        .timeline li.done::before { background: var(--success-text); border-color: var(--success-text); }
        .timeline li.final::before { background: var(--primary-bg); border-color: var(--primary-bg); }
        .timeline .when { font-weight: 600; }
        .people { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--divider); }
        .chip { font-size: .8rem; padding: .2rem .6rem; border-radius: 999px; background: var(--bg); color: var(--muted); }
        section.block { padding: 3rem 0; border-top: 1px solid var(--divider); }
        section.block > h2 { font-size: 1.6rem; margin: 0 0 .5rem; letter-spacing: -.01em; }
        section.block > p.muted { margin: 0 0 2rem; font-size: 1rem; }
        .steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; counter-reset: step; list-style: none; margin: 0; padding: 0; }
        .steps li { counter-increment: step; }
        .steps li::before { content: counter(step); display: grid; place-items: center; width: 2rem; height: 2rem; border-radius: 50%; background: var(--primary-bg); color: var(--primary-text); font-weight: 700; margin-bottom: .75rem; }
        .steps h3, .features h3 { margin: 0 0 .4rem; font-size: 1.05rem; }
        .steps p, .features p { margin: 0; color: var(--muted); line-height: 1.55; }
        .features { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; }
        .feature { background: var(--surface); border-radius: 10px; padding: 1.25rem; box-shadow: 0 1px 3px var(--shadow); }
        .feature .icon { font-size: 1.5rem; display: block; margin-bottom: .5rem; }
        .uses { display: flex; flex-wrap: wrap; gap: .6rem; }
        .uses span { background: var(--surface); border: 1px solid var(--divider); border-radius: 999px; padding: .45rem .9rem; }
        .closing { text-align: center; background: var(--surface); border-radius: 12px; padding: 2.5rem 1.5rem; box-shadow: 0 1px 3px var(--shadow); }
        .closing h2 { margin: 0 0 .5rem; }
        .closing .cta { justify-content: center; margin-top: 1.5rem; }
        @media (max-width: 800px) {
            .hero { grid-template-columns: 1fr; gap: 2rem; padding-top: 1.5rem; }
            .steps, .features { grid-template-columns: 1fr; }
            main.landing { margin: 0 auto; padding: 0 1rem 3rem; }
        }
    </style>
@endpush

@section('content')
    @guest
        <div class="landing-top">
            <a class="brand" href="{{ url('/') }}">{{ config('app.name') }}</a>
            <nav>
                <a href="{{ route('login') }}">Log in</a>
                <a class="button" href="{{ route('register') }}">Sign up</a>
            </nav>
        </div>
    @endguest

    <section class="hero">
        <div>
            <span class="eyebrow">Email reminders, on your schedule</span>
            <h1>Never miss the dates that matter.</h1>
            <p class="lead">
                {{ config('app.name') }} emails you before an important date: a deadline, a renewal, a birthday, an appointment.
                Choose when you want to hear about it, add the people who should know too, and we'll send
                the reminders on time, in your timezone.
            </p>
            <div class="cta">
                @auth
                    <a class="button" href="{{ route('reminders.index') }}">Go to my notifications</a>
                    <a href="{{ route('reminders.create') }}">Create a notification &rarr;</a>
                @else
                    <a class="button" href="{{ route('register') }}">Create a free account</a>
                    <a href="{{ route('login') }}">I already have an account &rarr;</a>
                @endauth
            </div>
        </div>

        <div class="timeline" aria-label="Example notification">
            <h2>Passport renewal</h2>
            <div class="muted">Bring two photos and the old passport.</div>
            <ol>
                <li class="done"><span class="when">Mon, May 4 · 09:00</span><div class="muted">Early reminder · Sent</div></li>
                <li class="done"><span class="when">Mon, May 25 · 09:00</span><div class="muted">Early reminder · Sent</div></li>
                <li><span class="when">Fri, Jun 5 · 09:00</span><div class="muted">Early reminder · Coming up</div></li>
                <li class="final"><span class="when">Fri, Jun 12 · 09:00</span><div class="muted">Final date</div></li>
            </ol>
            <div class="people">
                <span class="chip">You</span>
                <span class="chip">partner@example.com</span>
                <span class="chip">Europe/Athens</span>
            </div>
        </div>
    </section>

    <section class="block">
        <h2>How it works</h2>
        <p class="muted">Three steps.</p>
        <ol class="steps">
            <li>
                <h3>Add the date</h3>
                <p>Give it a title, an optional note, and the final date and time, entered in your own timezone.</p>
            </li>
            <li>
                <h3>Pick your reminders</h3>
                <p>Add up to four earlier reminders, like a week or a day before, and up to three other people to notify.</p>
            </li>
            <li>
                <h3>Get the emails</h3>
                <p>Each reminder is emailed at its time to you and everyone you added. You can see what was sent on your list.</p>
            </li>
        </ol>
    </section>

    <section class="block">
        <h2>What you get</h2>
        <p class="muted">Simple to use, and reliable.</p>
        <div class="features">
            <div class="feature">
                <span class="icon" aria-hidden="true">⏰</span>
                <h3>Early reminders</h3>
                <p>A final date plus up to four reminders before it.</p>
            </div>
            <div class="feature">
                <span class="icon" aria-hidden="true">👥</span>
                <h3>Notify others</h3>
                <p>Add up to three people by email. They don't need an account, and the email says who asked us to remind them.</p>
            </div>
            <div class="feature">
                <span class="icon" aria-hidden="true">🌍</span>
                <h3>Your timezone</h3>
                <p>Times follow the timezone in your settings. If you move, your reminders keep their clock time: 09:30 stays 09:30.</p>
            </div>
            <div class="feature">
                <span class="icon" aria-hidden="true">✉️</span>
                <h3>Reliable delivery</h3>
                <p>Every email is sent on its own and retried if it fails, so one problem doesn't stop the rest.</p>
            </div>
            <div class="feature">
                <span class="icon" aria-hidden="true">✅</span>
                <h3>Clear status</h3>
                <p>Each date shows whether it's pending, sent or failed.</p>
            </div>
            <div class="feature">
                <span class="icon" aria-hidden="true">🌙</span>
                <h3>Light or dark</h3>
                <p>Switch between light and dark mode. Your choice is saved to your account.</p>
            </div>
        </div>
    </section>

    <section class="block">
        <h2>Some ways to use it</h2>
        <p class="muted">Anything with a date you can't miss.</p>
        <div class="uses">
            <span>📄 Document &amp; visa renewals</span>
            <span>🎂 Birthdays &amp; anniversaries</span>
            <span>🩺 Doctor &amp; dentist appointments</span>
            <span>💳 Bills &amp; subscription renewals</span>
            <span>🚗 Car service &amp; insurance</span>
            <span>📚 Exams &amp; project deadlines</span>
        </div>
    </section>

    <section class="block">
        <div class="closing">
            <h2>Set it once. We'll remind you.</h2>
            <p class="muted">It takes about a minute to schedule your first notification.</p>
            <div class="cta">
                @auth
                    <a class="button" href="{{ route('reminders.create') }}">Create a notification</a>
                @else
                    <a class="button" href="{{ route('register') }}">Get started</a>
                @endauth
            </div>
        </div>
    </section>
@endsection
