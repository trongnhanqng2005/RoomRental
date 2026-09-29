# Local Demo and Defense

## Safety and reset

Realistic Demo V2 is synthetic local demo data. `DemoSeeder` refuses `APP_ENV=production` and refuses a database that already contains users, listings, provinces, or amenities. It does not reconcile or overwrite a populated application database and does not assign `SUPER_ADMIN`.

Use only a fresh, disposable local demo database. The reset sequence is destructive and must not be run against a shared or production database:

```text
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoSeeder
php artisan storage:link
```

The seed command is separate from the production-safe reference `DatabaseSeeder`. Runtime-generated images are stored on the public disk below `demo-fixtures/listings/`; a fresh reset recreates them. Normal uploaded images use a separate path.

## Demo accounts

All credentials are local/demo-only; every account uses the same password, stored as a hash:

`RoomRental-Demo-V1-2026!`

| Actor | Login | Capability and purpose |
|---|---|---|
| Main renter | `demo-renter@roomrental.test` | RENTER; starts with 10 favorites |
| Main landlord | `demo-landlord@roomrental.test` | RENTER + LANDLORD; 15-listing portfolio and known dashboard metrics |
| Main Admin | `demo-admin@roomrental.test` | ADMIN; moderation, reports, catalog, and dashboard flow |
| Second landlord | `demo-landlord-second@roomrental.test` | RENTER + LANDLORD; 8-listing portfolio, suspended-listing and hidden-category examples |
| Heavy-portfolio landlord | `demo-landlord-heavy@roomrental.test` | RENTER + LANDLORD; 15-listing portfolio |
| Locked landlord | `demo-landlord-locked@roomrental.test` | LOCKED RENTER + LANDLORD; two non-public listings and report-based account enforcement |

V2 also seeds 97 RENTER-only accounts, 20 combined RENTER + LANDLORD accounts total, and three active ADMIN-only accounts. Three RENTER-only accounts and the named locked landlord are LOCKED. No Super Admin user is seeded. Generated identities use deterministic Vietnamese-style fictional names and `roomrental.test` email addresses.

## Exact fixture scale

| Entity | Count |
|---|---:|
| Users / profiles | 120 / 120 |
| Landlords | 20 |
| Listings | 135 |
| Viewing slots / appointments | 320 / 180 |
| Reports / enforcement actions | 60 / 28 |
| Favorites | 430 |
| Listing images / listing fees | 432 / 405 |
| Demo provinces / districts / wards | 4 / 12 / 48 |
| Demo amenities | 6 |

Landlord portfolios are deliberately long-tailed: ten landlords own two listings each, five own eight each, and five own 15 each. The hidden demo category has one rejected historical listing reference; it cannot be selected for a new listing. The six public categories have this persisted distribution:

| Category | Listings |
|---|---:|
| Phòng trọ | 53 |
| Căn hộ mini | 27 |
| Studio | 21 |
| Chung cư | 15 |
| Nhà nguyên căn | 10 |
| Ở ghép | 8 |
| Hidden demo category (historical reference) | 1 |

Current listing state axes are independent. Full totals are moderation APPROVED/PENDING/REJECTED **106/20/9**, occupancy AVAILABLE/RENTED **113/22**, visibility VISIBLE/HIDDEN/SUSPENDED **110/17/8**, three logically deleted listings, and 18 approved-but-expired listings. Admin current-state metrics exclude deleted listings: moderation **104/19/9**, occupancy **111/21**, visibility **108/16/8**. Exactly **80 listings** pass the existing public eligibility query.

## Content and history

