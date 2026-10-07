# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Notifier is a Laravel 10 (PHP 8.1+) app that emails users (and up to 3 extra addresses) before dates that matter. UI is server-rendered Blade with plain inline CSS/JS — there is no frontend build step; `package.json`/Vite are unused Laravel scaffolding.

## Commands

```bash
composer install
php artisan migrate
php artisan serve                 # dev server on :8000
php artisan schedule:work         # runs reminders:send every minute locally
php artisan reminders:send        # send due reminders once by hand
php artisan queue:work            # only needed when QUEUE_CONNECTION=database

php artisan test                                  # full suite
php artisan test --filter=ReminderTest            # one class
php artisan test --filter=test_guests_are_redirected_to_login   # one test
./vendor/bin/pint                                 # code style (Laravel Pint, default preset)
```

Tests use in-memory SQLite, the `sync` queue and the `array` mailer (see `phpunit.xml`), so they need no setup. Feature tests use `RefreshDatabase` and fake `Notification`/`Queue` as needed.

## Architecture

**Naming:** the user-facing term is "notification", but the code uses `Reminder` throughout (`User::reminders()`, because `Notifiable` already defines `notifications()`). Routes are `/notifications/...` with route names `reminders.*` and the `{reminder}` parameter.

**Data model:**
- `Reminder` — title, message, channel (`ReminderChannel` enum), timezone; belongs to `User`. Limits: `MAX_EARLY_DATES = 4`, `MAX_RECIPIENTS = 3`.
- `ReminderDate` — one send moment per row: up to 4 early dates plus exactly one with `is_final = true`. Each has its own `status` (`ReminderStatus`: pending/sent/failed) and `sent_at`. `Reminder::status()` aggregates them (failed > pending > sent).
- `ReminderRecipient` — extra email addresses (no account needed).
- Birthdays are `Reminder`s with `type = birthday` (`ReminderType`): `title` holds the person's name, `email` the card's address, plus `birth_day`/`birth_month`/optional `birth_year`. They have their own `BirthdayController`/`BirthdayRequest`/`birthdays/*` views (routes `/notifications/birthdays/...`, names `birthdays.*`) and are listed/deleted with the rest. Each has one final date at `BIRTHDAY_SEND_HOUR` local time; when it is sent, `scheduleNextBirthday()` adds next year's and keeps only the last sent row. The card goes only to `email`, not the owner. Its look is the `layout` column (`BirthdayLayout` enum: balloons/confetti/cake), each with an HTML view in `emails/birthdays/` sharing `text.blade.php`; `/notifications/birthdays/layouts/{layout}` previews one with sample data.

**Saving:** `ReminderController` store/update both call `syncDates()` and `syncRecipients()` inside a transaction, which delete and recreate the rows — editing replaces the whole schedule with fresh pending dates. `ReminderRequest` holds all input parsing: `datetime-local` values (`Y-m-d\TH:i`) are interpreted in the user's timezone and converted to UTC via `finalDate()` / `earlyDates()`; it also validates future-ness and that early dates precede the final date.

**Timezones:** all `notify_at` values are stored in UTC. The user's timezone (`users.timezone`, set in Settings) is used for input and display via `User::toLocal()`. `User::changeTimezone()` shifts every pending date so its local clock time stays the same.

**Sending pipeline:**
1. `app/Console/Kernel.php` schedules `reminders:send` every minute (`withoutOverlapping`).
2. `SendDueReminders` takes `ReminderDate::due()` (pending and `notify_at <= now()`), marks each date `sent` *before* dispatching, then dispatches one `SendReminderEmail` job for the owner and one per recipient.
3. `SendReminderEmail` (3 tries, backoff 60s/300s, `deleteWhenMissingModels`) sends the `ReminderDue` notification — to the `User` for the owner, or an on-demand `Notification::route()` for recipients. Its `failed()` hook flips the date to `failed` only for the owner's job (or a birthday card); extra recipients' failures don't affect the status.
4. `ReminderDue` changes subject ("Upcoming:" vs "Reminder:") and body depending on whether the date is final and whether the notifiable is the owner (only the owner gets the "View your notifications" link).

Channels are abstracted through `ReminderChannel::driver()` (currently only email → `mail`); adding a channel means a new enum case plus notifiable routing.

**Auth:** custom controllers in `app/Http/Controllers/Auth` (no Breeze/Jetstream). `User` implements `MustVerifyEmail`; all app routes sit behind `auth` + `verified`. Theme (light/dark, `Theme` enum) is stored per user and toggled via `PUT /theme`, which only requires `auth`. Ownership checks go through `ReminderPolicy`.

**Views:** `resources/views/layouts/app.blade.php` holds all shared CSS as custom properties with a `[data-theme=dark]` override — new styles should use those variables. The reminder form is shared by create/edit via `reminders/_form.blade.php` and `_optional-list.blade.php` (the add/remove rows for early dates and recipients).
