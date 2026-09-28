# Local Setup and Operations

## Requirements

- PHP 8.3 with PDO MySQL and the extensions required by Laravel.
- Composer.
- Node.js/npm compatible with the committed lockfile.
- MySQL 8.0.16 or later. SQLite is not supported by the current MySQL-specific check constraints and stored generated columns.

## Local clean install

1. Create an empty, disposable MySQL database, for example `room_rental`.
2. Copy `.env.example` to `.env`; set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` for the local MySQL instance. Example credentials are local placeholders, not production credentials.
3. Run:

   ```text
   composer install
   npm ci
   php artisan key:generate
   php artisan migrate:fresh --seed
   php artisan storage:link
   npm run build
   ```

   PowerShell users can copy the example with `Copy-Item .env.example .env`.
4. Start through a Laragon virtual host pointing at `public/`, or use `php artisan serve` for local smoke testing.

`migrate:fresh --seed` is destructive. Use it only for a new disposable local/demo/test database. Never use it on production/shared data. For existing deployments use the reviewed migration path (`php artisan migrate --force`); the normal `DatabaseSeeder` contains reference initialization only. Room-category defaults are inserted only into an empty category table because those Admin-managed rows have no stable seed key. Do not turn `composer setup` into a destructive fresh-migration command.

## Super Admin and demo setup

Production-safe reference seeding does not create users. Create the initial production Super Admin explicitly after references are available:

```text
php artisan users:bootstrap-super-admin
```

Provide operator-controlled credentials through the hidden prompts. See [`demo.md`](demo.md) for the isolated demo sequence, demo-only credentials, location limitation, and reset procedure. Run DemoSeeder before bootstrapping a Super Admin into the same local demo database; DemoSeeder deliberately refuses any database with application data and refuses production.

## Storage and assets

Listing images use the `public` filesystem disk. Run `php artisan storage:link` so URLs under `/storage` resolve. `npm run build` compiles production Vite assets. `npm run dev` is for local asset development.

## Tests and formatting

Feature tests require a dedicated local MySQL database named `room_rental_test`. The test base class rejects a non-MySQL connection or different database name; also verify `DB_HOST` is local/disposable before running refresh-based tests. Do not run parallel `RefreshDatabase` workers against the same database.

```text
php artisan test
vendor/bin/pint
vendor/bin/pint --test
npm run build
git diff --check
composer audit
npm audit
```

## Production settings

- Set `APP_ENV=production`, a private generated `APP_KEY`, and `APP_DEBUG=false`.
- Serve the application over HTTPS. Set `SESSION_SECURE_COOKIE=true` in the HTTPS deployment environment; leave it unset/false for local plain-HTTP development. Keep `SESSION_HTTP_ONLY=true` and `SESSION_SAME_SITE=lax` unless a reviewed deployment need differs.
- Application responses set `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, and `Referrer-Policy: strict-origin-when-cross-origin`. Mirror the appropriate headers at the proxy/web server for static files served outside Laravel. Configure HSTS at the HTTPS edge after TLS is enabled and disable PHP `expose_php` in production. A Content Security Policy requires separate review against the current frontend assets before rollout.
- Use deployment-owned DB credentials; never commit `.env` or real credentials.
- `.env.example` uses `QUEUE_CONNECTION=sync`. Current notification and side-effect code is synchronous/in-system; no queue worker is required. Reassess only if a future approved implementation adds queued work.

## Scheduler

`appointments:auto-cancel` is scheduled every 15 minutes. Production must invoke Laravel Scheduler once per minute, for example:

```cron
* * * * * cd /path/to/RoomRental && php artisan schedule:run >> /dev/null 2>&1
```

There is intentionally no listing expiry scheduler. Public eligibility checks expiry at request time.

## Location deployment limitation

Vietnam moved to a two-level local-government model from 2025. V1 persists the approved legacy `province → district → ward` hierarchy. The small demo fixture is not current authoritative Vietnamese geography and is not included by the production-safe seeder. Production requiring up-to-date real-world Vietnamese administrative units needs a future reviewed location-domain redesign/migration; do not import a current two-level dataset into the three-level schema.
