# RoomRental V1 Requirements

This document describes the currently implemented V1 scope. It is reconciled against the routes, controllers, services, queries, migrations, and feature tests; it is not a proposal for additional marketplace scope.

## Implemented functionality

- Public visitors can search, filter, sort, and view publicly eligible room listings.
- Users can register, log in with email or phone, manage allowed profile fields, and receive in-system notifications.
- Renter-capable users can favorite listings, manage a wishlist, book/cancel viewing appointments, and report another user's listing.
- Landlord-capable users can create and manage listings, images, viewing slots, appointments, and occupancy/visibility/lifecycle actions.
- Admins can moderate listings, handle reports and enforcement, manage ordinary accounts, manage room categories and amenities, and view the Admin dashboard.
- The Super Admin has Admin capabilities and may promote/revoke Admin roles. Initial Super Admin setup is a separate operator command.
- Role-specific Landlord and Admin dashboards show live database aggregates.
- Appointment overdue cancellation is scheduled. Listing expiry is evaluated at read/action time and has no expiry scheduler.

## V1 exclusions

V1 does not include payments, chat, email/SMS delivery, maps/geocoding, recommendations, a public API/mobile app, Redis, Elasticsearch, WebSockets, multi-tenancy, advanced analytics, deleted-listing restoration, or a listing-expiry scheduler. Notifications are in-system. No queue worker is required by current application workflows.

## Location limitation

Vietnam moved to a two-level local-government model from 2025. The approved V1 schema and forms remain `province → district → ward`; this is a retained legacy project model, **not** a claim about the current official administrative structure. Demo location rows are small legacy fixtures only. Production use requiring current real-world Vietnamese administrative geography needs a future location-domain redesign and migration; no such redesign is part of this V1 release-readiness work.
