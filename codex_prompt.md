# Codex Build Prompt — Waste Management SaaS MVP

You are building a production-minded MVP for a multi-tenant waste-management SaaS using Laravel 12.

Before writing code, read `agent.md` and `skills.md` completely and treat them as project rules.

## Product Summary

Waste-management companies discover the platform and register themselves.

A company owner:

1. creates an account;
2. creates/setup the waste-management company;
3. supplies company details;
4. adds one or more company office locations;
5. uploads required compliance documents;
6. submits the company for platform review.

The Platform Admin reviews the application and can approve, reject, or request corrections.

Once approved, the company owner gets access to the company Filament panel and can:

- invite staff;
- assign roles and permissions;
- manage company settings and office locations;
- configure service areas;
- manage residents/customers;
- manage properties and resident occupancy history;
- manage which company services a property over time;
- create service plans;
- issue bills;
- track payments;
- generate receipts;
- manage waste collection operations;
- manage complaints.

Residents/customers use a Livewire portal to interact with their account, bills, payments, receipts, collection information, and complaints.

## Critical Domain Rules

### Companies register themselves

Platform Admin must NOT manually create all waste companies as the normal onboarding process.

Companies self-register and are reviewed before activation.

### Residents can move

Do not store a permanent resident foreign key on a property.

Use occupancy history.

A resident can move from Property A to Property B without losing:

- old bills;
- old payments;
- old receipts;
- old complaint history;
- old collection/service history.

### Waste companies can relocate

Do not model a company with only one mutable permanent address.

Use `company_locations` so a company can relocate, close an old office, open branches, and change head office while preserving history.

### Properties can change providers

Do not permanently bind a property to one waste company.

Use `property_company_assignments` with effective dates/current state.

### Service coverage and actual provider are different

`company_service_areas` represents where a company is configured/authorized to operate.

`property_company_assignments` represents the actual company servicing a specific property for a specific period.

### Historical bills must remain correct

Bills must store enough immutable information to reproduce the original invoice/receipt even if:

- resident changes name/contact details;
- resident moves;
- property changes provider;
- company changes address;
- service plan price changes.

Use appropriate snapshot columns for issued financial documents.

---

# IMPLEMENTATION PHASES

You MUST implement one phase at a time and STOP after the requested phase.

Do not skip ahead.

## PHASE 1 — DATABASE FOUNDATION ONLY

Implement Phase 1 now.

### Phase 1 goals

Create the database foundation and Eloquent domain model only.

Create:

- enums;
- migrations;
- models;
- relationships;
- casts;
- query scopes/query helpers;
- necessary factories for relationship/constraint tests;
- tests covering schema/model invariants.

Do NOT create yet:

- controllers;
- Services;
- Form Requests;
- Filament resources/pages/widgets;
- Livewire components;
- payment-gateway integration;
- notifications;
- jobs;
- business observers;
- API endpoints.

### Phase 1 tables

#### Authentication / identity

1. `users`

Recommended columns beyond Laravel defaults:

- `uuid` unique;
- first name;
- last name;
- email unique;
- phone nullable/indexed as appropriate;
- email verification timestamp;
- password;
- status;
- last login timestamp nullable;
- remember token;
- timestamps.

Use an enum for user status.

#### Companies

2. `companies`

Columns should include:

- `id`;
- `uuid` unique;
- `owner_user_id` FK users;
- legal/company name;
- slug unique;
- registration number nullable but unique when present;
- waste-management/license number nullable;
- email;
- phone;
- website nullable;
- status;
- submitted_at nullable;
- approved_at nullable;
- approved_by nullable FK users;
- rejection/correction summary nullable only as current convenience field;
- timestamps;
- soft deletes only if justified.

Company status should support at least:

- draft;
- pending_review;
- correction_required;
- approved;
- rejected;
- suspended.

3. `company_memberships`

Purpose: links owner/staff users to a company.

Include:

- company;
- user;
- status;
- joined_at;
- left_at nullable;
- timestamps.

Add uniqueness preventing duplicate active membership rows for the same company/user under the chosen design.

4. `company_documents`

Include:

