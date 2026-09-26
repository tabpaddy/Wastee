# Waste Management SaaS — Codex Agent Guide

## 1. Project Mission

Build a multi-tenant waste-management SaaS for Nigeria using the Laravel ecosystem.

The platform has three primary experiences:

1. **Platform Admin** — Filament panel for platform operations, company approval, compliance review, platform oversight, and system-level reporting.
2. **Waste Management Company** — Filament panel for company owner and staff to manage company operations, staff, customers/residents, properties, billing, payments, receipts, collections, complaints, and reports.
3. **Resident / Customer** — Livewire UI for customers to access bills, make payments, download receipts, see payment history, view service/collection information, and submit complaints.

The system MUST preserve historical accuracy when:

- a resident moves from one property to another;
- a property changes the waste-management company servicing it;
- a waste-management company relocates its office or opens another office;
- a company expands or reduces its service coverage;
- a bill or payment is viewed years after the underlying resident/property/company relationships have changed.

This is not a social-media product. It is an operational SaaS and billing platform.

---

## 2. Mandatory Technical Stack

- PHP 8.3+ compatible with the selected Laravel version.
- Laravel 12.
- Filament for Platform Admin and Company dashboards.
- Livewire for Resident / Customer UI.
- MySQL / MariaDB.
- Spatie Laravel Permission for roles and permissions.
- Laravel Form Requests for validation at HTTP boundaries.
- Laravel Services for business/application logic.
- Eloquent Models for relationships, reusable query scopes, casts, and persistence-oriented querying.
- Laravel Observers for cache invalidation and model lifecycle side effects that belong at the persistence boundary.
- Laravel Notifications / Mail for notifications.
- Queues for slow or retryable operations such as email, receipt generation, and external payment follow-up.
- Laravel Cache for frequently read reference/configuration data.
- Laravel Storage for company documents and generated receipts.

Do not introduce a second frontend framework for the MVP unless explicitly requested.

---

## 3. Architecture Rules

### 3.1 Hybrid Service + Model Architecture

The project uses a hybrid architecture:

- **Controllers / Filament resources / Livewire components stay thin.**
- **Form Requests validate and authorize incoming HTTP data.**
- **Services contain business workflows and transaction boundaries.**
- **Models contain relationships, casts, local/global scopes, query helpers, computed attributes, and persistence-oriented methods.**
- **Observers clear or refresh relevant caches and handle model lifecycle side effects.**
- **Policies / permissions decide whether an actor may perform an action.**

Do not put large SQL/Eloquent query chains directly in controllers, Filament pages, resources, or Livewire components. Reusable data-access queries belong in model scopes/query methods. Multi-model business workflows belong in Services.

### 3.2 Thin HTTP / UI Layer

Controllers, Filament actions, and Livewire actions should typically do only this:

1. receive validated input;
2. authorize the action;
3. call the appropriate Service;
4. return a response / notification / redirect.

They must not own domain rules such as company approval, move-in/move-out, payment confirmation, receipt numbering, provider reassignment, or staff invitation acceptance.

### 3.3 Service Rules

Services should:

- have one clear responsibility;
- be grouped by domain rather than by controller;
- use `DB::transaction()` for workflows that update multiple related records;
- use row locking when concurrent modification could create duplicates or financial inconsistencies;
- be idempotent where external callbacks or retries are possible;
- dispatch events/jobs after persistence where appropriate;
- never trust client-supplied tenant/company IDs when they can be resolved from authenticated context.

Examples:

- `CompanyRegistrationService`
- `CompanyApprovalService`
- `CompanyStaffService`
- `ResidentService`
- `PropertyOccupancyService`
- `PropertyProviderAssignmentService`
- `BillingService`
- `PaymentService`
- `ReceiptService`
- `CollectionService`
- `ComplaintService`

Do not create Action classes unless explicitly requested.

---

## 4. Multi-Tenancy Rules

This is application-level multi-tenancy using one database for the MVP.

