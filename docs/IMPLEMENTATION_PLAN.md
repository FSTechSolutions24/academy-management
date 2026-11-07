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

- [x] Branch-scoped student, guardian and enrolment records with concurrency-safe centre codes; student editing and archival are audited.
- [x] Teacher student record editing and archival with branch-scoped authorization and audit events.
- [x] Manage up to five student guardian contacts with relationship labels, primary-contact selection and per-student link removal.
- [ ] Server-paginated student directory, filters and bulk actions (search, level filters, pagination and permission-gated bulk archival are implemented; automated authorization/isolation coverage remains).
- [ ] CSV/XLSX staged import with preview, queue progress and error workbook (private CSV/XLSX preview, queued import and formula-safe CSV error report are implemented; automated authorization/isolation coverage remains).
- [x] Initial branch-scoped teaching groups with academic links, capacity tracking and active enrolment counts.
- [x] Recurring group schedules with branch overlap conflict validation and teacher-readable schedule API.
- [ ] Revocable Code 128 student cards with A4 print/save-as-PDF, hashed card tokens and attendance scanner lookup (automated authorization/isolation coverage remains).

## Phase 3 — attendance

- [ ] Full-screen USB scanner flow and manual fallback.
- [x] Attendance session and duplicate-safe scanner record foundation linked to teaching groups.
- [x] Responsive teacher attendance desk connected to live session and scanner APIs.
- [x] Session closure automatically finalises unscanned active group students as absent and exposes status summaries.
- [x] Manual bulk attendance fallback for scanner outages and forgotten cards.
- [x] Idempotent attendance processing and duplicate feedback for scanner retries and repeated session closure.
- [x] Attendance status correction history and branch-scoped correction endpoint.
- [ ] Session closure/absence job and richer reports.
- [ ] Authorised-device offline queue with explicit pending state.

## Phase 4 — examinations and performance

- [x] Initial branch-scoped exam creation/listing with subject, date, total marks, notes and draft status.
- [x] Exam targeting and expected participants foundation through branch-scoped teaching-group targets.
- [x] Marks storage/API with total-mark validation and locked-exam protection.
- [x] Keyboard-first marks entry UI with teaching-group targeting and comments.
- [x] CSV marks import with targeted-student validation, row-level errors and teacher-facing upload UI.
- [x] Physical exam attendance register with targeted-student status updates and teacher UI.
- [x] Draft/review/publish/lock workflow with permission checks and correction audit.
- [x] Branch-scoped student risk summary, exam averages and early-warning performance dashboard.
- [x] Group performance summaries with active enrolment counts, published exam averages, low-average learner counts and recorded attendance rates.

## Phase 5 — parent portal and communications

- [x] Hashed, expiring, revocable student-scoped parent links.
- [ ] Responsive Arabic/English performance page.
- [x] Responsive Arabic/English parent student summary page with secure-link expiry/revocation states.
- [ ] WhatsApp consent, templates, queue, retry and delivery log (branch-scoped consent/template/outbox APIs, queued Cloud API worker and permission-gated communications UI are implemented; provider status callbacks and acceptance coverage remain).

## Phase 6 — finance, subscriptions and platform admin

- [ ] Fee plans, invoices, instalments, receipts, refunds and expenses.
- [ ] Plan entitlements, trial lifecycle and Paymob abstraction.
- [x] Super-admin plan catalogue with Egypt-ready Starter, Growth and Academy limits for centres, students, staff and groups.
- [x] Super-admin plan creation from the master screen with pricing, limits and feature lists.
- [x] Persisted 14-day free-trial subscription and expired-subscription portal lock with owner subscription selection screen.
- [x] Encrypted Paymob credential storage and protected platform settings API/screen; checkout activation remains dependent on merchant credentials and subscription payment records.
- [x] Super-admin fixed navigation shell and global organisation, subscription, identity, security and support overview console.
- [ ] Super-admin plan editing, usage metering and operational write actions (financial summaries/reports, audited manual renewal, user identity editing and immediate account deactivation are implemented; automated authorization/audit coverage remains).

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

- 2026-11-06: continued the bilingual route audit for the teacher attendance desk; added matching English/Arabic catalogue entries and explicit `locale.tr(...)` calls for its primary headings. Vue typecheck and Vite production build pass (2,161 modules; 312.62 kB main JS / 105.51 kB gzip). Browser-based RTL visual QA remains pending because this environment exposes no browser automation surface.

- 2026-11-06: added multi-guardian editing to student registration and profiles. API validates guardian contact lists, scopes contact matching to the active organisation and syncs associations only on the server-resolved branch student. Vue typecheck/Vite production build pass (2,161 modules; 315.59 kB main JS / 106.28 kB gzip); PHP syntax check passes. Automated tests were not run in this step.

