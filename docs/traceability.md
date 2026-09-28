# V1 Requirement Traceability

This is a compact map from user-visible capability to implementation and verification. Paths and test names refer to the current codebase.

| Requirement | Route/controller | Service/query | DB invariant | Test | UI |
| --- | --- | --- | --- | --- | --- |
| Register/login, profile, locked sessions, temporary password | `register`, `login`, `profile.*`, `password.change.*`; Auth/Profile controllers | `ProfileService`; account/session middleware | Unique identifiers; user/profile FK; hashed `password_hash` | `RegistrationTest`, `AuthenticationTest`, `ProfileUpdateTest`, `LockedAccountEligibilityTest`, `TemporaryPasswordChangeTest` | `auth/*`, `profile/show.blade.php` |
| Public search and eligible detail | `public.listings.index/show`; Public ListingController | `ListingSearchQuery`, `AppointmentService` | Listing current-moderation composite FK; public state/expiry rules | `PublicListingTest` | `public/listings/index/show.blade.php` |
| Landlord listing create/edit/moderation/lifecycle | `landlord.listings.*`, Admin moderation routes | `ListingService`, `ListingModerationService`, `ListingLifecycleService` | Moderation version uniqueness and listing-scoped pointer; image ordering | `ListingManagementTest`, `ListingLifecycleTest`, `ListingModerationTest` | `landlord/listings/*`, `admin/listing-moderations/*` |
| Favorites and unavailable wishlist | `favorites.*`, `wishlist.index` | `FavoriteController` | Composite favorite PK `(user_id, listing_id)` | `FavoritesWishlistTest` | `renter/wishlist/index.blade.php` |
| Slots and appointment transitions | renter/landlord appointment and viewing-slot routes | `AppointmentService`, `ViewingSlotService` | Slot schedule unique; generated active-slot unique key | `ViewingSlotAppointmentTest`, `AutoCancelOverdueAppointmentsTest` | `renter/appointments/*`, `landlord/appointments/*`, `landlord/viewing-slots/*` |
| Reports and enforcement | `reports.store`, `admin.reports.*` | `ReportService`, `AccountLockService` | Generated pending-listing unique key; enforcement target checks | `ReportSubmissionTest`, `ReportConcurrencyTest`, `ReportManagementTest`, `UserAccountLockTest` | `admin/reports/*`, listing detail report form |
| Admin user and Admin-role management | `admin.users.*` | `UserAdministrationService` | Composite user-role PK; app-enforced Super Admin singleton | `UserManagementTest`, `SuperAdminBootstrapTest` | `admin/users/*` |
| Category and amenity catalogs | `admin.categories.*`, `admin.amenities.*` | `CatalogAdministrationService` | Unique category/amenity names | `CatalogManagementTest`, `ListingManagementTest` | `admin/categories/*`, `admin/amenities/*` |
| Dashboards | `admin.dashboard`, `landlord.dashboard` | `AdminDashboardQuery`, `LandlordDashboardQuery` | Aggregates use listing, role, slot, appointment state | `AdminDashboardTest`, `LandlordDashboardTest` | `admin/dashboard.blade.php`, `landlord/dashboard.blade.php` |
| Reference/demo bootstrap and setup | Artisan seeders/commands | `DatabaseSeeder`, `DemoSeeder`, `SuperAdminBootstrapService` | Unique reference codes/names and location hierarchy keys | `ReferenceSeederTest`, `DemoSeederTest`, `SuperAdminBootstrapTest` | `docs/setup.md`, `docs/demo.md` |

The legacy location fixture is demo-only and must not be interpreted as a current authoritative Vietnam dataset.