- A waste-management company is a tenant.
- Tenant-owned operational tables MUST include a `company_id` where direct tenant ownership is required for safe filtering and efficient queries.
- Shared physical/reference entities such as Nigerian geographic data and properties are not automatically owned by a company.
- Never expose one company's operational data to another company.
- All company-panel queries must be scoped to the authenticated user's active company context.
- Never accept arbitrary `company_id` from a company user request and trust it directly.
- Platform Admin may query across companies only with explicit platform permissions.

Historical relationships must not be destroyed merely because the current tenant/provider changes.

---

## 5. Identity and Authentication Model

Use `users` as the authentication identity table for human accounts.

A user may represent:

- a platform administrator;
- a waste company owner;
- a waste company staff member;
- a resident/customer with portal access.

Keep business/domain profiles separate from authentication identity.

### Resident identity

`residents` must have an optional `user_id` so a company can register a resident before that resident creates/claims a portal account.

A resident is NOT permanently owned by a waste company. The current servicing company is determined from the resident's current property occupancy and the property's current provider assignment.

### Company membership

Company owners and staff are linked to companies through `company_memberships`.

The company owner is also recorded explicitly on the `companies` table for ownership semantics.

Use Spatie Permission for authorization. Company roles must be tenant-aware/team-aware. Platform-level permissions must remain distinguishable from company-scoped roles.

Do not hardcode role names for authorization when a permission check is more appropriate. The `Owner` role may be protected from deletion, but permissions should drive capabilities.

---

## 6. Company Registration and Approval Rules

Waste-management companies register themselves.

Flow:

1. Owner creates a user account.
2. Owner starts company onboarding.
3. Company record is created in `draft` or `pending` state.
4. Owner enters company details and uploads required compliance documents.
5. Owner submits for review.
6. Platform Admin reviews company and documents.
7. Platform Admin approves, rejects, or requests correction.
8. Rejected/correction-required companies may update permitted fields and resubmit.
9. Only approved/active companies may run normal operational workflows.
10. Approval history must remain auditable.

Company status changes must use an enum/value object rather than uncontrolled strings.

Do not overwrite rejection/approval history. Use `company_approval_logs` for immutable review history.

---

## 7. Location and Mobility Rules

### 7.1 Company location history

Do not store a single permanent address directly on `companies` as the source of truth.

Use `company_locations`.

A company can:

- relocate;
- have multiple offices;
- change head office;
- close an office without deleting its history.

Company location records should support effective dates and active/inactive state.

### 7.2 Resident movement

Do not store `resident_id` directly on `properties`.

Use `property_occupancies` to record:

- resident;
- property;
- move-in date;
- move-out date;
- current/active state.

Moving a resident must close the previous occupancy and create a new occupancy inside one transaction.

Historical occupancies must not be deleted just because a resident moves.

### 7.3 Provider changes

Do not permanently bind a property to one waste-management company.

Use `property_company_assignments` to record which company serviced a property during each period.

Changing provider must close the previous assignment and create a new assignment.

Historical assignments must remain queryable.

### 7.4 Service coverage

Use `company_service_areas` to describe communities/areas a company is authorized or configured to serve.

A service area is not a substitute for `property_company_assignments`.

- `company_service_areas` = where a company may/does operate.
- `property_company_assignments` = which provider actually serviced a specific property over time.

---

## 8. Billing and Financial Integrity Rules

Bills must remain historically correct after residents move or provider relationships change.

A bill should contain at least:

- `company_id` — company that issued the bill;
- `property_occupancy_id` — resident/property relationship at billing time;
- `service_plan_id`;
- unique invoice number;
- billing period;
- subtotal/amount;
- discount;
- late fee;
- total;
- outstanding amount;
- status;
- issue date;
- due date;
- paid date when applicable;
- creator;
- historical snapshot fields where needed for printable invoices/receipts.

Do not calculate old invoices using only current mutable profile data. Store immutable billing snapshots or equivalent fields needed to reproduce the historical document correctly.

Use decimal columns for money. Never use floats for money.

Payments must be separate from bills because:

- a bill can have multiple payment attempts;
- failed/pending payment attempts must be retained;
- a successful payment may be partial if partial payments are enabled;
- gateway callbacks can be retried.

Payment references must be unique and payment confirmation must be idempotent.

Receipts must only be generated from successful/confirmed payments.

---

## 9. Core MVP Domains and Tables

