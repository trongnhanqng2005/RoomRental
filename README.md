# RoomRental

RoomRental is a Vietnamese-first room-rental discovery and management website. V1 supports public search, renter and landlord workflows, listing moderation, viewing appointments, favorites, reports and enforcement, Admin tools, and role-specific dashboards.

## Stack

PHP 8.3, Laravel 13, MySQL 8, Blade, Tailwind CSS 4, Preline, Lucide, Plus Jakarta Sans, Vanilla JavaScript, and Vite.

## Local quick start

1. Install PHP 8.3 with the required Laravel extensions, Composer, Node.js/npm, and MySQL 8.0.16 or later.
2. Create an empty local MySQL database and copy `.env.example` to `.env`. Set the local database name and credentials.
3. Run `composer install`, `npm ci`, and `php artisan key:generate`.
4. Run `php artisan migrate:fresh --seed` **only against the empty disposable local database**.
5. Run `php artisan storage:link` and `npm run build`.
6. Start through Laragon or `php artisan serve`.

The seed command installs reference rows only. To populate local demo actors and listings, follow [`docs/demo.md`](docs/demo.md). Never run `migrate:fresh` against shared or production data.

## Quality checks

Tests require a separate local MySQL database named `room_rental_test`; confirm its host is disposable before running `php artisan test`. Format with `vendor/bin/pint`, verify with `vendor/bin/pint --test`, and build assets with `npm run build`.

## Project documentation

- [Setup and operations](docs/setup.md)
- [Implemented requirements and exclusions](docs/requirements.md)
- [Business rules](docs/business-rules.md)
- [Architecture](docs/architecture.md)
- [Database schema](docs/erd.md)
- [Demo and defense flow](docs/demo.md)
- [Requirement traceability](docs/traceability.md)

V1 intentionally retains a legacy `province → district → ward` location model. It is not represented as current official Vietnamese administrative geography; see the setup and demo documentation before using location data.
