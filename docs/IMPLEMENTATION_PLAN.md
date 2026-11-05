# AcademyHub implementation plan

> Continuation note: [`CONTINUATION_HANDOFF.md`](CONTINUATION_HANDOFF.md) is the machine-to-machine quick-start document. Read it together with this checklist and `CURRENT_STATUS.md` before making changes.

This file is the resumable source of truth. A checked item is implemented and verified, not merely designed.

## Phase 0 — discovery and product foundation

- [x] Preserve the complete supplied specification in `MASTER_PRODUCT_PROMPT.md`.
- [x] Audit the Laravel starter and reusable Exousia UI/tenancy patterns.
- [x] Record architecture, domain relationships and security boundaries.
- [x] Establish the visual system and responsive product shell.

## Phase 1 — identity, tenancy and academic foundation

- [x] Laravel 12 organisation, membership, branch, academic-year, level and subject schema.
- [x] Transactional owner registration with a 14-day trial and sensible Egyptian defaults.
- [x] Session login/logout and rate limiting.
- [x] Organisation middleware and request-scoped context.
- [x] Operational centre isolation: server-resolved branch context, owner centre creation/switcher and independent academic data.
- [x] Centre-specific academic year, educational level and subject management.
- [x] Staff invitations, role/status administration and explicit multi-centre assignments.
- [x] System roles plus organisation-scoped custom roles with granular permission sets.
- [x] Vue 3 + TypeScript landing, registration, login and protected dashboard.
- [x] Signed email verification and protected password recovery.
- [x] Explicit centre workspace switching with server-side access revalidation.
- [x] Complete core Arabic translation catalogue, live label/attribute translation and RTL toggle foundation.
- [x] Audit ledger for centre, academic-structure and staff-access changes.
- [x] Per-member allow/deny permission overrides and owner-facing filtered audit-log viewer.

## Phase 2 — students and teaching operations

- [x] Initial branch-scoped student/guardian/enrolment records and concurrency-safe centre codes; editing, archival and richer guardian management remain iterative work.
- [x] Teacher student record editing and archival with branch-scoped authorization and audit events.
- [ ] Server-paginated student directory, filters and bulk actions.
- [ ] CSV/XLSX staged import with preview, queue progress and error workbook.
- [x] Initial branch-scoped teaching groups with academic links, capacity tracking and active enrolment counts.
- [x] Recurring group schedules with branch overlap conflict validation and teacher-readable schedule API.
- [ ] Revocable Code 128/QR cards plus A4 PDF generation.

## Phase 3 — attendance

- [ ] Full-screen USB scanner flow and manual fallback.
- [x] Attendance session and duplicate-safe scanner record foundation linked to teaching groups.
- [x] Responsive teacher attendance desk connected to live session and scanner APIs.
- [x] Session closure automatically finalises unscanned active group students as absent and exposes status summaries.
- [x] Manual bulk attendance fallback for scanner outages and forgotten cards.
- [ ] Idempotent attendance processing and duplicate feedback.
- [ ] Session closure/absence job, corrections and reports.
- [ ] Authorised-device offline queue with explicit pending state.

## Phase 4 — examinations and performance

- [ ] Exam targeting and expected participants.
- [ ] Physical exam attendance and keyboard-first marks entry/import.
- [ ] Draft/review/publish/lock workflow with correction audit.
- [ ] Student, group and subject analytics plus early warnings.

## Phase 5 — parent portal and communications

- [x] Hashed, expiring, revocable student-scoped parent links.
- [ ] Responsive Arabic/English performance page.
- [x] Responsive Arabic/English parent student summary page with secure-link expiry/revocation states.
- [ ] WhatsApp consent, templates, queue, retry and delivery log.

## Phase 6 — finance, subscriptions and platform admin

- [ ] Fee plans, invoices, instalments, receipts, refunds and expenses.
- [ ] Plan entitlements, trial lifecycle and Paymob abstraction.
- [x] Super-admin plan catalogue with Egypt-ready Starter, Growth and Academy limits for centres, students, staff and groups.
- [x] Encrypted Paymob credential storage and protected platform settings API/screen; checkout activation remains dependent on merchant credentials and subscription payment records.
- [x] Super-admin fixed navigation shell and global organisation, subscription, identity, security and support overview console.
- [ ] Super-admin plan editing, usage metering and operational write actions.

## Phase 7 — reports, hardening and release

- [ ] Large-dataset exports, scheduled reports and dashboard aggregates.
- [ ] Horizon/Redis, storage, backups, monitoring and deployment guide.
- [ ] Accessibility, RTL, responsive and performance QA.
- [ ] Full acceptance scenario and security release gates.

## Verification log

