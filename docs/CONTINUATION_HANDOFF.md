# AcademyHub continuation handoff

Last updated: 2026-11-07 (Africa/Cairo)

This file is the quick-start context for continuing development on another device. The canonical product specification remains [`MASTER_PRODUCT_PROMPT.md`](MASTER_PRODUCT_PROMPT.md); the implementation checklist is [`IMPLEMENTATION_PLAN.md`](IMPLEMENTATION_PLAN.md); the verified state is [`CURRENT_STATUS.md`](CURRENT_STATUS.md).

## What this project is

AcademyHub is a Laravel 12 + Vue 3 + TypeScript SaaS for Egyptian teaching academies. One organisation can own multiple independent centres. A centre is an operational tenant: its academic years, levels, subjects, students, attendance, groups, schedules, exams and future finance data must remain isolated from other centres.

## Runtime and setup

- Windows/XAMPP development environment.
- PHP 8.2, Laravel 12, Node 22, MariaDB/MySQL.
- Development database: `academy_management` on `127.0.0.1:3306`.
- Test database: `academy_management_test` on the same MySQL server.
- phpMyAdmin can be used to inspect both databases.
- SQLite is intentionally not used.
- Latest migrations are applied through `2026_11_07_000020_enable_teacher_student_imports`; this migration adds teacher access to student imports.
- Copy the ignored `.env` from the development machine only through a secure channel. It contains local database credentials and the seeded demo password; never commit it.

After copying the repository, run:

```powershell
composer install
npm install
php artisan migrate
npm run build
php artisan test
```

If the database is empty, use the project’s existing seeders rather than manually creating records. Check `.env.example` for required variable names and `CURRENT_STATUS.md` for the latest migration/test baseline.

## Completed product areas

- Public landing, registration, login, email verification and password recovery.
- Organisation and multi-centre tenancy with server-side context validation.
- Owner/admin/teacher/assistant/reception/finance roles, custom permissions and centre assignments.
- Invitation acceptance, permission exceptions and audit history.
- Teacher workspace with a branch-scoped student directory, registration/editing, guardian contact management for up to five linked guardians, primary-contact selection and archival.
- Teaching groups, enrolments, recurring schedules and overlap validation.
- Attendance sessions, scanner records, duplicate-safe scans, closure/absence finalisation, manual bulk marking and branch-scoped correction history.
- Teacher examination workflow at `/teacher/exams`, including branch-scoped draft creation/listing, teaching-group targets, keyboard-first marks entry, CSV marks import, audited publish/lock lifecycle and subject/early-warning analytics.
- Physical exam attendance register at `/teacher/exams/{examId}/attendance` with targeted students, status summary and locked-exam protection.
- Parent access links with hashed, expiring and revocable tokens plus a bilingual parent portal.
- Platform-admin console with tenant, identity, security, support and plan settings sections.
- Starter, Growth and Academy subscription plans with centre/student/staff/group limits.
- Super-admin plan creation from `/admin/settings/plans`, persisted 14-day Free trial subscriptions, and `/subscribe` owner plan selection with portal lock after expiry.
- Encrypted Paymob settings and checkout/callback foundation; real checkout still needs merchant credentials and production verification.
- English/Arabic locale files at `resources/lang/en.json` and `resources/lang/ar.json` with matching keys, persistent language toggle, explicit attendance desk translations, live DOM translation and RTL layout support.
- Student operations now include staged CSV/XLSX import with preview/progress/error reporting, teacher-visible import permission, downloadable template and branch-scoped formula-safe CSV export.

## Current verification baseline

- MySQL backend suite: 29 tests, 212 assertions passing.
- Subscription enforcement allows legacy workspaces without subscription rows to continue functioning; newly registered academies always receive a persisted trial row.
- Vue typecheck and Vite production build: passing, 2,176 modules.
- Latest frontend output: 370.64 kB main JS, 120.40 kB gzip.
- Composer audit: no known advisories at the last check.
- Browser screenshot QA is still pending because the previous environment had no browser surface.

## Remaining work, in priority order

1. Finish the translation audit page by page. Replace remaining hard-coded template strings with `locale.tr(...)` where practical, and add every user-facing phrase to both JSON files. Keep API values, names and codes untouched. Verify Arabic text, placeholders, modals and RTL spacing in a real browser.
2. Complete teacher operations: staged CSV/XLSX import is implemented with preview, queued progress and formula-safe error CSV; bulk archival, imports and student cards still need authorization/isolation tests. Student cards now support reissue/revocation, hashed tokens, Code 128 A4 print/save-as-PDF and attendance scan lookup.
3. Complete attendance production workflow: correction permissions, richer reports, background closure jobs and an authorised-device offline queue. Scanner duplicate handling and repeated closure are now idempotent; correction history is implemented.
4. Extend examinations and performance: deeper group trends and UI refinements. Teaching-group targeting, keyboard-first marks entry, audited draft/publish/lock controls, secure CSV import, physical exam attendance and the early-warning/subject dashboard are implemented in `/teacher/exams` and `/teacher/performance`.
5. Finish parent communications: performance detail is now available in the secure parent portal with attendance and published exam summaries; next add WhatsApp consent/templates/queue/retry/delivery log.
6. Build finance and subscription operations: fees, invoices, instalments, receipts, refunds, expenses, usage metering, entitlement enforcement and super-admin lifecycle actions. Basic trial/subscription enforcement and plan selection are now implemented; Paymob credentials and callback verification still need production testing.
7. Add exports/reports, Horizon/Redis deployment guidance, backups, monitoring, accessibility/performance testing and the final security/acceptance gates.

## Important implementation notes

- Do not reintroduce SQLite or bypass organisation/centre authorization in controllers.
- Every new centre-scoped query must use the server-resolved branch context, not a trusted client branch ID.
- Keep platform-admin routes separate from teacher/academy routes.
- The global DOM translator in `resources/js/App.vue` is a compatibility layer for legacy hard-coded text. New UI should use `useLocaleStore().tr(...)` directly.
- The locale store intentionally restores original English text when switching back from Arabic. Do not mutate API data just to translate it.
- Update `CURRENT_STATUS.md`, `IMPLEMENTATION_PLAN.md` and this handoff file after each meaningful milestone, including tests/build results.

## Recommended first continuation task

Continue by linking the platform academy pagination controls visibly in the admin UI, then add deeper group analytics and WhatsApp parent communications. Automated authorization/isolation coverage is still needed for student imports, bulk archival and cards. Browser-based Arabic/English visual QA is still pending because no browser automation surface is available here.
