# AcademyHub architecture

## Decision summary

AcademyHub is a Laravel 12 modular monolith with a Vue 3 and TypeScript browser application. The first release uses session authentication and same-origin JSON endpoints. The local and production database target is MySQL 8 or compatible MariaDB; SQLite is not used for the application or automated tests. Redis will back queues, cache, locks and rate limits. Expensive imports, exports, PDF generation and outbound messages must run as queued jobs.

Local XAMPP development uses `academy_management`. Automated tests use the separate `academy_management_test` database so `RefreshDatabase` can rebuild test tables without touching development data. Both databases use `utf8mb4` and are accessible through the local phpMyAdmin installation.

Business code is organised by domain as each slice is added: Tenancy, Identity, Academic Structure, Students, Groups, Attendance, Examinations, Performance, Parent Portal, Finance, Communications, Reports, Subscriptions and Platform Administration. Controllers validate and coordinate; domain actions/services own state transitions and transactions; policies own authorisation.

## Tenant boundary

`organizations` is the commercial tenant root. `branches` are operational sub-tenants: every centre has independently scoped academic years, levels, subjects, students, groups, attendance, examinations, finance, communications, files and reports. Operational tables carry both a non-null `organization_id` and `branch_id`; both values are verified by server-resolved context.

`organization_memberships` is the only user-to-organisation entry point. Owners and organisation administrators may access all active branches. Other staff require an explicit `branch_user` assignment. Organisation and branch middleware resolve the active contexts from membership plus the server-side session; request-supplied IDs never prove access. Cross-organisation and cross-branch IDs return 404 or 403 without revealing record details.

One organisation subscription covers its allowed branch count. A branch is deliberately not a separate billing tenant, so an owner has one account while each centre remains operationally isolated.

## Operational invariants

- Money is stored as integer EGP piastres.
- Student public IDs and card tokens are opaque and immutable/revocable respectively.
- Attendance, card replacement, payment and message dispatch operations are idempotent.
- Published marks and financial corrections are append-only/audited transitions.
- Private uploads never use public disk URLs.
- Parent links store only a hash of the random token and can be revoked immediately.
- Arabic and English copy are first-class; direction is set at the document boundary.
- Branch context is mandatory for all operational reads and writes; organisation-wide screens must be explicit and permission-protected.

## Deployment shape

Web, queue worker, scheduler, Redis, MySQL and S3-compatible object storage are separate runtime concerns around one application. Production requires HTTPS, secure cookies, `APP_DEBUG=false`, queue supervision, monitored scheduler runs, database/object backups, log aggregation and secret rotation.