- 2026-11-05: repository and environment audited; Laravel 12.71 / PHP 8.2 / Node 22 confirmed.
- 2026-11-05: configured database migration passed for the academy foundation schema.
- 2026-11-05: PHP suite passed: 5 tests, 12 assertions. Coverage includes transactional registration, active-membership enforcement and cross-tenant dashboard isolation.
- 2026-11-05: Vue TypeScript check and Vite production build passed (2,132 modules; 190.37 kB main JS / 71.66 kB gzip).
- 2026-11-05: route and live HTTP smoke checks passed (`/` returned 200; anonymous `/api/session` returned the expected unauthenticated payload).
- 2026-11-05: Composer reported no known security advisories. Browser screenshot QA remains pending because the current environment exposed no browser or desktop surface to the UI controller.
- 2026-11-05: product scope expanded so every branch/centre is an operational sub-tenant with independent academic, student, attendance, examination, finance, file and reporting data under one organisation subscription.
- 2026-11-05: centre-as-workspace migration applied. Existing academic foundation records were backfilled to each organisation's head office; centre selection is stored server-side and revalidated on every operational request.
- 2026-11-05: database-level non-null branch ownership is enforced for academic years, educational levels and subjects after legacy backfill.
- 2026-11-05: owners can create and switch centres from the application shell. New centres start with an empty academic workspace and never inherit transactional data.
- 2026-11-05: centre-specific academic years, levels and subjects can be created from the dashboard. Assistants are denied management writes until granular permissions are implemented.
- 2026-11-05: verification passed with 8 PHP tests and 42 assertions, including cross-organisation isolation, cross-centre isolation, owner switching, staff assignment restrictions and independent academic structure. Vue typecheck and production build passed (2,132 modules; 203.82 kB main JS / 74.93 kB gzip).
- 2026-11-05: local runtime moved from SQLite to the same XAMPP MySQL/MariaDB pattern as Exousia. `academy_management` is the development database and `academy_management_test` is the isolated automated-test database; both use `utf8mb4` and are available through phpMyAdmin.
- 2026-11-05: MySQL verification exposed and fixed a migration index-order issue that SQLite had not detected. A clean MySQL migration now passes through all centre-tenancy migrations.
- 2026-11-05: staff access slice completed: secure invitations, direct addition for existing accounts, system/custom roles, explicit centre assignment, status changes, revocation and one-time acceptance UI.
- 2026-11-05: invitation tokens are stored only as SHA-256 hashes, expire after seven days and verify new users' email ownership on acceptance. Development mail uses the log transport until SMTP is configured.
- 2026-11-05: audit logging covers centre creation/switching, academic years/levels/subjects, invitations, access updates and custom-role creation.
- 2026-11-05: MySQL suite passes with 11 tests and 76 assertions; Vue typecheck and production build pass (2,136 modules; 217.49 kB main JS / 78.63 kB gzip).
- 2026-11-06: added an explicit `is_platform_admin` identity flag and an idempotent local demo seeder. The super-admin and teacher demo accounts were created in MySQL, attached to the fictional demo academy and verified through live login/session/logout HTTP requests.
- 2026-11-06: authentication responses now refresh the SPA's CSRF token after session regeneration, fixing subsequent protected POST requests such as logout and centre changes.
- 2026-11-06: identity hardening completed. New owner accounts receive signed verification links and cannot enter organisation or centre routes before verification; resend and sign-out remain available.
- 2026-11-06: added non-enumerating password recovery with expiring broker tokens, strong-password validation and modern responsive recovery screens.
- 2026-11-06: MySQL suite passes with 15 tests and 96 assertions; Vue typecheck and production build pass (2,142 modules; 226.46 kB main JS / 81.01 kB gzip).
- 2026-11-06: added a dedicated platform-administrator control plane. Super admins now land outside academy workspaces and see only global SaaS operations; every sidebar item opens its own working section.
- 2026-11-06: platform routes are protected by a separate `platform_admin` boundary and tested for both privileged access and tenant-user denial. MySQL suite passes with 17 tests and 101 assertions; Vue typecheck and production build pass (2,144 modules; 241.13 kB main JS / 84.78 kB gzip).
- 2026-11-06: completed fine-grained staff exceptions with explicit allow/deny/inherit states. Denials take precedence over role permissions and every change is audited.
- 2026-11-06: added an organisation-scoped audit viewer with event, actor and centre filters, expandable before/after records and owner/admin authorization. Teachers are denied access.
- 2026-11-06: documented production SMTP, sender-domain DNS, HTTPS URL and persistent queue-worker requirements in `PRODUCTION_EMAIL.md` while preserving the safe local log transport.
- 2026-11-06: MySQL suite passes with 19 tests and 111 assertions; Vue typecheck and production build pass (2,146 modules; 247.66 kB main JS / 86.79 kB gzip).
- 2026-11-06: introduced a persistent English/Arabic locale store and a live language control. The document language/direction, fixed sidebars, mobile drawers, inputs, modals and content offsets now mirror without reloading.
- 2026-11-06: wired Arabic catalogue copy and Arabic-first data-name selection into login, registration, the academy operations shell and the platform-admin control plane. Remaining public/recovery and detailed modal copy stays explicitly tracked before the localisation checklist is closed.
- 2026-11-06: MySQL suite remains at 19 tests and 111 assertions; Vue typecheck and production build pass (2,149 modules; 258.54 kB main JS / 91.32 kB gzip).
- 2026-11-06: created a separate teacher operations shell and role-based dashboard redirect. Teachers now see only teaching overview, students, attendance, schedule, exams/marks and performance pages, with responsive working navigation and module-ready empty states.
- 2026-11-06: teacher-panel production build passes (2,151 modules; 271.85 kB main JS / 95.12 kB gzip); the MySQL suite remains green at 19 tests and 111 assertions.
- 2026-11-06: connected the teacher student directory to MySQL. Teachers with permission can register a student, primary guardian and academic enrolment transactionally, search/filter the active centre and receive concurrency-safe `STU-######` codes.
- 2026-11-06: migration `000006` applied to local MySQL; suite passes with 21 tests and 121 assertions. Teacher UI production build passes (2,151 modules; 278.88 kB main JS / 96.90 kB gzip).
- 2026-11-06: teacher student directory now sends server-side page parameters, resets pagination when search/level filters change and exposes previous/next controls for multi-page centre rosters.
- 2026-11-06: added branch-scoped student editing and owner/admin archival with enrolment closure and audit events; MySQL suite passes with 22 tests and 128 assertions, and the Vue production build passes (2,151 modules; 280.23 kB main JS / 97.31 kB gzip).
- 2026-11-06: added MySQL teaching-group and group-enrolment foundations with branch-safe creation/listing, capacity metadata and audit logging; migration `000007` applied and suite passes with 23 tests and 135 assertions.
- 2026-11-06: added migration `000008` for subscription plans, organisation subscriptions and encrypted Paymob settings. Added `/admin/settings/plans`, protected plan editing and Paymob configuration APIs; MySQL suite passes with 24 tests and 140 assertions, and the production build passes (2,153 modules; 287.39 kB main JS / 99.16 kB gzip).
- 2026-11-06: added migration `000009` for subscription payments plus Paymob checkout creation, callback HMAC validation and subscription activation endpoints. Paymob remains disabled until valid merchant credentials are configured.
- 2026-11-06: added migration `000010` for attendance sessions and records; group-linked sessions, permission checks, duplicate-safe scans and closure are covered by the MySQL suite (25 tests, 145 assertions).
- 2026-11-06: connected `/teacher/attendance` to live groups and attendance APIs with session creation, scanner feedback, duplicate handling and closure; Vue build passes (2,155 modules; 295.19 kB main JS / 101.14 kB gzip).
- 2026-11-06: attendance closure now creates missing absent records atomically and `/sessions/{id}/report` returns attendance records and status totals; attendance feature tests pass with 33 assertions.
- 2026-11-06: added validated manual bulk marking for attendance sessions; the attendance feature slice now passes with 35 assertions.
- 2026-11-06: added migration `000011` for recurring group schedules, overlap conflict validation and schedule listing; MySQL feature coverage now passes with 26 tests and 156 assertions.
- 2026-11-06: added migration `000012` and parent link APIs for secure expiring/revocable student views; parent-link feature coverage passes with 6 assertions.
- 2026-11-06: added the public `/parent/{token}` bilingual responsive portal view with RTL support and expired-link handling; frontend build passes (2,157 modules; 298.82 kB main JS / 102.28 kB gzip).
- 2026-11-06: replaced the corrupted partial locale catalogue with a clean bilingual core map and added a live DOM translator for legacy hard-coded UI text, placeholders and titles; RTL behavior remains persistent across route changes.
- 2026-11-06: moved the bilingual catalogue into explicit `resources/lang/en.json` and `resources/lang/ar.json` files and updated the locale store to load them directly; production build passes (2,159 modules; 290.61 kB main JS / 98.95 kB gzip).
- 2026-11-06: expanded the locale catalogue for the live attendance desk and Paymob plan settings, retained persistent English/Arabic switching and verified RTL-aware production build (2,155 modules; 296.11 kB main JS / 101.43 kB gzip).
- 2026-11-06: expanded `resources/lang/en.json` and `resources/lang/ar.json` to 204 shared keys covering teacher, attendance, platform, plans, authentication, parent and landing-page copy. The locale store now translates normalized embedded phrases and common fallback words; JSON validation and the Vue/Vite production build pass (2,161 modules; 310.96 kB main JS / 105.08 kB gzip).
- 2026-11-06: added `CONTINUATION_HANDOFF.md` with cross-device setup instructions, current architecture, verification baseline, known caveats and the prioritized remaining queue.
