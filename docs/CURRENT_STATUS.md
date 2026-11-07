# Current development status

Last updated: 2026-11-07 (Africa/Cairo)

For moving the project to another device, start with [`CONTINUATION_HANDOFF.md`](CONTINUATION_HANDOFF.md). It contains the runtime setup, completed scope, verification baseline and prioritized remaining work.

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
- Persistent browser-level English/Arabic locale switch with live document `lang`/`dir` updates and mirrored fixed-sidebar, mobile drawer, forms, modals and dashboard layout rules.
- Arabic catalogue is wired into login, registration, the academy workspace and the platform-admin navigation/control-plane shell; Arabic mode prefers Arabic tenant and academic names with safe fallbacks.
- The email-verification journey is fully translated and responds live to the English/Arabic switch.
- The forgot-password journey, including its privacy-preserving success message, is fully translated into Arabic.
- Idempotent local demo-account seeder with one platform-super-admin identity and one centre-scoped teacher identity. The shared local password is read only from the ignored `.env` file.
- Dedicated fixed platform-admin shell: global command centre, academy directory, subscription lifecycle, user/access directory, security/audit, support and platform settings. Super admins are redirected away from teaching and centre menus.
- Dedicated responsive teacher workspace with role-based redirect and working navigation for teaching overview, assigned students, attendance, weekly schedule, exams/marks and performance. Teacher navigation excludes finance, staff administration, academy settings and platform operations.
- Teacher sidebar navigation has been simplified by removing the duplicate Quick Access section; primary module links remain the single source of navigation.
- Teacher page foundations include useful empty states, quick actions, student filters, attendance scanner launch, weekly schedule and performance placeholders ready for the Phase 2/3 data modules.
- Working branch-scoped student directory for teachers with search, level filters, server-backed pagination and permission-gated bulk archival. Bulk archive is branch-scoped and creates one audit event per archived student; authorization tests remain pending. Student profiles support up to five guardian contacts, relationship labels, primary-contact selection and removal from the student link.
- Teacher-authorized student editing and archival now update active enrolments safely and write auditable events.
- CSV/XLSX student imports support private staging, row-level previews, 100-row queued processing, progress polling, formula-safe CSV error reporting and teacher-visible import permission. The student modal includes required-field guidance, a downloadable template and branch-scoped current-student CSV export. Automated authorization and tenant-isolation tests remain pending.
- Branch-scoped teaching groups now have academic links, unique centre codes, capacity metadata, active enrolment counts and audited owner/admin creation/listing APIs.
- Teaching groups now support recurring branch schedules with day/time/mode/room data, overlap conflict validation and teacher-readable schedule APIs.
- Super-admin commercial settings now include Starter, Growth and Academy plans with centre/student/staff/group limits, editable pricing/features, and encrypted Paymob credential configuration at `/admin/settings/plans`.
- Super-admins can now add new subscription plans from the plan master screen, including pricing, limits and feature lists.
- The super-admin sidebar now exposes a direct Plans & payments link to `/admin/settings/plans`.
- The plan master screen now uses the full platform-admin shell/sidebar, and its add-plan dialog has a responsive two-column form with grouped limits, fixed modal framing and a clear action footer.
- New academies receive a persisted 14-day Free trial subscription. Once a trial/subscription expires, organisation APIs are locked with HTTP 402 and owners are redirected to `/subscribe` to select a paid plan.
- Paymob subscription flow now has payment records, checkout-intent/iframe creation, callback HMAC verification and paid-plan activation endpoints; merchant credentials are still required before checkout can be used.
- Attendance foundation now supports branch/group sessions, permission-checked scanner records, duplicate-safe scans, automatic absence finalisation on close and session reports through `/api/attendance/sessions`.
- Manual bulk attendance marking is available at `PUT /api/attendance/sessions/{id}/records` for present, late, excused and absent statuses.
- Attendance corrections are now recorded whenever an existing status changes through scanner or manual marking, with a branch-scoped history endpoint at `GET /api/attendance/sessions/{id}/corrections`.
- Teacher examinations are now usable at `/teacher/exams`: teachers can load centre subjects, create draft exams with date, mark totals and notes, and see the branch-scoped exam list. The API is protected by `create_examinations` and `view_reports` permissions.
- Exams now support branch-safe teaching-group targets and an entering-marks API (`GET/PUT /api/examinations/{id}/marks`) with total-mark validation and locked-exam protection. Migration `2026_11_07_000018_add_exam_targets_and_marks` is applied.
- Added a regression test for teacher draft-exam creation and branch-scoped listing; it passes with 8 assertions.
- Secure parent access links now use random one-time-displayed tokens stored only as hashes, with expiry, revocation and a privacy-limited student view.
- Public bilingual parent portal is now available at `/parent/{token}` with a polished responsive summary card, expiry/revocation state and live RTL switching.
- Teacher attendance desk is now connected at `/teacher/attendance` with session creation, group selection, scanner input, duplicate feedback and close-session controls.
- Student registration/editing modal now keeps its header content fixed while only the form body scrolls, including a compact mobile layout.
- All modal families now use the fixed-header/scrolling-body pattern: centre forms, academic setup, attendance, exams, student imports, team management, audit logs and card printing.
- Native dropdowns across modals, filters, teacher tools and admin forms now share modern product styling with custom chevrons, hover/focus states, selected options and RTL-aware alignment.
- Dropdown menus now use custom in-app option surfaces instead of the browser-native popup, with selected checkmarks, hover states, keyboard activation, click-outside closing, scrollable long lists and RTL support.
- Custom dropdown menus now include a compact themed scrollbar with hover feedback for long option lists.
- Custom dropdown triggers now show a clear keyboard focus ring for accessible navigation.
- Student card lifecycle now supports branch-scoped bulk issue/reissue/revoke, SHA-256 token storage, one-time raw-token delivery for Code 128 printing, A4 print/save-as-PDF, audit events and attendance scanning by active card token or legacy student code. Migration `2026_11_06_000014_create_student_cards_table` is applied; automated authorization/isolation coverage remains pending.
- Localization now uses a clean English/Arabic catalogue, a persistent globe-style switch and a live DOM pass for hard-coded labels, buttons, placeholders and titles; document-level RTL mirrors sidebars, forms, tables, modals and parent views.
- Translation catalogues are maintained explicitly in [`resources/lang/en.json`](../resources/lang/en.json) and [`resources/lang/ar.json`]. They now contain 258 matching English/Arabic keys, including the teacher attendance desk. The locale store translates embedded phrases and common fallback words for legacy UI.
- Platform overview API is protected independently of tenant membership; ordinary academy users receive a forbidden response.
- Complete product prompt, architecture, database design, security rules and phased plan are tracked in `docs/`.

