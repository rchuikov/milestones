# MyMilestones

MyMilestones is a personal milestone and elapsed-time tracker built with Laravel. It keeps track of time since meaningful events, preserves period history, and highlights recurring anniversaries.

The project is intentionally small in its domain model and straightforward in its Laravel structure.

## Features

- Account registration, email verification, login, and password recovery.
- Profile editing, including email and password changes with current-password verification.
- Personal milestones owned by the authenticated user.
- Optional custom colors for milestone cards.
- Live elapsed-time counters for active periods.
- Start, Stop, and Reset operations with period history.
- Read-only history with individual periods and total tracked duration.
- Anniversary detection for:
  - round days;
  - five-day marks;
  - weeks;
  - calendar months;
  - calendar years.
- Per-milestone anniversary display settings.
- Interactive Artisan command for creating verified users, including users with the `admin` role.

The `admin` role is stored in the user model, but there is currently no separate admin interface.

## Domain

A user owns milestones. Each milestone has one or more periods.

A period is active while `finished_at` is `null`.

| Operation | Behavior |
| --- | --- |
| Start | Creates a new active period for a stopped milestone. |
| Stop | Finishes the active period. |
| Reset | Finishes the active period and starts a new one at the same timestamp. |

The initial start time can be edited only while the milestone has exactly one period and that period is still active. Historical periods are read-only in the interface.

## Stack

- **Backend:** PHP 8.5, Laravel 13, Eloquent, MySQL 8.4.
- **Frontend:** Blade, Tailwind CSS 4, Vite 8, Blade Heroicons.
- **Date calculations:** Carbon on the server and `@js-temporal/polyfill` in the browser.
- **Local development:** Laravel Sail, Docker Compose, Mailpit.
- **Development tools:** PHPUnit 12, Laravel Pint.

## Local setup

### Requirements

- Git.
- Docker with Docker Compose v2.
- A Bash-compatible shell. On Windows, WSL2 is recommended.

PHP, Composer, Node.js, MySQL, and Mailpit run through the local Docker environment.

### 1. Prepare the checkout

Clone the repository, open its root directory, and create the local environment file:

```bash
cp .env.example .env
```

### 2. Bootstrap Composer dependencies

A fresh checkout does not contain `vendor/bin/sail`, so install the locked Composer dependencies with a temporary Composer container:

```bash
docker run --rm \
    --user "$(id -u):$(id -g)" \
    --env COMPOSER_HOME=/tmp/composer \
    --volume "$PWD:/app" \
    --workdir /app \
    composer:2 \
    install --ignore-platform-reqs --no-scripts --no-interaction
```

### 3. Start Sail and initialize the application

```bash
./vendor/bin/sail up -d
./vendor/bin/sail composer install
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm ci
./vendor/bin/sail npm run build
```

If MySQL is still starting when migrations run, wait until it is healthy and run the migration command again.

Open the application at the URL configured by `APP_URL` in `.env`.

For frontend development, run Vite separately:

```bash
./vendor/bin/sail npm run dev -- --host 0.0.0.0
```

### 4. Create an account

You can register through the application. Verification and password-reset emails are captured by Mailpit in the local environment.

Alternatively, create an already verified user interactively:

```bash
./vendor/bin/sail artisan user:create
```

The command prompts for a name, email, role, password, and password confirmation.

## Common commands

Run project commands through Sail from the repository root:

```bash
# Start or stop the local environment.
./vendor/bin/sail up -d
./vendor/bin/sail down

# Apply migrations and inspect their status.
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan migrate:status

# Create a verified user or admin.
./vendor/bin/sail artisan user:create

# Run the test suite.
./vendor/bin/sail artisan test

# Rebuild frontend assets.
./vendor/bin/sail npm run build

# Format PHP code.
./vendor/bin/sail pint
```

## Tests

```bash
# Backend unit and feature tests.
./vendor/bin/sail artisan test

# JavaScript timer tests (Node.js test runner, no additional dependencies).
./vendor/bin/sail npm test
```

Backend tests cover registration, login/logout, email verification, password recovery, Google sign-in and account linking, profile updates and first-password setup, milestone ownership and validation, period transitions and transaction rollback, history, anniversaries, duration rendering, user creation, and the Google-account migration rollback.

The PHP suite uses the dedicated MySQL database named `testing`. Sail creates it on first initialization of the MySQL volume; an existing volume may require it to be created separately with access for the configured database user. Tests rebuild this database and roll back their changes. Do not store application data there. A guard stops the suite if cached configuration points to another database; clear configuration with `./vendor/bin/sail artisan config:clear` if needed.

Google responses and mail notifications are faked, so the suite does not need Google credentials or an external mail server. JavaScript tests use controlled time and a minimal DOM to exercise the actual timer module, including daylight-saving transitions and midnight reloads. They use Node's experimental VM modules to isolate the browser module; no real browser or visual layout checks are included.

## Project status

MyMilestones is under active development. The core authentication, profile, milestone tracking, period history, anniversary, and card-color functionality is implemented.
