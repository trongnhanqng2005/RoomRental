# RoomRental V1 Architecture

## Stack

- PHP `^8.3`; locked Laravel framework `13.32.0`.
- MySQL 8 with Eloquent and MySQL-specific checks/generated columns.
- Laravel MVC web application: routes, controllers, Form Requests, middleware/policies, models, Blade views.
- Blade UI with Tailwind CSS 4, Preline, Lucide, Plus Jakarta Sans, and Vanilla JavaScript, built with Vite.
- Session authentication; MySQL-backed local session/cache configuration by default in `.env.example`.
- Composer and npm lockfiles provide dependency resolution. PHPUnit/Laravel feature tests run against MySQL.

## Request and code boundaries

`routes/web.php` groups public, auth, renter, landlord, and Admin/Super Admin HTTP workflows. Controllers coordinate requests and views; Form Requests validate input; middleware and policies enforce authentication, capability roles, and ownership. Eloquent models represent persistence and relationships.

Services are used for multi-write transactions, state changes, locking/concurrency, filesystem cleanup, enforcement, and notification side effects. Read-oriented query classes serve listing search and dashboards. Ordinary catalog CRUD remains direct Eloquent. Blade and JavaScript do not own business state transitions.

## Persistence and side effects

MySQL constraints enforce important identity, hierarchy, catalog, moderation-pointer, active-appointment, and pending-report invariants. Application services lock rows and translate expected duplicate-key conflicts where workflows require it. Listing image files use the public disk and are addressed beneath per-listing paths.

Notifications are in-system rows written synchronously after business transactions. Audit rows record administrative actions. No email/SMS delivery, queued app jobs, or worker-dependent notification flow is implemented.

## Operations

`routes/console.php` schedules overdue pending appointment cancellation every 15 minutes. Production runs Laravel Scheduler every minute. There is intentionally no listing-expiration scheduler. There is no need to add Redis, a queue worker, an API layer, or a new architectural abstraction for V1 readiness.