- Category-aware area/rent ranges correlate rent with area, demo region, and amenities. Prices remain in bounded category bands; listings are not created with independent random price/area values.
- Active amenity attachments are deterministic: Wi-Fi 108, Chỗ để xe 97, Máy lạnh 65, Nội thất 54, Máy giặt 47. `Ban công` is hidden and attached only to the named historical reference listing.
- Listings receive 3 local generated 320×240 PNG gallery images; 27 featured listings receive a fourth. Every gallery has exactly one cover. Six generated room-scene color/layout variants are cached in process; no network or third-party imagery is used.
- The monthly listing-creation chart, oldest to newest, is **4, 5, 6, 7, 8, 9, 10, 11, 13, 16, 20, 26**. Approved expiry uses the existing approval-plus-30-day rule; older still-current listings have an `expires_at` consistent with a later renewal, without invented moderation versions.
- The 320 slots include 210 past and 110 today/upcoming, with 250 OPEN and 70 CLOSED. They do not overlap for a listing while OPEN. There are at least 25 currently bookable public OPEN slots.
- Appointment states PENDING/ACCEPTED/REJECTED/CANCELLED/COMPLETED/AUTO_CANCELLED are **28/22/25/30/55/20**. Six ACCEPTED appointments use today’s `Asia/Ho_Chi_Minh` viewing date; four PENDING appointments belong to the main landlord.
- Report reasons WRONG_ADDRESS/WRONG_PRICE/FRAUD/INAPPROPRIATE_CONTENT/OTHER are **18/15/9/8/10**. Report states PENDING/DISMISSED/RESOLVED are **18/14/28**. Resolved actions are WARNING/SUSPEND_LISTING/LOCK_ACCOUNT **22/5/1**. The five enforced suspension targets remain suspended; the lock action targets the named locked landlord.
- Favorite distribution is 30 renter-capable users × 4, 30 × 7, and 10 × 10. The main renter has ten, including one retained unavailable favorite. Exactly 25 retained favorites point to listings that are now unavailable, for the existing wishlist fallback.
- Fee rows are electricity 115 (`PER_KWH`), water 100 (`PER_M3`), internet 75 (`FIXED_MONTHLY`), parking 70 (`PER_VEHICLE`), and service 45 (`FIXED_MONTHLY`). Listings receive only a category-appropriate subset.
- Gender requirement is ANY/FEMALE/MALE **100/23/12**. Existing public behavior includes ANY listings in FEMALE and MALE filters; ANY-only returns exact ANY matches.
- At least six same-owner, same-ward address pairs use identical fictional street addresses but distinct room labels. They demonstrate the existing non-blocking duplicate-address warning.

## Storyline records

Find these records by owner email and exact title, not by generated database ID:

| Flow | Owner login | Exact listing title |
|---|---|---|
| Public detail and booking | `demo-landlord@roomrental.test` | `Studio nội thất, máy lạnh — Phòng A01` |
| Pending moderation | `demo-landlord@roomrental.test` | `Phòng trọ chờ duyệt — Phòng A02` |
| Pending report target | `demo-landlord@roomrental.test` | `Phòng trọ khu vực demo — Phòng A03` |
| Expired and renewable | `demo-landlord@roomrental.test` | `Căn hộ mini phòng A04` |
| Hidden amenity historical reference | `demo-landlord@roomrental.test` | `Studio tham chiếu tiện nghi — Phòng A05` |
| Suspended; report enforcement / Admin unsuspend | `demo-landlord-second@roomrental.test` | `Phòng trọ thuộc hồ sơ báo cáo — Căn demo` |
| Hidden category historical reference | `demo-landlord-second@roomrental.test` | `Tin tham chiếu danh mục ẩn — Căn demo` |
| Locked account report target | `demo-landlord-locked@roomrental.test` | `Phòng đã có người thuê — Hồ sơ chủ trọ khóa` |

The public listing has open bookable slots. The suspended item begins in SUSPENDED visibility and the hidden-category item remains a rejected historical listing; neither should be used as an ordinary public listing.

## Known search and dashboard examples

- Unfiltered public search: **80 listings / 8 pages** at 10 per page.
- Several results: keyword `Studio` returns multiple public listings. Region + Wi-Fi and the two-amenity AND filter are also useful.
- One result: search exact title `Studio nội thất, máy lạnh — Phòng A01`, select its ward, and require both `Máy lạnh` and `Nội thất`.
- Zero results: use that same ward with a maximum price of `1` VND.
- Compare newest, price ascending/descending, area ascending/descending, and most viewed; the reserved prices, areas, dates, and views make the first results differ.
- Main landlord fresh-seed dashboard: **15 listings, 12 available, 3 rented, 4 waiting appointments, 2,340 views**.
- Admin fresh-seed dashboard: **120 users, 20 landlords, 18 pending reports, 6 today appointments, 132 non-deleted listings**; both recent queues contain five rows and the chart has the 12 monthly counts above.
- Before relying on fresh-seed view metrics, reset after public detail visits: each successful detail request increments `view_count` under current V1 behavior.

## Legacy location warning

The 4/12/48 `province → district → ward` records are **LEGACY DEMO FIXTURES** compatible with the approved V1 schema. TP.HCM, Hà Nội, Đà Nẵng, and Cần Thơ are illustrative demo region labels with sample districts and wards. These fixtures do **not** claim current official names, boundaries, or nationwide administrative accuracy.
