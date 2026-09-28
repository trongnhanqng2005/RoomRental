# RoomRental V1 Business Rules

These rules summarize behavior implemented in the current application and its feature tests. The code and migrations remain the executable authority where a detail is more specific.

## Roles and accounts

- Roles are additive: `RENTER`, `LANDLORD`, `ADMIN`, and `SUPER_ADMIN`. Landlord capability is added to the same account; it does not replace renter capability.
- There is one intended Super Admin. Bootstrap serializes on the seeded role row; web management cannot lock, demote, delete, or reset the Super Admin. The schema does not prevent direct database writes from assigning the role to another account.
- Passwords are hashed. Login accepts email or phone and is rate-limited. Successful login regenerates the session; logout invalidates the session and token.
- Locked accounts cannot log in. Middleware logs an already-authenticated locked user out on the next web request and invalidates that session.
- Admin password resets set `must_change_password`, store only a hash, and reveal the random temporary password once through a private no-store response. The user may change the password or log out until the change succeeds.
- Users may update their own profile fields. Email/phone are individually optional, but at least one identifier is required; profile changes do not grant roles. Avatar upload/URL changes are outside this V1 profile workflow.
- First listing creation requires a valid phone and contact address and adds `LANDLORD` transactionally.

## Listings and moderation

Listing state uses independent axes:

| Axis | Values |
| --- | --- |
| Current moderation | `PENDING`, `APPROVED`, `REJECTED` |
| Occupancy | `AVAILABLE`, `RENTED` |
| Visibility | `VISIBLE`, `HIDDEN`, `SUSPENDED` |

- New listings start `PENDING`, `AVAILABLE`, `VISIBLE`. Critical content edits create a new pending moderation version; occupancy and visibility changes do not.
- The listing's current moderation pointer must refer to a moderation row belonging to that listing. Approval sets expiry to review time plus 30 rolling days. Re-approval starts a new period.
- Public eligibility requires a non-deleted listing, active landlord, `VISIBLE`, `AVAILABLE`, current `APPROVED` moderation, and non-expired eligibility. A null stored expiry uses the current approval's 30-day fallback.
- Hidden and suspended listings, rented inventory, expired listings, and listings under pending/rejected current moderation are not public. Admin unsuspension changes visibility to `HIDDEN`; only an Admin can unsuspend.
- Expiry does not mutate visibility, moderate a listing, or cancel appointments. There is no expiry scheduler.
- Landlords may renew only their own expired, approved, available, non-suspended listings while their account is active. Renewal grants 30 days without changing other listing state.
- Landlord deletion is logical. It preserves listing history, favorites, image records/files, slots, and appointments; V1 has no restore action. Future pending/accepted appointments are atomically auto-cancelled on deletion.
- Listing images are public-disk files, with 3–8 images and one cover required by request validation. Replaced images are removed after a successful transaction; failed writes clean up newly uploaded files.
- A same-landlord, same-ward normalized-address match produces a non-punitive warning and does not block create/update.

## Search and favorites

- Public search covers title/address keyword, location, rent, area, amenities, and gender suitability. Sorts are newest, price ascending/descending, area ascending/descending, and most viewed; ties use listing ID descending.
- Public keyword search uses a parameter-bound SQL `LIKE` contains pattern. `%` and `_` therefore retain SQL wildcard meaning; this is not SQL injection. V1 does not specify literal matching semantics, so final hardening leaves the current behavior unchanged.
- Public landlord details use an allowlist: full name, avatar URL, phone, and Zalo when present. Email, contact address, IDs, roles, account state, and security data are not public.
- Favorites require renter capability and can be created only for a publicly eligible listing. A saved favorite remains when a listing becomes unavailable; the wishlist renders a generic unavailable state and allows removal without exposing current listing/moderation details.

## Slots and appointments

- Slot civil date/time uses `Asia/Ho_Chi_Minh`; application timestamps use UTC. Landlords manage only their own listing slots. Open slots for a listing cannot overlap; adjacent slots are allowed.
- A slot is bookable only if the listing is publicly eligible, the slot is open/unoccupied, and the start is inclusively 2 hours to 30 days ahead. A renter cannot book their own listing.
- `PENDING` and `ACCEPTED` are the only active appointment statuses. MySQL generated-column uniqueness allows at most one active appointment per slot while retaining terminal history.
- Transitions are: pending→accepted/rejected; pending or accepted→renter-cancelled before start; accepted→completed at/after slot end; pending→auto-cancelled once its start has passed.
- Renting a listing, suspending it, locking its landlord account, or logically deleting it atomically cancels affected future active appointments as specified by the workflow. Started appointments and terminal history remain.
- Overdue scheduler cancellation runs every 15 minutes and only cancels still-pending appointments. It is safe to rerun.
- Renters see only their appointments and the public landlord contact allowlist. The listing owner sees the renter's full name, email, phone, and Zalo, not contact address, roles, account state, or security fields.

## Reports and enforcement

- Any authenticated role may report another user's non-deleted listing, including one that is not public. Self-reporting is forbidden; only active reasons can be selected.
- A reporter may have one pending report per listing. Terminal reports remain history and permit a later new pending report.
- Admin handling is terminal: dismiss with a reason, or resolve with `WARNING`, `SUSPEND_LISTING`, or `LOCK_ACCOUNT` enforcement. Report handling/enforcement and audit history are transactional; notifications are attempted after commit.
- Report enforcement cannot lock an Admin/Super Admin account. Suspension changes the reported listing to `SUSPENDED`; account lock targets the landlord and cancels future active appointments without changing listing axes.

## Admin management and catalogs

- Admins may view accounts and mutate ordinary accounts. Only Super Admins may promote/revoke Admin. Neither may mutate themselves or a Super Admin through user management.
- Admin catalog management covers room categories and amenities: create, edit, hide/reactivate. Names are normalized for whitespace and unique across active/hidden rows. Hiding preserves existing listing references; hidden catalog labels remain visible on attached listings.
- The production-safe room-category seeder initializes its six defaults only when the category table is empty. Because category rows have no stable seed key, later reruns do not overwrite, reactivate, or backfill a renamed/hidden Admin-managed catalog.
- Fee types, fee units, and report reasons are seeded reference catalogs but do not have Admin management routes in V1.
- Administrative mutations are audited. Temporary passwords are not written to logs, audit metadata, or notifications.

## Dashboards

- Admin dashboard counts users, landlords, pending reports, today's Vietnam-local appointments, independent listing-state axes, 12 UTC calendar months of listing creation, category distribution, and bounded recent queues.
- Landlord dashboard shows total/non-deleted inventory, available/rented counts, pending appointments for owned non-deleted listings, and total view count. It has no additional analytics or expiry feed.

## Location model

Vietnam moved to a two-level local-government model from 2025. The application continues to store `province → district → ward` because that is the approved V1 schema. It is a legacy model, not current official Vietnamese geography. `DemoSeeder` creates a tiny, clearly labeled fixture. There is no production-safe nationwide location import or runtime scraping. Current real-world administrative geography requires a future reviewed redesign/migration.