Phase 1 should establish migrations and models for the following.

### Platform / Identity

- `users`
- Spatie permission tables

### Company onboarding / tenancy

- `companies`
- `company_memberships`
- `company_documents`
- `company_locations`
- `company_approval_logs`
- `company_settings`
- `company_service_areas`
- `staff_invitations`

### Geography / property

- `states`
- `lgas`
- `communities`
- `properties`
- `property_company_assignments`

### Residents / occupancy

- `residents`
- `property_occupancies`

### Billing / payment

- `service_plans`
- `bills`
- `payments`
- `receipts`

### Waste collection operations

- `collection_zones`
- `collection_zone_properties`
- `collection_schedules`
- `collection_records`

### Customer support

- `complaints`
- optional `complaint_messages` if conversation history is required in MVP

### System

- Laravel notifications table
- `audit_logs` or an approved activity-log implementation

Avoid adding subscriptions/pricing for the SaaS itself until the operational MVP is stable unless explicitly requested.

---

## 10. Model Responsibilities

Models may contain:

- relationships;
- enum casts;
- date/datetime casts;
- decimal casts;
- query scopes;
- query helper methods;
- tenant-safe finders;
- status helpers;
- computed attributes that do not perform business workflows.

Examples of acceptable model scopes:

- `Company::pendingApproval()`
- `Company::approved()`
- `CompanyLocation::active()`
- `PropertyOccupancy::current()`
- `PropertyCompanyAssignment::current()`
- `Bill::unpaid()`
- `Bill::overdue()`
- `Payment::successful()`
- `Complaint::open()`

Do not put cross-aggregate workflows in a model method.

Bad:

`$resident->moveToProperty($property)` if it updates occupancy, billing, notifications, and provider relationships.

Good:

`PropertyOccupancyService::moveResident(...)` with model scopes/relationships supporting the transaction.

---

## 11. Validation Rules

Every normal HTTP form submission must use a dedicated Laravel Form Request where appropriate.

Examples:

- `RegisterCompanyRequest`
- `SubmitCompanyForReviewRequest`
- `ReviewCompanyRequest`
- `InviteStaffRequest`
- `StoreResidentRequest`
- `MoveResidentRequest`
- `AssignPropertyProviderRequest`
- `StoreServicePlanRequest`
- `GenerateBillRequest`
- `InitiatePaymentRequest`
- `StoreComplaintRequest`

Filament-native validation can be used for UI field validation, but critical domain validation must also exist in the domain/service boundary so it cannot be bypassed by another entry point.

---

## 12. Caching and Observers

Cache only data with a clear invalidation strategy.

Good cache candidates:

- company settings;
- approved company profile data;
- service plans;
- geographic reference data;
- company permissions/feature settings;
- dashboard aggregates where eventual consistency is acceptable.

Observers should clear relevant cache keys after create/update/delete/restore operations.

Do not put large business workflows inside observers.

Do not use observers to hide critical financial behavior.

---

## 13. Database Rules

- Prefer `id` BIGINT primary keys internally unless a different decision is explicitly made.
- Add public `uuid` columns to externally addressable business entities.
- Use internal IDs for foreign keys and joins; use UUIDs for public URLs/API identifiers.
- Use foreign-key constraints.
- Index foreign keys and high-frequency filter columns.
- Add composite indexes for common tenant queries such as `(company_id, status)`.
- Use unique constraints to enforce business invariants wherever possible.
- Use nullable foreign keys only when absence is meaningful.
- Use `restrict`, `cascade`, or `nullOnDelete` deliberately; do not default blindly.
- Prefer soft deletes only when recovery/history is genuinely required.
- Financial records, payments, receipts, approval logs, and historical occupancy/provider records should not be casually hard-deleted.
- Use enums in PHP and appropriate string columns in the database unless DB-level enums are specifically justified.

---

## 14. Historical Data Rules

Never destroy history by overwriting current relationships.

Examples:

- Moving resident: close old occupancy; create new occupancy.
- Company relocation: close/deactivate old office location; create/activate new location.
- Provider change: close old property-company assignment; create new assignment.
- Approval review: append approval log.
- Payment retry: create/update the payment attempt safely; never erase the failed attempt to pretend it never occurred.