- 2026-11-06: implemented permission-gated bulk student archival with page selection, server-resolved branch/organisation scoping, a 100-student request limit and one audit event per changed student. Vue typecheck/Vite build and PHP syntax checks pass (2,161 modules; 317.70 kB main JS / 106.94 kB gzip). Automated authorization/isolation tests were not run, so the roadmap item remains unchecked pending that verification.

- 2026-11-06: added private CSV/XLSX student import staging, per-row validation preview, explicit commit, 100-row queue processing with progress, and a formula-safe CSV error report. Migration 2026_11_06_000013 is applied. Vue typecheck/Vite build and PHP syntax checks pass (2,163 modules; 328.71 kB main JS / 109.78 kB gzip). Automated authorization/isolation tests were not run, so the checklist item remains unchecked pending verification.
- 2026-11-06: added migration `000015` for attendance correction history. Scanner and manual status changes now preserve from/to status, actor, method and centre scope; `/api/attendance/sessions/{id}/corrections` returns the history. MySQL feature coverage passes with 27 tests and 168 assertions; Vue/Vite build passes (2,166 modules; 333.46 kB main JS / 111.19 kB gzip).
- 2026-11-06: made scanner duplicate detection transaction-safe and repeated attendance-session closure idempotent. Regression coverage now passes with 27 tests and 170 assertions.
- 2026-11-06: refined the teacher student registration/editing modal so the header remains fixed and scrolling is confined to the form body; production build passes after the UI adjustment.
- 2026-11-06: upgraded shared native dropdown styling across forms, filters, teacher tools and modals with accessible option menus, custom chevrons, focus states and RTL alignment; production build passes (2,166 modules; 333.46 kB main JS / 111.19 kB gzip).
- 2026-11-07: replaced the browser-native option popup with custom in-app dropdown menus across rendered selects; menus now provide selected states, hover/focus styling, keyboard activation, outside-click closing, scroll limits and RTL alignment. Production build passes (2,166 modules; 335.59 kB main JS / 111.79 kB gzip).
- 2026-11-07: added migration `000016`, branch-scoped examination API and a working teacher exams screen at `/teacher/exams`. Teachers can create draft assessments and view existing exams; Vue/Vite build passes (2,168 modules; 340.74 kB main JS / 112.92 kB gzip).
- 2026-11-07: removed the duplicate Quick Access links from the teacher sidebar and verified the frontend build remains passing.
- 2026-11-07: generalized fixed modal headers and isolated scrolling bodies across centre, academic, attendance, exam, import, team, audit and card-print dialogs; production build passes (2,168 modules; 340.78 kB main JS / 112.94 kB gzip).
- 2026-11-07: polished long custom dropdown lists with themed thin scrollbars and hover feedback; production build passes (2,168 modules; 340.78 kB main JS / 112.94 kB gzip).
- 2026-11-07: added visible `:focus-visible` styling to custom dropdown triggers; production build passes.
- 2026-11-07: added `ExaminationTest` coverage for draft creation, subject linkage and branch-scoped listing; the test passes with 8 assertions.
- 2026-11-07: full MySQL suite reverified after the exam workflow: 28 tests and 178 assertions pass.
- 2026-11-07: added migration `000017` for the Free trial plan and persisted organisation subscriptions, subscription enforcement middleware, `/subscribe` owner plan-selection screen and super-admin plan creation. Existing platform-admin tests pass; frontend build passes (2,170 modules; 346.02 kB main JS / 114.16 kB gzip).
- 2026-11-07: added migration `000018` for exam teaching-group targets and marks. Exam APIs now return targets/mark counts and support validated branch-scoped mark entry while preventing changes to locked exams. Examination feature coverage passes with 10 assertions; frontend build passes (2,172 modules; 347.30 kB main JS / 114.54 kB gzip).
- 2026-11-07: exposed the Plans & payments master screen directly in the super-admin sidebar; frontend build passes (2,170 modules; 346.06 kB main JS / 114.18 kB gzip).
- 2026-11-07: polished the plan master screen with a shared platform sidebar and rebuilt the add-plan modal layout; frontend build passes (2,172 modules; 347.22 kB main JS / 114.52 kB gzip).
- 2026-11-07: upgraded the teacher examinations workspace with teaching-group targeting, a branch-scoped marks table, Enter-to-next keyboard navigation and saveable mark/comment rows. The teacher sidebar now appears across all `/teacher/*` routes. MySQL suite passes with 28 tests and 180 assertions; frontend build passes (2,172 modules; 351.10 kB main JS / 115.57 kB gzip).
- 2026-11-07: expanded the secure parent portal with attendance summaries, recent session status, published exam results, percentages and progress cards. Data remains token-, student- and branch-scoped; parent feature coverage now verifies the expanded payload. MySQL suite passes with 28 tests and 188 assertions; frontend build passes (2,172 modules; 354.06 kB main JS / 116.35 kB gzip).
- 2026-11-07: completed exam lifecycle controls with audited publish and lock actions, permission-gated status transitions, locked-mark protection and teacher-facing status buttons. MySQL suite passes with 28 tests and 193 assertions; frontend build passes (2,172 modules; 356.41 kB main JS / 117.10 kB gzip).
- 2026-11-07: added `/api/examinations/performance` and a teacher Performance dashboard with class averages, exam trend bars and attendance/low-mark early warnings. MySQL suite passes with 28 tests and 200 assertions; frontend build passes (2,174 modules; 360.02 kB main JS / 117.93 kB gzip).
- 2026-11-07: added secure CSV marks import at `POST /api/examinations/{id}/marks/import`; imports are branch-scoped, limited to targeted students, reject invalid marks with row-level errors and cannot modify locked exams. MySQL suite passes with 28 tests and 203 assertions.
- 2026-11-07: added a live public pricing section to the landing page with active plan catalogue data, monthly/yearly billing toggle, savings labels, featured-plan treatment, plan limits, feature lists and registration CTAs. Frontend build passes (2,174 modules; 362.66 kB main JS / 118.41 kB gzip); MySQL suite remains green at 28 tests and 203 assertions.
- 2026-11-07: fixed pricing toggle values being reverted by the legacy DOM translation observer; dynamic pricing headings are now excluded from that compatibility pass and remain Vue-reactive. Frontend build passes (2,174 modules; 362.84 kB main JS / 118.53 kB gzip).
- 2026-11-07: upgraded organization audit history to server-side pagination and search with bounded `per_page` values, total/page metadata and preserved event, actor and centre filters. The modal now includes search and previous/next controls; all 28 tests and 203 assertions remain green, and the frontend build passes (2,174 modules; 363.74 kB main JS / 118.83 kB gzip).
- 2026-11-07: upgraded the platform academy directory API with server-side search, bounded pagination and total/page metadata while preserving platform-admin-only access. Added regression coverage; MySQL suite passes with 29 tests and 208 assertions, and frontend build passes (2,174 modules; 364.03 kB main JS / 118.93 kB gzip).
- 2026-11-07: exposed marks CSV import in the teacher exam workspace with file selection, locked-exam protection, imported-count feedback and row-level error display. MySQL suite remains green at 29 tests and 208 assertions; frontend build passes (2,174 modules; 365.86 kB main JS / 119.41 kB gzip).
- 2026-11-07: added migration `000019` and branch-scoped physical exam attendance APIs (`GET/PUT /api/examinations/{id}/attendance`) with targeted-student validation, permission checks and locked-exam protection. MySQL suite passes with 29 tests and 212 assertions; frontend build remains passing.
- 2026-11-07: added the teacher physical exam attendance register at `/teacher/exams/{examId}/attendance` with status buttons, summary cards, locked-exam state and one-click save. Frontend build passes (2,176 modules; 369.62 kB main JS / 120.22 kB gzip).
- 2026-11-07: linked the physical exam attendance register into the exam list workflow; selecting an exam loads marks, and selecting the active exam again opens its attendance register. Frontend build passes (2,176 modules; 369.69 kB main JS / 120.26 kB gzip).
- 2026-11-07: expanded the branch-scoped performance report with subject averages and result counts, and added a Subject trends card to the teacher Performance page. Examination coverage now passes with 30 assertions; frontend build passes (2,176 modules; 370.46 kB main JS / 120.35 kB gzip).
- 2026-11-07: fixed duplicate borders on custom dropdowns by removing inherited toolbar/wrapper chrome from `.modern-select-shell`; the student import modal retains required-field guidance, preview validation and CSV template download. Student feature tests pass and frontend build passes (2,176 modules; 370.46 kB main JS / 120.35 kB gzip).
- 2026-11-07: fixed hidden student import actions by granting the teacher role `import_students` through migration `000020`; added branch-scoped student CSV export with formula-safe cells and exposed both template download and current-student export links in the import modal. Student feature tests pass with 51 assertions; frontend build passes (2,176 modules; 370.64 kB main JS / 120.40 kB gzip).
- 2026-11-07: repaired the teacher Create exam dialog layout. The modal now has a fixed header, an independently scrollable form body, a stable action footer, and explicit sizing/focus styles for title, subject, date, marks and notes fields. ExaminationTest passes (30 assertions); frontend build passes (2,176 modules; 370.64 kB main JS / 120.40 kB gzip).
- 2026-11-07: added branch-scoped guardian WhatsApp consent, utility template registration, consent-gated outbox queueing, retryable Cloud API worker and paginated message history. Migration `000022` is applied; Vue build passes with 2,180 modules. Provider delivery callbacks and focused authorization/isolation coverage remain.
