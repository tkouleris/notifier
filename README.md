<p align="center">
    <img src="public/images/logo-full.png" alt="Notifier" width="160">
</p>

# Notifier

Notifier emails you before the dates that matter: deadlines, renewals, birthdays, appointments.
You set a final date, choose up to four earlier reminders, add the people who should know too,
and Notifier sends each email on time, in your timezone.

## Features

- **Final date plus early reminders**: every notification has a final date and up to 4 optional reminders before it.
- **Notify other people**: add up to 3 extra email addresses per notification. They don't need an account, and the email tells them who asked for the reminder.
- **Your timezone**: you pick your timezone in Settings, and all dates are entered and shown in it. If you change it, notifications not yet sent keep their clock time (09:30 stays 09:30 in the new timezone).
- **Reliable delivery**: each email goes out as its own queued job and is retried up to 3 times, so one failing address doesn't block the others.
- **Status tracking**: every date shows whether it is *pending*, *sent* or *failed*.
- **Accounts**: registration with email verification, plus a profile page to change your username, password and optional birthday.
- **Light and dark mode**, saved per user.

## Requirements

- PHP 8.1 or newer with the usual Laravel extensions (`mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`)
- [Composer](https://getcomposer.org/)
- A database: MySQL / MariaDB (the default in `.env.example`), PostgreSQL or SQLite
- A mail service: any SMTP server, or Mailgun (the Mailgun mailer is already installed)

Node.js is **not** needed. The pages use plain CSS, with no build step.

## Installation

1. **Clone the repository and install dependencies**

   ```bash
   git clone https://github.com/tkouleris/notifier.git
   cd notifier
   composer install
   ```

2. **Create the environment file and app key**

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Configure `.env`**

   ```dotenv
   APP_NAME=Notifier
   APP_URL=http://localhost:8000

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=notifier
   DB_USERNAME=root
   DB_PASSWORD=

   MAIL_MAILER=smtp
   MAIL_HOST=smtp.example.com
   MAIL_PORT=587
   MAIL_USERNAME=your-username
   MAIL_PASSWORD=your-password
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS="notifier@example.com"
   MAIL_FROM_NAME="${APP_NAME}"
   ```

   To send through Mailgun's API instead of SMTP:

   ```dotenv
   MAIL_MAILER=mailgun
   MAILGUN_DOMAIN=mg.example.com
   MAILGUN_SECRET=your-mailgun-api-key
   MAILGUN_ENDPOINT=api.mailgun.net   # or api.eu.mailgun.net for EU domains
   ```

   `APP_URL` must be the address users open the app at. It is used for the links in
   verification and reminder emails.

4. **Create the database tables**

   ```bash
   php artisan migrate
   ```

5. **Start the app**

   ```bash
   php artisan serve
   ```

   Open http://localhost:8000 and create an account. Registration sends a verification email.
   You need to verify your address before you can create notifications.

## Sending reminders

Reminders are sent by the `reminders:send` command. It runs every minute through Laravel's
scheduler, finds every date whose time has come, and queues one email job per person.

### 1. Run the scheduler

**Locally**, keep this running in a terminal:

```bash
php artisan schedule:work
```

**On a server**, add a single cron entry:

```cron
* * * * * cd /path/to/notifier && php artisan schedule:run >> /dev/null 2>&1
```

You can also send due reminders once by hand:

```bash
php artisan reminders:send
```

### 2. Run a queue worker (recommended)

With the default `QUEUE_CONNECTION=sync`, emails are sent immediately inside the
`reminders:send` command. That works, but failed emails are not retried. To send them in the
background with retries, use the database queue:

```bash
php artisan queue:table
php artisan migrate
```

```dotenv
QUEUE_CONNECTION=database
```

Then keep a worker running (on a server, use Supervisor or systemd to keep it alive):

```bash
php artisan queue:work
```

Each email job retries itself up to 3 times, waiting 1 and then 5 minutes between attempts.

A date is marked *sent* as soon as its emails are queued. If any of its emails still fails
after all retries, the date is marked *failed*.

## How to use it

1. **Sign up and verify your email.** Your timezone is taken from your browser when you register.
2. **Check your timezone** under **Settings**. Every date you enter is read in this timezone.
3. **Create a notification** from **Notifications → New notification**:
   - **Title** and an optional **message**, which are included in the email.
   - **Final date**: the date and time of the event itself.
   - **Reminders before the final date** *(optional)*: press **+ Add reminder** for up to 4 earlier emails, for example a week and a day before.
   - **Notify me by**: the delivery channel (email).
   - **Also notify** *(optional)*: press **+ Add person** to add up to 3 more email addresses.
4. **Follow progress** on the Notifications list. Each date shows its status, and the notification
   as a whole shows *failed* if any date failed, *pending* while any date is still to come, and
   *sent* when everything has gone out.
5. **Edit or delete** a notification at any time. Saving replaces the whole schedule with the
   dates in the form. Dates that were already sent are not shown and are not sent again.
6. **Profile** lets you change your username, birthday and password (your current password is
   required). The theme switch in the navbar toggles light and dark mode.

### Example

> **Passport renewal**, final date *Fri, Jun 12 at 09:00*, reminders on *May 4*, *May 25* and *Jun 5*,
> also notifying *partner@example.com*.
>
> You and your partner get an "Upcoming: Passport renewal" email on each reminder date, saying
> when the final date is, and a "Reminder: Passport renewal" email on the final date.

## Running the tests

```bash
php artisan test
```

The tests use an in-memory SQLite database, the `sync` queue and a fake mailer, so they need no
setup and send no real email.

## Built with

- [Laravel 10](https://laravel.com/)
- Blade templates with plain CSS

## License

Notifier is open-source software licensed under the [MIT license](https://opensource.org/licenses/MIT).
