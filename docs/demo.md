# Local Demo and Defense

## Safety and setup

Demo fixtures are separate from normal `DatabaseSeeder`. Use only a newly created disposable local MySQL database, run the reference seed first, and invoke:

```text
php artisan db:seed --class=DemoSeeder
```

DemoSeeder refuses `APP_ENV=production` and refuses a database containing users, listings, provinces, or amenities. It does not assign `SUPER_ADMIN`. It is not an incremental content editor; reset by recreating the disposable demo database and reseeding. Do not run it against a shared/production database.

## Demo accounts

These deterministic credentials are for local defense/demo use only. All three accounts use the same password; DemoSeeder hashes it with Laravel's configured password hasher.

| Actor | Login | Local/demo password | Capability |
| --- | --- | --- | --- |
| Renter | `demo-renter@roomrental.test` | `RoomRental-Demo-V1-2026!` | RENTER |
| Landlord | `demo-landlord@roomrental.test` | `RoomRental-Demo-V1-2026!` | RENTER + LANDLORD |
| Admin | `demo-admin@roomrental.test` | `RoomRental-Demo-V1-2026!` | ADMIN |

They are not production credentials. DemoSeeder creates no Super Admin. If the defense requires Super Admin screens, run `php artisan users:bootstrap-super-admin` manually and enter a local-only password at the hidden prompt.

## Fixture contents

- Six demo-only amenities: Wi-Fi, Máy lạnh, Máy giặt, Chỗ để xe, Nội thất, Ban công. Five are active; Ban công is hidden and attached to an existing listing for lifecycle display.
- One active and one hidden demo-only room category in addition to production-safe baseline categories.
- Twelve listings with coherent current moderation pointers, approval-derived expiries, and independent moderation/occupancy/visibility values: approved public/available, rented, hidden, suspended, expired, pending, rejected, a second public listing, and four historical chart fixtures.
- Three locally generated 320×240 PNG placeholder variants labeled `DEMO`; no external image fetch or third-party image dependency. Each listing receives three public-disk gallery paths under `demo-fixtures/listings/{id}/`, separate from normal uploaded files, with one cover.
- Five appointments: pending, accepted, completed, cancelled, and auto-cancelled. Each has its own slot; timestamps and cancellation fields follow the corresponding transition rules.
- Four reports: one pending, one dismissed, and two resolved (warning and listing suspension). The suspension fixture agrees with the final listing visibility; no locked-account history is fabricated.
- A small demo `province → district → ward` hierarchy: two demo provinces, two districts, and four wards. Stable fixture codes (`DEMO-P01/02`, parent-scoped `D01`, parent-scoped `W01/02`) match current uniqueness rules.

## Location warning

Vietnam moved to a two-level local-government model from 2025. These location records are deliberately small **DEMO / LEGACY V1 FIXTURES**. Their names and codes are illustrative and are not claimed to be current, official, or geographically accurate Vietnamese administrative data. The application retains its approved legacy three-level model; a current real-world production dataset needs a future location-domain redesign/migration.

## Reset

1. Point `.env` at a fresh disposable demo database.
2. Run `php artisan migrate:fresh --seed` against that database only.
3. Run `php artisan db:seed --class=DemoSeeder`.
4. Run `php artisan storage:link` and `npm run build` if required by the local setup.
5. Optionally bootstrap a local Super Admin explicitly.

Seeded placeholder files use deterministic `demo-fixtures/listings/{id}/demo-{1,2,3}.png` paths and are overwritten on a fresh re-seed with the same IDs. Normal listing uploads use separate `listings/{id}/` paths. Do not clear shared or production storage as part of a reset.

## 10–15 minute defense flow

1. Public search, filters, sort, and an available listing detail.
2. Log in as renter, favorite a listing, book a pending slot, and submit a report.
3. Log in as landlord, view/respond to the appointment, inspect listing inventory and dashboard.
4. Log in as Admin, moderate a pending listing, inspect report queue/history, and review dashboard/catalogs.
5. If available, use an explicitly bootstrapped Super Admin to promote/revoke an Admin and show protected-account restrictions.
6. Demonstrate concurrency and uniqueness through the automated MySQL tests rather than issuing a risky live race.

Reset the disposable database to restore the known initial state; do not depend on hidden/manual database preparation.
