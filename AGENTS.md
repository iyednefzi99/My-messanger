# AGENTS.md — MESSANGER

## Quick start

```sh
# 1. Copy config (not tracked by git)
cp config/config.example.php config/config.php

# 2. Create DB and import schema
mysql -u root -e "CREATE DATABASE messanger CHARACTER SET utf8mb4"
mysql -u root messanger < db/messange.sql

# 3. Run migrations (optional — schema.sql creates the base table)
php migrate.php --apply

# 4. Start built-in server (root must be public/)
php -S localhost:8000 -t public
# Then open http://localhost:8000/register.php
```

## Critical conventions

- **DB names**: database = `messanger`, table = `messange` (no trailing 'r'). Both spellings are used throughout code — do not "fix" either.
- **Web root**: `public/` only. Never serve `src/`, `config/`, or `db/` from the web. Each has an `.htaccess` that denies access.
- **All HTML output must be escaped**: `htmlspecialchars($v, ENT_QUOTES, 'UTF-8')`. Two XSS holes were already fixed in commit `0142666` — do not reintroduce raw `echo`.
- **All SQL must use `mysqli_prepare` + `mysqli_stmt_bind_param`**. No string interpolation on user input (commit `f53d3f0`).
- **Timezone**: `Africa/Tunis` from `config/config.php`, applied to both PHP (`date_default_timezone_set`) and MySQL (`SET time_zone`). The offset is recalculated on each connection in `database.php:65`.
- **Session**: Started in `src/auth.php`. Cookies are `httponly; samesite=Lax; secure` only on HTTPS.
- **CSRF**: Forms include a hidden `csrf_token` field. Validate with `csrf_check($_POST['csrf_token'] ?? '')` before any state-changing operation (checked in `process.php:30`, `register.php:11`, `login.php:11`).
- **Message length**: Max 2000 chars (`MESSAGE_MAX_LENGTH` constant in `src/auth.php:9`). Validated both client-side (`maxlength` attribute) and server-side.

## Authentication flow

- `public/login.php` / `public/register.php` — collect username/password, set session via `auth.php:login_user()`.
- `public/index.php` — guards with `require_login()` (from `src/auth.php:52`). Shows messages; `COALESCE(u.username, m.user)` handles pre-existing messages without `user_id`.
- `public/messages.php` — JSON polling endpoint; also guards with `is_logged_in()`.
- `public/process.php` — handles form POST after auth; inserts into `messange` with user's `user_id` and `username` from session.

## Migrations

```sh
php migrate.php              # apply pending migrations
php migrate.php --status     # list applied / pending
php migrate.php --baseline   # mark pending as applied without running (adopt existing DB)
```

Migrations live in `db/migration_*.sql` and are tracked in a `schema_migrations` table. The starter schema `db/messange.sql` is imported once manually before running `migrate.php`.

## Login throttling (per `src/auth.php`)

- Login: max 5 attempts per account / 20 per IP, window 15 min.
- Register: max 5 attempts per IP, window 60 min.
- Both use `login_attempts` / `registration_attempts` tables. IP-based checks use `REMOTE_ADDR` only — do not trust `X-Forwarded-For` unless behind a trusted reverse proxy.

## Error handling

- `mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)` in `database.php:6` — mysqli throws exceptions on errors.
- Global exception handler in `database.php:10-16` returns 503 with a neutral message.
- `config/config.php` is required; if missing or incomplete, the app dies with 503.