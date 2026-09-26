# Phase 1 schema review

Implemented Phase 1 only. No authentication panels, Services, Form Requests, business observers, controllers, endpoints, notifications, jobs, Filament resources, or Livewire components were added. The existing Laravel skeleton remains.

## Verification

- Installed Laravel: 12.69.2; Spatie Laravel Permission: 6.25.0.
- Runtime available here: PHP 8.2.12. The implementation is compatible with PHP 8.3+, but a PHP 8.3+ CI/runtime run remains necessary before deployment. Composer's existing PHP ^8.2 constraint was retained so this environment can install and test; review raising it once the runtime is upgraded.
- Clean MariaDB 10.4.32 database: all 12 migrations passed (3 existing Laravel migrations and 9 Phase 1 migrations).
- Full suite on SQLite: **42 tests passed, 107 assertions**.
- Full suite on MariaDB: **42 tests passed, 107 assertions**.
- These totals include the two existing Laravel example tests; Phase 1 contributes 40 test cases.
- MariaDB full rollback passed; migrations were then reapplied.
- Laravel Pint passed.
- MariaDB verification used the dedicated database `wastee_phase1_test_20260926`. The application's configured database and .env were not changed.
- MySQL itself was not available for execution. Migrations use Laravel schema definitions, generated stored columns, and MySQL/MariaDB trigger syntax; run on the actual production engine/version before release. SQLite uses equivalent triggers for tests.

Run the default suite with `php artisan test` and formatting checks with `php vendor/bin/pint --test`. To repeat MariaDB tests, explicitly select a disposable database using DB_CONNECTION=mysql (or mariadb), DB_DATABASE, DB_HOST, DB_USERNAME, DB_PASSWORD and an empty DB_URL. RefreshDatabase rebuilds that selected database; never point these tests at application data.

## Tables

31 new tables were added. The existing users table was extended in an additive migration that backfills UUIDs and splits existing names. No existing migration was rewritten.

| New table | Model / purpose |
| --- | --- |
| `states` | `State` |
| `lgas` | `Lga` |
| `communities` | `Community` |
| `companies` | `Company` |
| `company_memberships` | `CompanyMembership` |
| `company_documents` | `CompanyDocument` |
| `company_locations` | `CompanyLocation` |
| `company_approval_logs` | `CompanyApprovalLog` |
| `company_settings` | `CompanySetting` |
| `staff_invitations` | `StaffInvitation` |
| `company_service_areas` | `CompanyServiceArea` |
| `properties` | `Property` |
| `property_company_assignments` | `PropertyCompanyAssignment` |
| `residents` | `Resident` |
| `property_occupancies` | `PropertyOccupancy` |
| `service_plans` | `ServicePlan` |
| `bills` | `Bill` |
| `payments` | `Payment` |
| `receipts` | `Receipt` |
| `collection_zones` | `CollectionZone` |
| `collection_zone_properties` | Eloquent many-to-many pivot; current zone membership |
| `collection_schedules` | `CollectionSchedule` |
| `collection_records` | `CollectionRecord` |
| `complaints` | `Complaint` |
| `complaint_messages` | `ComplaintMessage` |
| `notifications` | Laravel DatabaseNotification; standard UUID primary key convention |
| `permissions` | Spatie Permission |
| `roles` | Spatie Role, with company/team scope |
| `model_has_permissions` | Company/team-scoped direct permission assignments |
| `model_has_roles` | Company/team-scoped role assignments |
| `role_has_permissions` | Spatie role-to-permission pivot |