## Verification baseline

- Runtime database: XAMPP MariaDB/MySQL at `127.0.0.1:3306`, database `academy_management`, accessible in phpMyAdmin.
- Automated test database: separate MySQL database `academy_management_test`; SQLite is not used.
- Database migrations: all applied through `2026_11_07_000020_enable_teacher_student_imports`.
- Backend: 29 tests, 212 assertions passing on MySQL.
- Latest full verification: all 29 tests pass with 212 assertions; frontend build passes with 2,176 modules (370.64 kB main JS / 120.40 kB gzip).
- Frontend: Vue TypeScript check and Vite production build passing (2,176 modules; latest build 369.62 kB main JS / 120.22 kB gzip).
- Composer audit: no known advisories at the last audit.
- Visual screenshot QA: pending because the current automation environment exposed no browser surface.

## Next safe implementation step

Continue Phase 4 examination workflow with deeper group trends. Subject averages are now available in the performance API and teacher dashboard; physical attendance is linked from the exam list by selecting the active exam again. Student import/export is now visible to teachers with a template download and formula-safe CSV export. CSV marks import is exposed in the teacher UI alongside draft/publish/lock controls, marks entry, class averages and early-warning signals. The public landing page now loads active subscription plans with monthly/yearly pricing. Audit history and the platform academy directory now use bounded server-side pagination/search to remain safe for large organizations. Automated authorization/isolation coverage remains pending for bulk archival, staged imports and student cards; browser-based RTL visual QA is also still open.

Start every future session by reading `CURRENT_STATUS.md`, `IMPLEMENTATION_PLAN.md`, `SECURITY.md` and the relevant part of `MASTER_PRODUCT_PROMPT.md`.
