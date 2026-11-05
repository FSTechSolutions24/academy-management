# Current development status

Last updated: 2026-11-06 (Africa/Cairo)

## Completed and verified

- Modern responsive public landing page, registration, login, email-verification, password-recovery and authenticated dashboard screens.
- Signed, expiring email-verification links; unverified owners remain authenticated only long enough to verify or sign out and cannot enter tenant routes.
- Non-enumerating password-reset requests with expiring, single-use broker tokens and strong-password validation.
- Transactional owner registration with one organisation, a 14-day trial and a head-office centre.
- Organisation membership boundary plus operational centre boundary.
- Owners can create centres and switch the active centre from the sidebar.
- Staff can see only centres explicitly assigned through `branch_user`; unassigned centre selection is denied.
- Every centre has independent academic years, educational levels and subjects.
- New centres start empty; student, attendance, examination and financial transactions are never copied.
- Centre-specific academic structure management modal in the dashboard.
- System roles for owners, administrators, teachers, assistants, receptionists and finance officers.
- Custom organisation roles built from granular permission sets.
- Staff invitation and direct-add workflow with explicit one-or-more-centre assignments.
- Hashed, single-use, seven-day invitation links and a responsive acceptance screen.
- Staff role/status/centre administration and pending-invitation revocation.
- Per-member permission exceptions with explicit allow/deny states; deny overrides the assigned role while owners remain protected from self-lockout changes.
- Organisation-scoped, read-only audit viewer with event, actor and centre filters plus expandable before/after metadata. Teachers cannot access it.
- Audit events for centre creation/switching, academic setup, staff invitations, staff access changes and custom roles.
- Production SMTP, sender-domain, DNS and queue-worker setup is documented in `PRODUCTION_EMAIL.md`; local development intentionally remains on the log mailer.
- Idempotent local demo-account seeder with one platform-super-admin identity and one centre-scoped teacher identity. The shared local password is read only from the ignored `.env` file.
- Dedicated fixed platform-admin shell: global command centre, academy directory, subscription lifecycle, user/access directory, security/audit, support and platform settings. Super admins are redirected away from teaching and centre menus.
- Platform overview API is protected independently of tenant membership; ordinary academy users receive a forbidden response.
- Complete product prompt, architecture, database design, security rules and phased plan are tracked in `docs/`.

## Verification baseline

- Runtime database: XAMPP MariaDB/MySQL at `127.0.0.1:3306`, database `academy_management`, accessible in phpMyAdmin.
- Automated test database: separate MySQL database `academy_management_test`; SQLite is not used.
- Database migrations: all applied through `2026_11_06_000005_add_platform_admin_to_users`.
- Backend: 19 tests, 111 assertions passing on MySQL.
- Frontend: Vue TypeScript check and Vite production build passing.
- Composer audit: no known advisories at the last audit.
- Visual screenshot QA: pending because the current automation environment exposed no browser surface.

## Next safe implementation step

Finish localisation before student records:

1. Full Arabic catalogue and live RTL/LTR switch.
2. Then begin the branch-scoped student/guardian/enrolment vertical slice.

Start every future session by reading `CURRENT_STATUS.md`, `IMPLEMENTATION_PLAN.md`, `SECURITY.md` and the relevant part of `MASTER_PRODUCT_PROMPT.md`.