Existing framework tables retained: `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, and `failed_jobs`. Laravel also creates its `migrations` bookkeeping table.

No general `audit_logs` table was added. General activity logging is deferred to an approved activity-log package in Phase 10; company review history is already supported by `company_approval_logs`.

## Models and relationships

24 domain models were created:

- `app/Models/State.php`
- `app/Models/Lga.php`
- `app/Models/Community.php`
- `app/Models/Company.php`
- `app/Models/CompanyMembership.php`
- `app/Models/CompanyDocument.php`
- `app/Models/CompanyLocation.php`
- `app/Models/CompanyApprovalLog.php`
- `app/Models/CompanySetting.php`
- `app/Models/StaffInvitation.php`
- `app/Models/CompanyServiceArea.php`
- `app/Models/Property.php`
- `app/Models/PropertyCompanyAssignment.php`
- `app/Models/Resident.php`
- `app/Models/PropertyOccupancy.php`
- `app/Models/ServicePlan.php`
- `app/Models/Bill.php`
- `app/Models/Payment.php`
- `app/Models/Receipt.php`
- `app/Models/CollectionZone.php`
- `app/Models/CollectionSchedule.php`
- `app/Models/CollectionRecord.php`
- `app/Models/Complaint.php`
- `app/Models/ComplaintMessage.php`

`app/Models/User.php` was updated. Existing Spatie Role/Permission and Laravel DatabaseNotification models are reused; no duplicate application models were introduced for package tables or pivots.

Important relationships:

- User has owned companies, company memberships and an optional resident identity. Company belongs to its owner and has memberships, documents, historical locations, approval logs, settings, invitations, service areas, plans, bills, payments, receipts, provider assignments, collections and complaints.
- State has LGAs; LGA belongs to State and has Communities; Community has Properties and CompanyServiceAreas.
- Property has occupancies and provider assignments. Resident has occupancies and an optional login User. Neither has a permanent provider/resident shortcut.
- Property.currentProviderAssignment and Resident.currentOccupancy are date-aware HasOne relationships that support eager loading.
- Bill belongs to the issuing Company, historical PropertyOccupancy and optional ServicePlan. Occupancy has Bills; Bill has Payments; Payment has one Receipt.
- Zone belongs to Company and has Properties through collection_zone_properties. Schedule belongs to Zone. CollectionRecord retains Property, optional Occupancy/Schedule, Collector and historical display snapshots.
- Complaint belongs to Company, Resident, optional historical Occupancy and optional staff Assignee. Complaint has Messages; each Message has exactly one user or resident sender.

Shared concerns: `HasPublicUuid` generates UUIDs without replacing bigint primary keys; `BelongsToCompany` provides the company relationship and explicit `forCompany()` scope. This scope is a query helper, not an authorization boundary. Tenant context and mandatory authorization belong to Phase 2.

Scopes include active(), current(), approved(), pendingReview(), successful(), unpaid(), overdue(), open(), and forCompany(), on the applicable models. Decimal money casts return strings; dates/datetimes use immutable casts. JSON snapshots/metadata use array casts.

UUID route binding is deliberately deferred until public routes exist; internal binding/relationships retain bigint IDs.

## Enums

24 string-backed PHP enums were created, all used by the schema/model casts:

| Enum | Values |
| --- | --- |
| `UserStatus` | `active`, `inactive`, `suspended` |
| `CompanyStatus` | `draft`, `pending_review`, `correction_required`, `approved`, `rejected`, `suspended` |
| `CompanyMembershipStatus` | `active`, `inactive`, `suspended` |
| `CompanyDocumentType` | `registration_certificate`, `waste_management_license`, `tax_certificate`, `other` |
| `DocumentReviewStatus` | `pending`, `approved`, `rejected` |
| `CompanyLocationStatus` | `active`, `closed` |
| `ApprovalAction` | `submitted`, `approved`, `rejected`, `correction_requested`, `suspended`, `reinstated` |
| `InvitationStatus` | `pending`, `accepted`, `expired`, `revoked` |
| `CommunityStatus` | `active`, `inactive` |
| `ServiceAreaStatus` | `active`, `inactive` |
| `PropertyType` | `residential`, `commercial`, `industrial`, `mixed_use` |
| `PropertyStatus` | `active`, `inactive` |
| `ResidentStatus` | `active`, `inactive` |
| `OccupancyType` | `tenant`, `owner_occupier`, `other` |
| `ServicePlanStatus` | `active`, `inactive` |
| `BillingCycle` | `monthly`, `quarterly`, `annually`, `one_off` |
| `BillStatus` | `draft`, `issued`, `partially_paid`, `paid`, `void` |
| `PaymentStatus` | `pending`, `successful`, `failed`, `cancelled`, `reversed` |
| `PaymentMethod` | `card`, `bank_transfer`, `cash`, `ussd`, `other` |
| `CollectionZoneStatus` | `active`, `inactive` |
| `CollectionScheduleStatus` | `active`, `paused`, `cancelled` |
| `CollectionRecordStatus` | `collected`, `missed`, `inaccessible`, `cancelled` |
| `ComplaintPriority` | `low`, `normal`, `high`, `urgent` |
| `ComplaintStatus` | `open`, `in_progress`, `resolved`, `closed` |

No ProviderAssignmentStatus or OccupancyStatus was introduced: their state derives from effective dates. CollectionScheduleStatus was added because schedules need active/paused/cancelled state. Categories, gender, and invitation role names remain strings rather than inventing unapproved closed vocabularies.

## Indexes and constraints

- Public UUIDs, company slugs, non-null registration numbers, property codes, and payment references are unique.
- Company/user membership is unique for the lifetime of that membership record. Resident.user_id is nullable and unique; many residents can have no login.
- Company settings is one-to-one via unique company_id.
- Invoice and receipt numbers are unique within a company. Payment_id is unique on receipts.
- Generated nullable slots and unique indexes allow multiple closed rows but only one open provider assignment per property, occupancy per resident, and coverage row per company/community.
- One open active head office per company and one pending invitation per company/email are enforced. Expired invitations must be marked expired before replacement.
- Collection zone names are unique per company. A property can be in one current zone per company.
- Composite foreign keys enforce payment/bill company agreement, receipt/payment company agreement, bill/plan company agreement, schedule/zone company agreement, record/schedule company agreement, record/occupancy property agreement, complaint/occupancy resident agreement, complaint assignee membership, and office LGA/state agreement.
- Foreign keys use RESTRICT for domain history, rather than cascading destruction. Spatie retains its package-defined cascading permission pivots. Polymorphic notifications and permission subjects follow package conventions and cannot have ordinary subject foreign keys.
- Tenant/status indexes support memberships, documents, offices, coverage, plans, bills, payments, zones, schedules and complaints. Additional indexes cover membership user/status, coverage community/status, geography/property status, provider property/company plus end date, occupancy resident/property plus end date, bill occupancy/status and due_at/status, payment bill/status and gateway_reference, collection company/property plus collected_at, and complaint assignee/status.
- Database triggers reject invalid enum strings, inverted temporal ranges, invalid weekday/recurrence, non-positive payment amounts, negative financial values, inconsistent bill arithmetic, and invalid complaint sender combinations.
- Approval logs reject updates/deletes, including direct query-builder writes.
- Issued bills protect their original issuer, occupancy, invoice number, period, pricing, currency, issue time and snapshots. Issued bills cannot return to draft. Payment progress/status remain updateable for later Services.
- Financial document/payment deletion is blocked. Receipts require a successful payment when inserted and protect their identity/snapshot thereafter; file-generation fields may still change after a payment reversal. Migration rollback explicitly removes triggers first.

The integrity migration uses database triggers only for persistence constraints. It implements no approval, billing, movement, payment-confirmation, notification, or other business workflow.

## History and schema decisions

Temporal periods are half-open: start <= date < end. A null end means open. current() uses today's date by default and accepts an explicit date for historical queries. A future row is not current; a finite row covering today is current. Open-slot uniqueness reserves even a future open row.

MySQL/MariaDB cannot practically exclude every overlapping closed date range with these simple indexes. **Phase 5 Services must lock the stable Resident/Property/Company rows in transactions, check all intersecting ranges, then close/create history rows.** No such Services were added in this phase. The one-open-record constraints protect concurrent duplicate open inserts, but do not replace those interval checks.

Company addresses live in company_locations. Community there is free text, with normalized LGA and State references. This allows offices outside configured collection communities. Community coverage and actual property provider assignments remain separate.

Memberships use one row per company/user, with status/joined_at/left_at. Rejoining will reuse this row; complete membership episode history would need another table if required. Occupancy, office, provider and coverage history use separate temporal rows.

Zone membership is current-only. Detaching a property does not delete collection records; collection records retain references and display snapshots.

Financial snapshots are JSON columns: bills contain resident_snapshot, property_snapshot, company_snapshot and plan_snapshot; receipts contain snapshot. Later issuing Services must supply versioned complete rendering payloads (names/contact details, property code/address, company registration/license/address, plan name/rate/cycle, currency and any receipt-specific payment reference/date/method). Monetary values inside JSON should be decimal strings. Schema immutability prevents later edits to issued snapshots, while the rendering contract/required keys will be defined in Phase 6/7.

Service plans are optional on bills, but deletion of referenced plans is restricted. Archive plans using status; historical rendering uses the snapshot. Price changes should create effective plan versions through Phase 6 Services.

Draft bills can have null issued_at; issued/non-draft records require it. Amount columns use DECIMAL(14,2), currency defaults to NGN. Money arithmetic excludes tax because tax requirements were not specified. Partial-payment accounting columns are present; confirmation/idempotency/reversal workflows are deferred.

Existing users.name remains for Laravel compatibility alongside first_name/last_name. Existing names/UUIDs are backfilled. New workflows should keep the display name synchronized. No development user/password is seeded automatically, and no unverified Nigerian state/LGA dataset was invented.

No soft deletes were added: historical relationships, status/effective dates, restrictive foreign keys and immutable financial/review records are the retention strategy. Broader retention/purge rules require explicit policy.

## Spatie team strategy

Spatie's teams feature is enabled, with company_id as the team key. This follows the [official teams configuration](https://spatie.be/docs/laravel-permission/v6/basic-usage/teams-permissions).

- Positive company IDs represent company contexts.
- Team 0 is reserved for platform-level assignments and platform roles. No fake Company row is created.
- Package-standard nullable role company_id can represent reusable global role definitions; it does not make assignments automatically available across contexts.
- The package's company_id/team columns intentionally have no company foreign key because the reserved platform context is not a tenant.
- Invitations record a role_name, resolved within the authenticated company and web guard during acceptance; role existence and assignment eligibility are later Service concerns.
- Phase 2 must set/reset team context and clear loaded role/permission relations on every context switch, including long-lived workers. Platform permissions must never be treated as implicit company membership.
- Tests prove company permissions do not appear in another company or platform context, and platform permissions do not appear in a company context. No middleware, policies, role seeds, or authorization UI were added.

Spatie v6 supports the available PHP version and Laravel 12; the locked package version is 6.25.0. Reassess the package major version together with the runtime upgrade.

## Files changed

- Modified: composer.json, composer.lock, app/Models/User.php, database/factories/UserFactory.php, database/seeders/DatabaseSeeder.php.
- Added: config/permission.php, docs/phase-1-review.md, tests/Feature/Phase1FoundationTest.php.
- Added 24 model files and 24 enum files listed above.
- Added: app/Models/Concerns/HasPublicUuid.php and app/Models/Concerns/BelongsToCompany.php.
- Added factories: database/factories/CompanyFactory.php, database/factories/StateFactory.php, database/factories/LgaFactory.php, database/factories/CommunityFactory.php, database/factories/PropertyFactory.php, database/factories/ResidentFactory.php, database/factories/PropertyOccupancyFactory.php, database/factories/ServicePlanFactory.php, database/factories/BillFactory.php, database/factories/PaymentFactory.php, database/factories/ReceiptFactory.php.
- Added migrations:
  - 2026_09_26_000000_extend_user_identity.php
  - 2026_09_26_000001_create_geography_tables.php
  - 2026_09_26_000002_create_companies_tables.php
  - 2026_09_26_000003_create_properties_residents_tables.php
  - 2026_09_26_000004_create_billing_tables.php
  - 2026_09_26_000005_create_collections_complaints_tables.php
  - 2026_09_26_000006_create_notifications_table.php
  - 2026_09_26_000007_create_permission_tables.php
  - 2026_09_26_000008_add_domain_invariants.php

## Review before Phase 2

1. Upgrade to/test PHP 8.3+ and confirm production MySQL/MariaDB version and trigger privileges.
2. Confirm platform team 0 and company-scoped roles, including lifecycle handling of team context.
3. Confirm one concurrent/open occupancy per resident, one provider per property, one zone per property/company, and half-open date boundaries.
4. Confirm lifetime membership rows versus rejoining episode history.
5. Confirm snapshot rendering fields, NGN default, decimal precision, taxes and partial-payment policy before billing work.
6. Choose an authoritative Nigerian state/LGA dataset for a later import.
7. Keep range-overlap checks, active membership eligibility, actual provider/coverage eligibility, snapshot completeness, currency agreement and workflow state transitions in the later authorized Services. The Phase 1 query helpers are not tenant authorization.

Phase 1 stops here.

