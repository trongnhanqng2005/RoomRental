# RoomRental V1 Database Schema

This document reflects the current migration definitions and the isolated MySQL 8 schema used for V1. Framework support tables are listed separately. Migrations are the source of truth; no location-domain migration is part of this release phase.

## Domain tables and relationships

- Identity: `users`, one-to-one `user_profiles`, `roles`, composite-key `user_roles` (including nullable assigning user).
- Legacy location: `provinces` → `districts` → `wards`; listings reference a ward.
- Catalogs: `room_categories`, `amenities`, `fee_types`, `fee_units`, `report_reasons`.
- Listings: `listings` references owner, category, ward, and current moderation; `listing_moderations` retains versions; `listing_images`, `listing_amenities`, and `listing_fees` hold listing details.
- Engagement: `favorites`, `viewing_slots`, `appointments`, `reports`, and `enforcement_actions`.
- Administration: `notifications` and `audit_logs`. Their `entity_type`/`entity_id` pairs are application-level polymorphic references, not foreign keys.

The domain schema has 24 tables. Laravel infrastructure migrations also create `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, and `failed_jobs`; the migrations repository contains migration state.

## Important foreign keys and uniqueness

- User email and phone are individually unique when non-null; a check requires at least one identifier. `user_roles` has a composite primary key `(user_id, role_id)`.
- Province `code` is unique. District uniqueness is `(province_id, code)`; ward uniqueness is `(district_id, code)`. These are parent-scoped codes, not documented global district/ward uniqueness.
- Room-category and amenity `name` each have a unique index across active and hidden records. Fee, unit, report-reason, and role codes are unique.
- Listing image order is unique per listing. Listing moderation version is unique per listing; `(id, listing_id)` supports the composite current-moderation relationship.
- `listings(current_moderation_id, id)` references `listing_moderations(id, listing_id)`, preventing a non-null current pointer from referencing another listing's moderation.
- Viewing slot schedule is unique by `(listing_id, viewing_date, start_time, end_time)`. Listing fee type is unique per listing. Favorites and listing amenities use composite primary keys.
- `appointments.active_slot_id` is a stored generated column containing `slot_id` only for `PENDING`/`ACCEPTED`; its unique index enforces one active appointment per slot while allowing multiple null terminal-history rows.
- `reports.pending_listing_id` is a stored generated column containing `listing_id` only while pending; unique `(reporter_id, pending_listing_id)` permits only one pending report per reporter/listing.
- Catalog names use the actual MySQL column collation for case/accent comparisons. Database config defaults MySQL connections to `utf8mb4`/`utf8mb4_unicode_ci`; newly provisioned database defaults should be checked in deployment.
- Room-category seed defaults are inserted only when the category table is empty. The schema has no immutable seed identity for mutable category names; later seeder runs intentionally do not recreate Admin-renamed/hidden rows.

## Checks and application-level rules

- Moderation checks require pending rows to have no review fields and approved/rejected rows to have reviewer/time. Rejection requires a reason.
- Dismissed reports require a resolution reason. Warning/lock enforcement requires a user target; suspension requires a listing target.
- The 3–8 image count, one cover, listing state transitions, expiry, appointment overlap, and role semantics are principally application rules, not all database checks.
- Super Admin singleton is application-enforced by bootstrap/web management, not by a unique DB constraint.
- `current_moderation_id` is nullable at schema level; normal listing creation sets it before commit.

## Delete and location semantics

Migrations do not specify explicit cascade actions. V1 listing deletion is logical (`deleted_at`) and preserves listing history, favorites, slot/appointment history, image rows, and image files. Do not infer physical cascade behavior from the ERD.

Vietnam moved to a two-level local-government model from 2025. Locations remain `province → district → ward` for this V1. DemoSeeder uses a small parent-scoped fixture. It is not an official/current Vietnamese location import. A production deployment needing current real-world administrative geography requires a future schema/design review; district IDs and listing forms are not changed here.

## Engine and verification

Migrations contain MySQL `ALTER TABLE ... CHECK` statements and stored generated columns; MySQL 8.0.16+ is required for enforced checks. A clean install verified on MySQL 8.4.3 produced InnoDB tables with `utf8mb4_unicode_ci`; `SHOW CREATE TABLE` confirmed the current moderation FK and both generated-column unique constraints. Verify deployed `SHOW CREATE TABLE`, `information_schema` foreign keys/indexes, generated definitions, engine, collation, and defaults after migrations. Do not change schema solely to make stale documentation appear consistent.
