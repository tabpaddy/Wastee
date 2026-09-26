# Waste Management SaaS — Required Engineering Skills and Implementation Standards

This file defines the skills Codex should apply while building the project. It is not a package list; it is the expected engineering behavior for this codebase.

## 1. Laravel Application Architecture

Codex must be capable of implementing and maintaining:

- Laravel 12 application structure;
- Eloquent models and relationships;
- migrations, indexes, constraints, and seeders;
- Services for business workflows;
- Form Requests for validation/authorization;
- Policies and permission checks;
- events/listeners/jobs/notifications;
- observers and cache invalidation;
- queues and retries;
- filesystem/storage;
- transactions and concurrency-safe workflows.

The preferred architecture is:

`UI/HTTP -> Form Request / Filament validation -> Service -> Eloquent Models -> Database`

Reusable database queries belong in model scopes/query helpers.

## 2. Filament

Codex must understand:

- multiple Filament panels;
- panel access control;
- resources;
- forms;
- tables;
- filters;
- relation managers;
- pages;
- widgets;
- actions;
- notifications;
- tenant-aware query scoping;
- permission-based navigation visibility;
- avoiding business logic inside Filament resources/pages.

The application will have at least:

- Platform Admin Filament panel;
- Waste Company Filament panel.

## 3. Livewire

Codex must use Livewire for the customer/resident portal.

Expected practices:

- small focused components;
- server-side validation;
- authorization before actions;
- Services for business workflows;
- pagination for large lists;
- loading/error/empty states;
- no duplicated billing/payment rules inside components.

## 4. Spatie Laravel Permission

Use Spatie Laravel Permission for RBAC.

Expected skills:

- roles;
- permissions;
- role-permission assignment;
- user-role assignment;
- company-scoped/team-aware roles;
- global/platform permissions;
- cache behavior;
- protected owner/platform roles;
- permission-based authorization rather than scattered role-name checks.

Company owners must be able to invite staff and assign company-available roles/permissions according to policy.

## 5. Multi-Tenant Data Isolation

Codex must understand row-level tenant isolation in a shared database.

Rules:

- resolve current company from authenticated membership/context;
- scope operational queries by company;
- do not trust `company_id` from user input;
- validate cross-table records belong to the correct tenant before mutation;
- platform admins are the only actors with cross-company visibility, subject to permission.

The code must prevent IDOR-style access between companies.

## 6. Relational Data Modeling

Codex must be comfortable designing temporal/history-aware relations.

Key concepts in this project:

- company can have many office locations over time;
- resident can occupy many properties over time;
- property can be serviced by different companies over time;
- company can serve multiple communities;
- bills/payments/receipts must preserve historical correctness.

Do not model changing relationships as one mutable foreign key when history matters.

## 7. Migration Design

Every migration should deliberately define:

- primary key;
- UUID where public addressing is needed;
- foreign keys;
- delete/update behavior;
- nullable rules;
- defaults;
- unique constraints;
- composite indexes;
- timestamp precision when useful;
- money precision/scale;
- soft-delete policy where applicable.

Important invariants should be enforced by the database where practical.

Examples:

- unique company slug;
- unique registration number when present;
- unique invoice number per company;
- unique payment reference;
- unique receipt number per company;
- uniqueness/indexes that support one active/current temporal assignment pattern.

## 8. Eloquent Modeling

Models should implement:

- `$fillable` or guarded strategy consistently;
- casts;
- enum casts;
- relationships;
- query scopes;
- route-key UUID strategy where used;
- aggregate helpers that do not contain large workflows.

Examples of scopes:

- `pendingReview()`
- `approved()`
- `active()`
- `current()`
- `forCompany($companyId)`
- `unpaid()`
- `overdue()`
- `successful()`

Avoid repeated raw query logic outside models when the same query concept is reused.

## 9. Service-Layer Engineering

Services own workflows that change business state.

Codex must know when to use:

- `DB::transaction()`;
- `lockForUpdate()`;
- idempotency checks;
- events after successful state changes;
- queued jobs;
- immutable history records.

Examples:

### Move resident

Transaction should:

1. lock current occupancy if necessary;
2. close old occupancy;
3. validate target property/service eligibility;
4. create new occupancy;
5. preserve all previous bills/history;
6. dispatch follow-up events only after the transaction succeeds.

### Change property provider

Transaction should:

1. verify company service coverage;
2. close current assignment;
3. create new assignment;
4. preserve old billing/collection records.

### Approve company

Transaction should:

1. validate required onboarding/compliance state;
2. update company status;
3. append approval log;
4. activate appropriate owner membership state if needed;
5. queue notification after commit.

## 10. Form Requests

Use Form Requests for HTTP requests rather than controller-level `$request->validate()` for substantial operations.

Expected abilities:

- authorization in `authorize()` when appropriate;
- `rules()`;
- custom messages/attributes only where helpful;
- `prepareForValidation()` sparingly;
- `passedValidation()` sparingly;
- custom Rules for cross-record invariants when suitable.

Business state transitions still belong in Services, not validation classes.

## 11. Caching and Observers

Codex should be able to implement:

- deterministic cache key conventions;
- per-company cache namespaces;
- cache invalidation using observers;
- cache tags only if supported by the selected driver;
- cache warming only where justified.

Observers must not silently perform payment or billing workflows.

## 12. Payment Engineering

When the payment phase begins, Codex must support:

- gateway initiation;
- gateway reference persistence;
- webhook signature verification;
- server-to-server verification;
- idempotent webhook handling;
- duplicate callback protection;
- pending/success/failed states;
- reconciliation-friendly data;
- partial payments if enabled;
- receipt generation only after confirmed success;
- no trust in client-side success redirects.

The first gateway can be Paystack, but gateway-specific code must be isolated behind a payment integration/service abstraction so another gateway can be added later.

## 13. Security

Required security practices:

- authorization on every protected state-changing action;
- tenant isolation;
- mass-assignment protection;
- validated uploads;
- private storage for compliance documents;
- signed/authorized receipt access where required;
- rate limits on authentication/payment-sensitive endpoints;
- CSRF protection for web flows;
- no logging of secrets/passwords/full sensitive gateway payloads unnecessarily;
- secure password hashing;
- email verification where required;
- safe handling of invitation tokens.

## 14. Testing

Codex should build feature/model tests for each phase.

Important test patterns:

- factories with sensible states;
- database refresh;
- authorization tests;
- multi-tenant access denial;
- transition tests;
- relationship/history tests;
- payment idempotency tests;
- queue/notification fakes where appropriate.

Phase 1 tests should focus on migrations, constraints, model relationships, enum casts, scopes, and temporal history behavior.

## 15. Performance

Codex should:

- add indexes before performance becomes a problem;
- avoid N+1 queries;
- eager load intentionally;
- paginate large admin/customer tables;
- use aggregate queries for dashboards;
- avoid loading entire tenant datasets into memory;
- keep reporting queries outside request hot paths when expensive.

## 16. Nigerian Operational Context

The system is being designed first for Nigeria.

Support:

- NGN as default currency;
- states and LGAs as normalized location data;
- community/estate/local-area service structures;
- company relocation and multi-office operation;
- residents/tenants moving between properties;
- property service-provider changes;
- online payment workflows common to Nigeria.

Do not make hardcoded assumptions that prevent future operation in another country. Country-specific reference data should be seedable/configurable.

## 17. Documentation Discipline

For each phase, Codex should report:

- files created/changed;
- database tables/columns added;
- important indexes and constraints;
- model relationships added;
- enums added;
- tests run and result;
- assumptions;
- issues requiring a decision.

Do not begin the next phase automatically.