Where historical documents depend on mutable names/addresses, store snapshot fields.

---

## 15. Filament Structure

Create separate Filament panels.

### Platform Admin Panel

Responsibilities:

- platform administrators;
- pending company applications;
- company document review;
- approve/reject/request correction;
- suspend/reactivate companies;
- platform-wide read-only operational views where permitted;
- audit/review logs.

### Company Panel

Responsibilities:

- company profile/settings;
- office locations;
- staff/invitations/roles;
- service areas;
- residents;
- properties relevant to the company;
- property occupancy operations;
- service plans;
- billing;
- payment tracking;
- receipts;
- collection zones/schedules/records;
- complaints;
- reports.

Company users must never select another arbitrary tenant from request input to escape scope.

---

## 16. Livewire Customer Portal

The Resident / Customer portal should support, eventually:

- registration/account claim;
- login/logout/password reset;
- current property/occupancy view;
- bills;
- bill details;
- initiate payment;
- payment history;
- receipt download;
- collection/service information;
- complaint creation and tracking;
- profile management.

Livewire components remain thin and call Services for business operations.

---

## 17. Phase Discipline

Codex MUST work phase-by-phase.

Do not skip ahead.

### Phase 1 — Database foundation

Create only:

- enums required by the schema;
- migrations;
- Eloquent models;
- relationships;
- casts;
- query scopes/query helpers;
- factories only where useful for immediate schema tests;
- minimal seeders for required reference data if explicitly part of Phase 1;
- database/model tests validating core constraints and relationships.

Do NOT create yet:

- controllers;
- Services;
- Form Requests;
- Filament resources/pages;
- Livewire components;
- payment gateway integration;
- notifications;
- jobs;
- observers beyond a placeholder only if absolutely required.

At the end of Phase 1, produce a schema review summary and STOP.

### Phase 2 — Authentication, tenancy context, RBAC

Only after Phase 1 approval.

### Phase 3 — Company onboarding + platform approval

Only after Phase 2 approval.

### Phase 4 — Company staff + roles + invitations

### Phase 5 — Geography, properties, residents, occupancy, provider assignment

### Phase 6 — Service plans + billing

### Phase 7 — Payments + receipts

### Phase 8 — Collection operations

### Phase 9 — Complaints + resident portal

### Phase 10 — Caching, observers, audit, notifications, reporting, hardening

Each phase must be independently reviewable and testable.

---

## 18. Testing Rules

Use Pest or PHPUnit consistently with the project choice.

At minimum test:

- database constraints;
- tenant isolation;
- owner/company membership rules;
- approval transitions;
- only one current occupancy for a resident where business rules require it;
- only one current provider assignment for a property at a time;
- move history preservation;
- provider change history preservation;
- bill historical relationships;
- duplicate payment callback/idempotency behavior;
- permissions.

Do not consider a phase complete while tests are failing.

---

## 19. Coding Standards

- Follow PSR-12 and Laravel conventions.
- Prefer typed properties, return types, enums, readonly DTOs where useful, and constructor injection.
- Keep methods small and intention-revealing.
- Avoid magic strings for statuses and types.
- Avoid N+1 queries.
- Use eager loading deliberately.
- Use database transactions for multi-record state transitions.
- Use domain-specific names, not vague names like `data`, `item`, `manager`, or `process` when a clearer term exists.
- Do not overengineer with repositories when Eloquent models/scopes already provide the required persistence abstraction.
- Do not introduce DDD layers, CQRS, event sourcing, or microservices for the MVP unless explicitly requested.

---

## 20. Codex Working Protocol

Before modifying code for a phase:

1. Read this file completely.
2. Inspect the existing project structure and installed package versions.
3. Do not replace existing architectural decisions without explaining a conflict.
4. List the exact files to be created/modified for the current phase.
5. Implement only the current phase.
6. Run migrations/tests/static checks relevant to the phase.
7. Summarize what changed, constraints added, assumptions made, and anything requiring human review.
8. Stop before starting the next phase.

When uncertain, prefer preserving data history, tenant isolation, financial integrity, and simple Laravel conventions over clever abstractions.
