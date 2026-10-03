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
background with retries, use the database queue. Its `jobs` table is created by the migrations
from the installation step, so you only need to switch the connection in `.env`:

```dotenv
QUEUE_CONNECTION=database
```

Then keep a worker running. Locally, a terminal is enough. On a server, use Supervisor (see below):

```bash
php artisan queue:work
```

Each email job retries itself up to 3 times, waiting 1 and then 5 minutes between attempts.

A date is marked *sent* as soon as its emails are queued. If any of its emails still fails
after all retries, the date is marked *failed*.

### 3. Keep the worker running with Supervisor (Linux)

`queue:work` is a long-running process. If it stops, because of a crash, a reboot or a deploy,
reminders are queued but no email goes out. [Supervisor](http://supervisord.org/) starts the worker
at boot and restarts it whenever it exits.

The examples below assume the app lives in `/var/www/notifier` and the web server runs as
`www-data`. Change the paths and user to match your server.

**1. Install Supervisor**

```bash
# Debian / Ubuntu
sudo apt update && sudo apt install supervisor

# RHEL / Rocky / AlmaLinux / Fedora
sudo dnf install supervisor
sudo systemctl enable --now supervisord
```

**2. Create the worker config**

On Debian/Ubuntu, create `/etc/supervisor/conf.d/notifier-worker.conf`.
On RHEL-based systems, create `/etc/supervisord.d/notifier-worker.ini`.

```ini
[program:notifier-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/notifier/artisan queue:work database --sleep=3 --max-time=3600
directory=/var/www/notifier
user=www-data
numprocs=1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=60
redirect_stderr=true
stdout_logfile=/var/www/notifier/storage/logs/worker.log
```

- `--max-time=3600` makes the worker exit after an hour and Supervisor starts a fresh one, which keeps memory use in check.
- `numprocs=1` is plenty for most installs. Raise it to send more emails in parallel.
- `stopwaitsecs` gives a running email time to finish before the worker is stopped.
- Use `which php` to find the PHP path if it isn't `/usr/bin/php`.

**3. Load and start it**

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start "notifier-worker:*"
```

**4. Check that it's running**

```bash
sudo supervisorctl status
# notifier-worker:notifier-worker_00   RUNNING   pid 12345, uptime 0:01:02

tail -f /var/www/notifier/storage/logs/worker.log
```

**5. After every deploy**

Workers keep the old code in memory, so tell them to restart once they finish their current job.
Supervisor starts them again with the new code:

```bash
php artisan queue:restart
```

If you change the Supervisor config itself, run `sudo supervisorctl reread && sudo supervisorctl update` again.

**Optional: run the scheduler with Supervisor instead of cron**

If you prefer not to use cron, Supervisor can also keep `schedule:work` running.
Add a second program to the same file:

```ini
[program:notifier-scheduler]
command=/usr/bin/php /var/www/notifier/artisan schedule:work
directory=/var/www/notifier
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/www/notifier/storage/logs/scheduler.log
```

Use either cron or this program, not both. Running both would start the scheduler twice every minute.

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
