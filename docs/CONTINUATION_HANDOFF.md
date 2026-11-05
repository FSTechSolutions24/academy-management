# AcademyHub continuation handoff

Last updated: 2026-11-06 (Africa/Cairo)

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
- Teacher workspace with student directory, registration, editing and archival.
- Teaching groups, enrolments, recurring schedules and overlap validation.
- Attendance sessions, scanner records, duplicate-safe scans, closure/absence finalisation and manual bulk marking.
- Parent access links with hashed, expiring and revocable tokens plus a bilingual parent portal.
- Platform-admin console with tenant, identity, security, support and plan settings sections.
- Starter, Growth and Academy subscription plans with centre/student/staff/group limits.
- Encrypted Paymob settings and checkout/callback foundation; real checkout still needs merchant credentials and production verification.
- English/Arabic locale files at `resources/lang/en.json` and `resources/lang/ar.json`, persistent language toggle, live DOM translation and RTL layout support.

## Current verification baseline

- MySQL backend suite: 27 tests, 162 assertions passing.
- Vue typecheck and Vite production build: passing, 2,161 modules.
- Latest frontend output: approximately 310.96 kB main JS, 105.08 kB gzip.
- Composer audit: no known advisories at the last check.
- Browser screenshot QA is still pending because the previous environment had no browser surface.

## Remaining work, in priority order

1. Finish the translation audit page by page. Replace remaining hard-coded template strings with `locale.tr(...)` where practical, and add every user-facing phrase to both JSON files. Keep API values, names and codes untouched. Verify Arabic text, placeholders, modals and RTL spacing in a real browser.
2. Complete teacher operations: richer guardian editing, student bulk actions, CSV/XLSX staged import, validation/error workbook, and revocable Code 128/QR student cards with A4 PDF output.
3. Complete attendance production workflow: idempotency/correction history, reports, background closure jobs and an authorised-device offline queue.
4. Build examinations and performance: exam targeting, keyboard-first marks entry/import, draft/review/publish/lock states, analytics and early warnings.
5. Finish parent communications: performance detail page, WhatsApp consent/templates/queue/retry/delivery log.
6. Build finance and subscription operations: fees, invoices, instalments, receipts, refunds, expenses, usage metering, entitlement enforcement and super-admin lifecycle actions.
7. Add exports/reports, Horizon/Redis deployment guidance, backups, monitoring, accessibility/performance testing and the final security/acceptance gates.

## Important implementation notes

- Do not reintroduce SQLite or bypass organisation/centre authorization in controllers.
- Every new centre-scoped query must use the server-resolved branch context, not a trusted client branch ID.
- Keep platform-admin routes separate from teacher/academy routes.
- The global DOM translator in `resources/js/App.vue` is a compatibility layer for legacy hard-coded text. New UI should use `useLocaleStore().tr(...)` directly.
- The locale store intentionally restores original English text when switching back from Arabic. Do not mutate API data just to translate it.
- Update `CURRENT_STATUS.md`, `IMPLEMENTATION_PLAN.md` and this handoff file after each meaningful milestone, including tests/build results.

## Recommended first continuation task

Run the application, switch to Arabic, and inspect every route as both super admin and teacher. Record untranslated strings and RTL defects in a short checklist, then close the translation gap before starting the next business module.