- uuid;
- company;
- document type;
- original filename;
- disk/path;
- mime/type metadata if useful;
- review status;
- reviewed_by nullable;
- reviewed_at nullable;
- review remarks nullable;
- timestamps.

5. `company_locations`

Must preserve relocation/branch history.

Include:

- uuid;
- company;
- label/name (`Head Office`, `Asaba Branch`, etc.);
- address line;
- community text/reference as appropriate;
- LGA reference;
- state reference;
- optional latitude/longitude;
- `is_head_office`;
- `active_from`;
- `active_to` nullable;
- status;
- timestamps.

Do not destroy old office locations when a company relocates.

6. `company_approval_logs`

Immutable audit trail of company review actions.

Include:

- company;
- actor/platform admin user;
- action/status transition;
- remarks nullable;
- metadata JSON nullable;
- created timestamp.

Prefer no normal update/delete workflow for these records.

7. `company_settings`

One-to-one with company.

Include MVP settings such as:

- currency default NGN;
- invoice prefix;
- receipt prefix;
- support email;
- support phone;
- timezone default Africa/Lagos;
- timestamps.

8. `staff_invitations`

Include:

- uuid/token or hashed token strategy;
- company;
- email;
- invited_by user;
- intended role reference/name according to the Spatie strategy;
- status;
- expires_at;
- accepted_at nullable;
- timestamps.

Do not store plain reusable secrets unnecessarily.

#### Geography

9. `states`

Seedable Nigerian state reference data.

Include:

- name;
- code nullable/unique if used;
- country code default `NG`;
- timestamps if desired.

10. `lgas`

Include:

- state;
- name;
- code nullable;
- unique constraint appropriate for state/name.

11. `communities`

Include:

- uuid;
- lga;
- name;
- ward nullable;
- postal code nullable;
- optional latitude/longitude;
- status;
- timestamps.

12. `company_service_areas`

For MVP, use community-level coverage unless there is a strong reason otherwise.

Include:

- company;
- community;
- active_from;
- active_to nullable;
- status;
- timestamps.

Prevent accidental duplicate active coverage rows.

#### Properties

13. `properties`

A property is a physical service location and is not permanently owned by a waste company or resident.

Include:

- uuid;
- unique property code;
- community;
- house/building number nullable;
- street;
- landmark nullable;
- property type;
- status;
- optional geolocation;
- timestamps.

Do NOT add `resident_id`.

Do NOT add permanent `company_id` as the provider.

14. `property_company_assignments`

Temporal provider history.

Include:

- property;
- company;
- assigned_from;
- assigned_to nullable;
- status/current indicator only if needed in addition to dates;
- reason nullable;
- created_by nullable user;
- timestamps.

Design indexes/constraints to support efficient lookup of current provider and prevent overlapping current assignments through service-level and database-level protections where practical.

#### Residents / occupancy

15. `residents`

Resident is a domain identity independent of current company/provider.

Include:

- uuid;
- optional `user_id` unique nullable for portal login/account claim;
- first name;
- last name;
- phone;
- email nullable;
- gender nullable;
- date of birth nullable;
- occupation nullable;
- status;
- timestamps.

Do NOT permanently bind resident to `company_id`.

16. `property_occupancies`

Temporal resident/property history.

Include:

- uuid;
- resident;
- property;
- move_in_date;
- move_out_date nullable;
- status/current flag if useful;
- occupancy type if needed (`tenant`, `owner_occupier`, etc.);
- created_by nullable user;
- timestamps.

Design indexes/constraints for efficient current occupancy queries and to prevent invalid duplicate current occupancy for the same resident according to MVP business rules.

#### Service plans / billing

17. `service_plans`

Company-owned.

Include:

- uuid;
- company;
- name;
- description nullable;
- property type applicability nullable if useful;
- amount decimal;
- billing cycle;
- status;
- effective_from nullable;
- effective_to nullable;
- timestamps.

Do not overwrite old plan pricing if doing so would corrupt historical expectations. Bills must snapshot the applied plan data.

18. `bills`

Company-owned financial document.

Include:

- uuid;
- company;
- property occupancy;
- service plan nullable if historical plan may later be removed;
- invoice number;
- billing period start/end;
- subtotal/amount;
- discount amount default 0;
- late fee default 0;
- total amount;
- amount paid default 0;
- outstanding amount;
- status;
- issued_at;
- due_at;
- paid_at nullable;
- created_by user;
- historical snapshot fields needed for invoice reproduction, such as resident name, property display address, company display name/address, plan name/rate where appropriate;
- timestamps.

Add uniqueness for invoice numbering in company scope.

19. `payments`

Company-owned.

Include:

- uuid;
- company;
- bill;
- payment reference unique;
- gateway nullable;
- gateway reference nullable/indexed;
- payment method;
- amount decimal;
- currency;
- status;
- paid_at nullable;
- verified_at nullable;
- initiated_by nullable user/resident-account relation as appropriate;
- gateway response/metadata JSON nullable but avoid storing secrets;
- timestamps.

Status should support pending, successful, failed, cancelled/reversed if required.

20. `receipts`

Include:

- uuid;
- company;
- payment;
- receipt number;
- file disk/path nullable until generated;
- generated_at;
- snapshot fields if necessary for immutable reproduction;
- timestamps.

Unique receipt number in company scope and one receipt per successful payment unless requirements explicitly change.

#### Collection operations

21. `collection_zones`

Company-owned.

Include:

- uuid;
- company;
- name;
- description nullable;
- status;
- timestamps.

22. `collection_zone_properties`

Many-to-many assignment of company-relevant properties to a collection zone.

Include effective/current data if zone history matters for MVP; otherwise keep MVP simple but do not delete collection records when assignments change.

23. `collection_schedules`

Company-owned.

Include:

- uuid;
- company;
- zone;
- day of week or schedule representation;
- start time nullable;
- end time nullable;
- recurrence/status fields;
- timestamps.

24. `collection_records`

Historical proof of collection attempt/result.

Include:

- uuid;
- company;
- property;
- property occupancy nullable if a resident-specific snapshot is needed;
- schedule nullable;
- collector user nullable;
- status;
- remarks nullable;
- collected_at/attempted_at;
- snapshot fields only if needed to preserve historical display;
- timestamps.

Do not make historical collection records dependent on a current occupancy still existing.

#### Complaints

25. `complaints`

Include:

- uuid;
- company;
- resident;
- property occupancy nullable where relevant;
- category/type;
- subject/title;
- description;
- priority;
- status;
- assigned_to nullable company user;
- resolved_at nullable;
- timestamps.

26. `complaint_messages` (include now if it remains simple)

Include:

- complaint;
- sender user nullable;
- resident/customer sender relation if needed;
- message;
- attachment metadata only if required;
- created timestamp.

#### System

27. Laravel `notifications` table.

28. Spatie Permission tables configured for the selected tenant/team strategy.

29. `audit_logs` only if a simple first-party table is chosen for the MVP; otherwise document that it will be introduced with the approved activity-log package in a later phase.

---

# ENUMS TO INTRODUCE IN PHASE 1

Use PHP backed enums for controlled values. Create only enums actually used by Phase 1 tables.

Likely enums include:

- `UserStatus`
- `CompanyStatus`
- `CompanyMembershipStatus`
- `CompanyDocumentType`
- `DocumentReviewStatus`
- `CompanyLocationStatus`
- `ApprovalAction`
- `InvitationStatus`
- `CommunityStatus`
- `ServiceAreaStatus`
- `PropertyType`
- `PropertyStatus`
- `ProviderAssignmentStatus`
- `ResidentStatus`
- `OccupancyType`
- `OccupancyStatus`
- `ServicePlanStatus`
- `BillingCycle`
- `BillStatus`
- `PaymentStatus`
- `PaymentMethod`
- `CollectionZoneStatus`
- `CollectionRecordStatus`
- `ComplaintPriority`
- `ComplaintStatus`

Do not create meaningless enums just to increase abstraction.

---

# MODEL RELATIONSHIPS TO IMPLEMENT

At minimum, implement and test relationships such as:

- User -> ownedCompanies
- User -> companyMemberships
- Company -> owner
- Company -> memberships
- Company -> documents
- Company -> locations
- Company -> approvalLogs
- Company -> settings
- Company -> serviceAreas
- Company -> servicePlans
- Company -> bills
- Company -> payments
- Company -> collectionZones
- Company -> complaints
- State -> lgas
- Lga -> state
- Lga -> communities
- Community -> lga
- Community -> properties
- Community -> companyServiceAreas
- Property -> community
- Property -> occupancies
- Property -> providerAssignments
- Property -> currentProviderAssignment helper/scope
- Resident -> user
- Resident -> occupancies
- Resident -> currentOccupancy helper/scope
- PropertyOccupancy -> resident
- PropertyOccupancy -> property
- PropertyOccupancy -> bills
- ServicePlan -> company
- Bill -> company
- Bill -> occupancy
- Bill -> servicePlan
- Bill -> payments
- Payment -> company
- Payment -> bill
- Payment -> receipt
- Receipt -> payment
- CollectionZone -> company
- CollectionZone -> properties
- CollectionSchedule -> zone
- CollectionRecord -> property
- CollectionRecord -> collector
- Complaint -> company
- Complaint -> resident
- Complaint -> occupancy
- Complaint -> assignee

Use model scopes such as `current()`, `active()`, `approved()`, `pendingReview()`, `successful()`, `unpaid()`, and `overdue()` where appropriate.

---

# UUID POLICY

Use auto-incrementing/internal bigint IDs for relational joins and foreign keys.

Use UUIDs for public-facing resource identifiers.

Do not use UUIDs as foreign keys unless there is a specific requirement.

Use route model binding by UUID only where public routes need it; do not break internal relationship loading.

---

# INDEXING EXPECTATIONS

Add indexes based on real query patterns, including examples such as:

- companies: `(status, submitted_at)`;
- memberships: `(company_id, status)`, `(user_id, status)`;
- company documents: `(company_id, status)`;
- company locations: `(company_id, status)`, `(company_id, is_head_office)`;
- service areas: `(company_id, status)`, `(community_id, status)`;
- properties: `(community_id, status)`;
- provider assignments: `(property_id, assigned_to)`, `(company_id, assigned_to)`;
- occupancies: `(resident_id, move_out_date)`, `(property_id, move_out_date)`;
- service plans: `(company_id, status)`;
- bills: `(company_id, status)`, `(property_occupancy_id, status)`, `(due_at, status)`;
- payments: `(company_id, status)`, `(bill_id, status)`, gateway reference;
- collection records: `(company_id, collected_at)`, `(property_id, collected_at)`;
- complaints: `(company_id, status)`, `(assigned_to, status)`.

Do not add indexes blindly; align them with query patterns and uniqueness requirements.

---

# PHASE 1 TESTS

Create focused tests that prove the foundation is correct.

At minimum verify:

1. company belongs to an owner user;
2. user can have company membership;
3. company has multiple historical locations;
4. resident can exist without a login user;
5. resident can have sequential property occupancies;
6. old occupancy remains after a new occupancy is created;
7. property can have sequential provider assignments;
8. old provider assignment remains after provider change;
9. property has no permanent resident/provider FK shortcuts;
10. bill belongs to a specific historical occupancy and issuing company;
11. bill remains related correctly after resident later moves;
12. payment belongs to bill/company and unique payment reference is enforced;
13. receipt uniqueness rules are enforced;
14. company-scoped invoice uniqueness is enforced;
15. relevant enum casts work;
16. current/active model scopes return the correct rows;
17. core foreign keys reject invalid references.

If database-level exclusion of overlapping date ranges is not practical in MySQL, document that the non-overlap invariant will be enforced in Phase 5 Services using transactions/locks, while still adding supporting indexes and uniqueness rules for open/current records where possible.

---

# OUTPUT REQUIRED AFTER PHASE 1

When Phase 1 is complete:

1. run migrations from a clean database;
2. run the Phase 1 test suite;
3. report migration/test results;
4. list every table created;
5. list every model and enum created;
6. summarize important relationships;
7. summarize important indexes and unique constraints;
8. explain any schema decisions that differ from this prompt and why;
9. identify assumptions that should be reviewed before Phase 2;
10. STOP.

Do not start authentication panels, Services, controllers, Form Requests, Filament resources, or Livewire UI until Phase 1 has been reviewed and approved.
