# MASTER CODEX PROMPT
# Build a Production-Ready Academy & Training Centre Management SaaS

## 1. Project Overview

You are a senior software architect, Laravel engineer, database architect, security engineer, and UI/UX designer.

Your task is to design and build a complete, production-ready, multi-tenant SaaS application for private teachers, tutoring centres, educational academies, and training centres, with the Egyptian education market as the primary target.

**Working project name:** AcademyHub (temporary; all branding must be configurable).

This must be a real, functional commercial application, not a prototype, UI demonstration, or collection of mock pages.

The platform will allow teachers and educational centres to subscribe, manage thousands of students, organise academic years and teaching groups, track physical attendance through barcode scanning, record examination results, monitor academic performance, collect fees, communicate with parents, and provide parents with private student-performance pages.

The platform must support Arabic and English throughout, including proper right-to-left (RTL) and left-to-right (LTR) layouts.

### Core business model

- Teachers or centres subscribe to the SaaS platform.
- Every subscribed organisation receives an isolated workspace.
- Each organisation can manage its teachers, assistants, branches, students, academic years, groups, examinations, attendance, fees, and reports.
- Parents can access student-specific performance information through secure, private, read-only links without registering.
- The SaaS owner has a separate super-admin portal for managing organisations, subscriptions, plans, and system operations.

### Primary target market

Egyptian private tutoring centres, especially those serving:

- Primary school students.
- Preparatory school students.
- Secondary school students, including Thanaweya Amma.
- Language and educational academies.
- Training centres and vocational education providers.

Design for organisations with 1,000â€“50,000 students, with a practical initial capacity target of at least 10,000 active students per organisation.

---

## 2. Technology Stack

Use a modern, maintainable, secure Laravel-based architecture.

### Backend

- Laravel, using the latest stable version compatible with the environment.
- PHP, using the supported version recommended for the selected Laravel release.
- MySQL 8 or a compatible supported database.
- Redis for caching, queues, rate limiting, and temporary operational state.
- Laravel Horizon for queue monitoring where appropriate.
- Laravel Scheduler for recurring background jobs.
- Laravel Policies, Gates, and middleware for authorisation.
- Laravel Notifications and Events.
- Laravel Sanctum for authenticated API access if required.
- Server-side validation using Laravel Form Requests.
- Database migrations, seeders, factories, and automated tests.

### Frontend

Preferred:

- Vue 3.
- Inertia.js.
- Tailwind CSS.
- A consistent, accessible component system.
- Vite.
- TypeScript where practical.
- Responsive desktop, tablet, and mobile layouts.

Use a consistent application shell with reusable layouts, form components, data tables, filters, dialogs, charts, and notification components.

Do not unnecessarily introduce a separate backend API for functionality that can be handled cleanly through Laravel and Inertia.

### Other technologies

- Barcode and QR-code generation libraries.
- Browser-based barcode scanning support.
- Optional camera-based QR scanning.
- Server-side PDF generation.
- Excel and CSV import/export.
- Object storage compatible with S3.
- WhatsApp Business Platform / Cloud API integration.
- Paymob or another suitable Egyptian payment provider, using a configurable payment abstraction.
- Secure encryption for stored integration credentials.

### Architecture requirements

Use a modular monolith rather than microservices.

Organise the code by business domain, for example:

- Tenancy
- Identity and Access
- Subscriptions
- Academic Structure
- Students
- Groups
- Attendance
- Examinations
- Performance
- Parent Portal
- Finance
- Communications
- Reports
- Administration

Use service classes, actions, jobs, policies, events, and repositories where they genuinely improve maintainability.

Avoid putting complex business logic directly into controllers or Vue components.

---

## 3. Multi-Tenant SaaS Architecture

This is one of the most important requirements.

### Organisation accounts

A teacher or centre owner can register and create an organisation.

Each organisation must have:

- Unique ID.
- Name in Arabic.
- Name in English, optionally.
- Logo.
- Contact phone.
- WhatsApp phone.
- Email.
- Address.
- Governorate.
- City or district.
- Preferred language.
- Default currency, initially EGP.
- Time zone, initially Africa/Cairo.
- Subscription plan.
- Subscription status.
- Registration date.
- Optional custom domain or subdomain.
- Organisation settings.

### Data isolation

Every tenant-owned business record must belong to an organisation.

Enforce tenant isolation at the database-query, authorisation, background-job, import/export, and file-access levels.

A user from one organisation must never access another organisation's students, grades, payments, or private files.

Use shared-database multi-tenancy with a clear `tenant_id` or `organization_id` strategy, unless there is a documented reason to select another approach.

All tenant-specific queries must be properly scoped.

### Multi-branch support

An organisation can have multiple physical branches.

Each branch has:

- Name.
- Address.
- Governorate and city.
- Contact details.
- Assigned teachers.
- Assigned assistants.
- Classrooms.
- Groups.
- Attendance locations.
- Optional operating hours.

Students may attend groups in different branches.

### Centre-as-workspace isolation

Every branch or centre must behave as a separate operational tenant inside the parent organisation.

The organisation owner keeps one commercial account and subscription, but can create and switch between multiple centres. Each centre has its own isolated operational data, including:

- Academic years and active academic year.
- Educational levels and grades.
- Subjects and teachers.
- Students, guardians, enrolments and student codes.
- Groups, classrooms and schedules.
- Attendance sessions and scan records.
- Examinations, marks and performance analytics.
- Fees, invoices, payments, expenses and financial reports.
- Parent links, files, exports, messages and audit history.

Centre isolation must be enforced in database queries, validation, authorisation, background jobs, imports, exports, private file paths, cache keys and reports. Selecting or submitting a different centre ID must never grant access to that centre.

Organisation owners can access all centres in their organisation and switch the active centre from the application shell. Staff members can access only the centres explicitly assigned to them. Centre managers may administer their assigned centre without gaining access to other centres.

Records from different centres must not be mixed in lists, totals, dashboards, searches or reports. When a student legitimately studies at more than one centre, use explicit cross-centre enrolment or transfer workflows; do not weaken the centre boundary or silently duplicate records.

Creating a new centre starts an independent academic workspace. The owner may optionally copy selected configuration such as levels, subjects, roles or fee templates from an existing centre, but student, attendance, examination and financial transactions must never be copied automatically.

### Multi-teacher support

One organisation can contain multiple teachers.

Each teacher can be assigned subjects, groups, academic years, and branches.

Students may study with multiple teachers.

Do not assume one student belongs to only one teacher, year, subject, or group.

---

## 4. Authentication, Roles, and Permissions

Implement secure authentication and role-based access control.

### Platform roles

**Super Administrator**

Can manage the entire SaaS platform, organisations, subscriptions, plans, support operations, and platform configuration.

**Organisation Owner**

Can manage the organisation, subscription, billing, branches, teachers, assistants, settings, and all permitted educational operations.

**Organisation Administrator**

Can manage operational modules according to permissions.

**Teacher**

Can manage assigned students, groups, examinations, grades, attendance, and reports.

**Assistant**

Can perform explicitly authorised operational tasks such as scanning attendance, registering students, entering marks, and recording payments.

**Receptionist**

Can register students, manage groups, scan attendance, and collect fees, subject to permissions.

**Finance Officer**

Can manage student fees, payments, expenses, and financial reports.

### Permission requirements

Implement granular permissions, including:

- View students.
- Create students.
- Edit students.
- Archive students.
- Import students.
- View attendance.
- Record attendance.
- Correct attendance.
- Create examinations.
- Enter examination marks.
- Edit published marks.
- Publish examination results.
- View financial information.
- Record payments.
- Refund payments.
- Send WhatsApp messages.
- Manage parent links.
- Manage staff.
- Manage organisation settings.

Allow organisation owners to create custom roles.

A teacher or assistant must only access branches, groups, subjects, and students within their authorised scope.

Maintain audit logs for sensitive actions.

---

## 5. Academic Years and Academic Structure

The teacher must be able to configure academic years and educational levels.

### Academic years master table

Provide an Academic Years management screen.

Examples:

- 2026/2027
- 2027/2028

Each academic year has:

- Unique ID.
- Display name.
- Start date.
- End date.
- Status: Upcoming, Active, Closed, Archived.
- Optional description.

Allow an organisation to configure its active academic year.

### Educational levels

Create a separate configurable master table for educational levels and grades.

Egyptian defaults should include:

**Primary**

- Primary 1â€“6.

**Preparatory**

- Preparatory 1â€“3.

**Secondary**

- Secondary 1â€“3.

Also support custom levels for language academies and training centres.

### Subjects

Create a subject master table.

Examples:

- Arabic.
- English.
- Mathematics.
- Physics.
- Chemistry.
- Biology.
- History.
- Geography.

Each subject can have Arabic and English names.

### Student enrolment

A student must have a permanent student record.

The student can then be enrolled into an academic year, grade, subject, and one or more teaching groups.

Do not duplicate the student's master record when moving them into a new academic year.

Maintain historical enrolments and results.

---

## 6. Student Management

Build a comprehensive student management module.

### Student information

Each student should have:

- Internal database ID.
- Unique public student identifier.
- Student code.
- Full name in Arabic.
- Full name in English, optional.
- Profile photo, optional.
- Date of birth, optional.
- Gender, optional.
- School name.
- Governorate.
- City or district.
- Student mobile number, optional.
- Parent/guardian name.
- Parent/guardian phone number.
- Additional guardian contact, optional.
- Emergency contact.
- Registration date.
- Status: Active, Suspended, Graduated, Archived.
- Internal notes.
- Academic enrolments.
- Assigned groups.
- Custom fields where configured.

### Unique student code

Every student must receive an automatically generated, unique, immutable identifier within the organisation.

Example:

`STU-00001284`

The identifier must never be reused.

Use database constraints and concurrency-safe generation.

Do not use the database primary key directly as a secret or authorisation mechanism.

### Barcode and QR code generation

Provide a button on the student profile:

**Generate Student Card**

The system must:

1. Generate a barcode-compatible card identifier.
2. Generate a printable barcode or QR code.
3. Display the student's name, code, photo if available, organisation name, and academic information.
4. Allow printing one student card.
5. Allow bulk generation of student cards.
6. Support PDF printing with multiple cards per A4 sheet.
7. Support configurable card dimensions.
8. Support reprinting and replacement of lost cards.

Support Code 128 barcodes and QR codes.

The barcode must represent an opaque card token or identifier, not sensitive student information.

Maintain a card lifecycle:

- Active.
- Replaced.
- Revoked.
- Expired.

When a card is replaced, the old card must stop working.

A student may have a permanent student code while their card token changes.

### Student list

Provide a fast, server-side paginated student table.

Features:

- Search by student name.
- Search by code.
- Search by parent phone.
- Filter by academic year.
- Filter by grade.
- Filter by subject.
- Filter by group.
- Filter by branch.
- Filter by status.
- Bulk selection.
- Bulk group assignment.
- Bulk card printing.
- Bulk export.
- Bulk archive.

### Bulk import

Provide Excel and CSV imports.

Requirements:

- Downloadable import template.
- Column mapping.
- Arabic and English text support.
- Duplicate detection.
- Preview before import.
- Validation report.
- Partial error handling.
- Background processing.
- Import progress indicator.
- Downloadable error report.

Support imports containing thousands of records without request timeouts.

Detect likely duplicates using configurable combinations of student name, guardian phone, and existing student code.

Do not automatically merge uncertain matches.

---

## 7. Teaching Groups and Scheduling

Egyptian centres frequently divide students into groups based on days and times.

Create a full teaching-group management module.

Each group contains:

- Group name.
- Academic year.
- Grade.
- Subject.
- Teacher.
- Branch.
- Classroom, optional.
- Maximum capacity.
- Schedule.
- Start date.
- End date.
- Status.
- Enrolled students.

Example:

Group A â€” Secondary 3 â€” Physics

Sunday and Wednesday at 5:00 PM.

### Group features

- Enrol students.
- Transfer students between groups.
- Track group capacity.
- Display available seats.
- Prevent or warn about timetable conflicts.
- Record group history.
- View group attendance.
- View group examination performance.
- View group revenue.
- Print group student lists.

### Session scheduling

A group can have recurring sessions.

Support:

- Weekly recurring sessions.
- One-off sessions.
- Cancelled sessions.
- Rescheduled sessions.
- Makeup sessions.
- Public holidays.
- Session notes.

Each scheduled session should have a unique record so attendance can be linked to the correct session.

---

## 8. Barcode Attendance System

This is a critical operational module.

The centre assistant must be able to scan student cards quickly at the entrance.

### Attendance scanning screen

Create a dedicated full-screen attendance interface optimised for reception desks.

It must support USB barcode scanners operating as keyboard input.

Requirements:

- Large scanning input.
- Automatically focused scanning field.
- Support rapid consecutive scans.
- Process scans without page reloads.
- Display the scanned student's photo and name.
- Display student code.
- Display assigned group.
- Display scan result.
- Play optional success/error sounds.
- Show a clear success or warning state.
- Automatically reset the input for the next scan.

### Attendance workflow

1. Assistant selects the branch.
2. Assistant selects the session or group.
3. Assistant starts scanning.
4. Student presents their card.
5. Scanner reads the barcode.
6. System validates the card.
7. System verifies enrolment and session eligibility.
8. Attendance is recorded.
9. Assistant receives immediate feedback.

### Attendance statuses

- Present.
- Late.
- Absent.
- Excused.
- Manually Recorded.

### Duplicate scan handling

If the student has already been marked present for that session, do not create another attendance record.

Display:

"Student already checked in at 05:03 PM."

Use database-level uniqueness and concurrency-safe processing to prevent duplicate attendance during simultaneous scans.

### Additional attendance functionality

- Manual attendance entry.
- Search by student code if card is forgotten.
- Attendance correction with reason.
- Late arrival thresholds.
- Attendance history.
- Attendance by session.
- Attendance by group.
- Attendance by branch.
- Attendance percentage.
- Absence streaks.
- Daily attendance summary.
- Export attendance reports.

### Automatic absence marking

Once a session closes, determine absences for expected enrolled students who were not marked present, late, or excused.

Do not prematurely mark students absent before the session ends.

### Offline scanning

Provide an optional, carefully designed offline-capable scanning mode for unstable Egyptian internet connections.

Offline scans must be stored locally only on authorised devices, synchronised when connectivity returns, and deduplicated safely.

Clearly display whether a scan is pending or confirmed by the server.

Do not treat unsynchronised attendance as successfully recorded.

---

## 9. Examination Management

Examinations are physical, not online.

Students will attend examinations at the centre, and staff will enter their marks afterward.

Do not build an online examination-taking interface.

### Examination creation

A teacher or authorised assistant can create an examination.

Fields:

- Examination title.
- Academic year.
- Grade.
- Subject.
- Teacher.
- Branch, optional.
- Examination date.
- Start time.
- End time.
- Maximum mark.
- Passing mark.
- Examination type.
- Description.
- Status.

Examination types:

- Weekly Quiz.
- Monthly Exam.
- Midterm.
- Final Exam.
- Revision Test.
- Homework Assessment.
- Custom.

### Examination assignment

Allow the examination to be assigned to:

- One group.
- Multiple groups.
- An entire grade.
- Selected students.

Keep a record of which students are expected to participate.

### Examination attendance

Track physical examination attendance separately from normal session attendance.

Statuses:

- Attended.
- Absent.
- Excused.
- Not Yet Recorded.

Allow barcode scanning for examination attendance as well.

### Marks entry

After the examination is completed and physically marked, authorised staff must be able to enter results.

Create a fast marks-entry screen.

Columns:

- Student code.
- Student name.
- Group.
- Attendance status.
- Maximum mark.
- Obtained mark.
- Percentage.
- Grade.
- Notes.

Requirements:

- Inline editing.
- Keyboard-friendly navigation.
- Bulk save.
- Autosave or explicit save with clear status.
- Validation against maximum mark.
- Support decimal marks.
- Bulk Excel import.
- Mark-entry progress.
- Draft and published result states.
- Ability to enter marks only for eligible participants.
- Prevent accidental overwriting.
- Audit history for corrections.

An absent student must not automatically receive a zero mark unless the organisation explicitly chooses that policy.

### Result publication

Implement a result workflow:

Draft â†’ Ready for Review â†’ Published â†’ Locked.

Only published results appear in the parent portal.

Published-result corrections must require permission and create an audit record.

Optionally allow the organisation to notify parents when results are published.

---

## 10. Student Performance Analytics

Provide detailed performance monitoring.

### Student performance dashboard

Display:

- Student profile.
- Current academic year.
- Enrolled subjects.
- Assigned groups.
- Total examinations.
- Examinations attended.
- Examinations missed.
- Average percentage.
- Highest percentage.
- Lowest percentage.
- Latest examination result.
- Attendance percentage.
- Performance trend.
- Subject-level performance.
- Teacher comments.

### Performance charts

Include:

- Marks over time.
- Percentage trend.
- Attendance trend.
- Performance by subject.
- Comparison against group average.
- Comparison against grade average.

When comparing different examinations, normalise marks to percentages rather than comparing raw marks with different maximum scores.

### Rankings

Provide optional rankings:

- Group ranking.
- Subject ranking.
- Grade ranking.
- Examination ranking.

Use consistent rules for ties and exclusions.

Allow organisations to disable rankings, especially on parent-facing pages.

### Early warning system

Automatically identify students who may require attention.

Examples:

- Three consecutive absences.
- Attendance below 70%.
- Examination percentage dropping significantly.
- Multiple missed examinations.
- Performance below a configured threshold.

Display alerts to authorised staff.

Do not label students as failures or make unsupported predictions.

### Performance reports

Generate:

- Individual student performance report.
- Group performance report.
- Subject performance report.
- Examination results report.
- Academic year performance report.
- Attendance and marks combined report.
- Student progress comparison between periods.

Support PDF, Excel, and CSV exports.

Arabic PDFs must render Arabic characters, text shaping, and RTL layout correctly.

---

## 11. Parent Performance Portal

This is another core feature.

Parents must be able to access a private, read-only student performance page without registering or logging in.

### Secure parent link

Generate a long, cryptographically random, unguessable access token.

Example URL structure:

`https://parents.example.com/p/{secure-random-token}`

Do not generate the token from the student's ID, phone number, or other predictable information.

Store only a cryptographic hash of the token where practical.

### Parent page

Display:

- Organisation name and logo.
- Student name.
- Academic year.
- Grade.
- Subjects.
- Attendance percentage.
- Attendance history.
- Examination results.
- Examination percentages.
- Performance charts.
- Teacher comments intended for parents.
- Upcoming examinations.
- Upcoming sessions, if enabled.

Do not expose internal notes, other students' information, staff records, or unrelated financial information.

### Parent-link management

The teacher must be able to:

- Generate a link.
- Copy a link.
- Share through WhatsApp.
- Revoke a link.
- Regenerate a link.
- Set an expiration date.
- View last-access information.
- Disable parent access for a student.

Support multiple guardians with independently revocable links.

### Security and privacy

A private URL is a bearer credential: anyone possessing it can access the page.

Therefore implement:

- Cryptographically secure tokens.
- Rate limiting.
- No indexing by search engines.
- `Cache-Control: no-store` for sensitive pages.
- Referrer policy that prevents token leakage.
- No third-party tracking scripts on tokenised pages.
- Token revocation.
- Optional expiration.
- Optional additional PIN verification for sensitive deployments.
- No student data in the URL.
- No cross-student access.
- Protection against token leakage through logs and analytics.

Do not expose private parent links through ordinary public student searches.

Design personal-data handling with Egyptian data-protection obligations in mind, including appropriate guardian notices, retention rules, access controls, and consent where required.

### Parent page design

The page must be extremely mobile-friendly.

Many parents will open it through WhatsApp on Android devices.

Arabic should be the default where configured.

Provide a language switcher between Arabic and English.

---

## 12. WhatsApp Integration

Allow each organisation to connect its own WhatsApp Business Platform account.

### Organisation WhatsApp settings

Provide a configuration page with:

- Integration status.
- Business phone number.
- WhatsApp Business Account ID.
- Phone Number ID.
- Secure access token configuration.
- Webhook configuration and verification.
- Approved message templates.
- Default messaging language.
- Messaging permissions.
- Delivery status logs.

Prefer Meta's official WhatsApp Business Platform / Cloud API.

Never expose access tokens in frontend code.

Encrypt credentials at rest.

### Supported notifications

- Student attendance confirmation.
- Student absence notification.
- Examination announcement.
- Examination reminder.
- Examination result publication.
- Monthly performance summary.
- Payment reminder.
- Outstanding fee notification.
- Schedule changes.
- Class cancellation.
- Parent-link sharing.
- General announcements.

### Messaging interface

Create a communications dashboard.

Features:

- Select recipients by academic year.
- Select by grade.
- Select by group.
- Select individual students.
- Select guardians.
- Message template selection.
- Message preview.
- Schedule messages.
- View delivery status.
- View failed messages.
- Retry eligible failures.
- View message history.

### WhatsApp compliance

Implement official API requirements.

- Approved templates for applicable business-initiated messages.
- Opt-in and opt-out management.
- Messaging-window restrictions.
- Webhook signature verification.
- Rate limits.
- Usage monitoring.
- Per-organisation message quotas.
- Message cost reporting where available.
- Queued delivery.
- Duplicate-send prevention.
- Retry with backoff.

Do not assume free unlimited messaging.

### Additional communication features

Create a provider-independent notification system that could later support:

- SMS.
- Email.
- Push notifications.

Use a common notification event architecture so the same event can be delivered through multiple channels.

---

## 13. Student Fees and Payments

This is especially important for Egyptian educational centres.

Centres may charge:

- Monthly subscriptions.
- Per-session fees.
- Per-subject fees.
- Term fees.
- Examination fees.
- Registration fees.
- Printed material fees.

### Fee configuration

Allow the organisation to define fee structures by:

- Academic year.
- Grade.
- Subject.
- Group.
- Branch.
- Student.

Support student-specific discounts and scholarships.

### Payment recording

Allow authorised staff to record:

- Cash payments.
- Bank transfers.
- InstaPay payments.
- Mobile wallet payments.
- Online gateway payments.
- Other configured payment methods.

Store payment method, amount, reference, collection date, collector, and receipt number.

### Financial functionality

- Student account statement.
- Outstanding balance.
- Payment history.
- Partial payments.
- Advance payments.
- Discounts.
- Refunds.
- Payment adjustments.
- Receipts.
- Daily cash collection.
- Revenue by teacher.
- Revenue by branch.
- Revenue by subject.
- Revenue by academic year.
- Outstanding fees report.

Never silently delete posted financial transactions.

Use reversals or auditable adjustments.

### Egyptian payment integration

Design a payment-provider abstraction and support Paymob as the first optional online payment integration.

Use webhook verification and idempotent transaction processing.

The platform must work fully with manually recorded cash payments even when no payment gateway is connected.

---

## 14. Student Registration and Self-Service

Provide multiple registration methods.

### Staff registration

Reception staff can manually register students.

### Bulk registration

Import students from Excel.

### Online registration

Allow each organisation to publish a configurable registration form.

Example:

`academy.example.com/register`

Students or guardians can submit:

- Student name.
- Academic level.
- School.
- Phone numbers.
- Preferred group.
- Preferred branch.

Submissions enter a registration review queue.

Staff can approve, reject, or request additional information.

Approved registrations create student records and enrolments without duplication.

### Waiting lists

When groups reach capacity, allow students to join a waiting list.

Staff can move waiting-list students into groups when places become available.

---

## 15. Homework and Assignment Tracking

Although exams are physical, centres may assign homework.

Create an optional homework tracking module.

Teachers can:

- Create homework records.
- Assign homework to groups.
- Set due dates.
- Attach instructions.
- Record submission status.
- Record completion status.
- Record optional marks.
- Add teacher comments.

Do not require students to upload homework online.

Support physical submission tracking.

Statuses:

- Assigned.
- Submitted.
- Late.
- Missing.
- Reviewed.

Show relevant homework information on the parent portal when enabled.

---

## 16. Educational Materials

Allow teachers to upload learning resources.

Examples:

- PDF notes.
- Revision sheets.
- Worksheets.
- Study schedules.
- Examination instructions.
- Reference documents.

Organise resources by academic year, grade, subject, and group.

Provide permission-controlled access.

Files must be stored privately and accessed through authorised, time-limited URLs.

Do not make student-specific or private educational files publicly accessible.

Support configurable storage quotas per subscription plan.

---

## 17. Staff and Assistant Management

Provide a staff management module.

Fields:

- Staff name.
- Phone.
- Email.
- Role.
- Assigned branches.
- Assigned teachers.
- Assigned groups.
- Status.
- Last activity.
- Created date.

### Staff activity audit

Track important actions, including:

- Student registration.
- Student edits.
- Attendance corrections.
- Examination mark changes.
- Payment collection.
- Payment reversals.
- WhatsApp campaigns.
- Parent-link generation and revocation.

Record actor, timestamp, organisation, relevant entity, and changed fields where appropriate.

Never store credentials or sensitive tokens in audit logs.

---

## 18. Dashboard and Analytics

Build a professional, configurable dashboard.

### Organisation dashboard

Show:

- Total active students.
- New registrations this month.
- Active academic years.
- Active groups.
- Today's scheduled sessions.
- Today's attendance.
- Attendance rate.
- Upcoming examinations.
- Recently published results.
- Students requiring attention.
- Today's collections.
- Monthly revenue.
- Outstanding fees.
- WhatsApp delivery summary.

### Charts

- Student registration trends.
- Attendance trends.
- Academic performance trends.
- Revenue trends.
- Student distribution by grade.
- Student distribution by branch.
- Examination results distribution.

Allow filtering by:

- Academic year.
- Date range.
- Teacher.
- Branch.
- Grade.
- Subject.
- Group.

Use efficient aggregate queries and caching where appropriate.

---

## 19. SaaS Subscription Management

Build a complete subscription system.

### Plans

The SaaS super administrator can create and edit plans.

Example plans:

**Starter**

For individual teachers.

**Professional**

For established tutoring centres.

**Business**

For large centres with multiple assistants.

**Enterprise**

For large organisations with multiple branches and teachers.

### Configurable plan limits

- Maximum active students.
- Maximum staff accounts.
- Maximum branches.
- Maximum teachers.
- Maximum storage.
- Monthly WhatsApp message allowance.
- Reporting features.
- Advanced analytics.
- Custom branding.
- API access.
- Parent portal features.

Do not hardcode plan limits into business logic.

### Subscription lifecycle

- Trial.
- Active.
- Past Due.
- Grace Period.
- Suspended.
- Cancelled.
- Expired.

Support monthly and annual billing.

### Billing

- Subscription invoices.
- Payment history.
- Plan upgrades.
- Plan downgrades.
- Renewal reminders.
- Trial expiration.
- Grace periods.
- Payment gateway integration.
- Manual payment approval for bank transfers or InstaPay where appropriate.

Avoid immediately deleting or hiding student records when a subscription expires.

Use configurable restricted-access behaviour and a clear data-retention policy.

---

## 20. SaaS Super Administrator Portal

Create a completely separate administration area.

Features:

- Platform dashboard.
- Organisation management.
- Teacher and owner account management.
- Subscription management.
- Plan management.
- Trial management.
- Revenue analytics.
- Payment transactions.
- Platform usage statistics.
- Support tickets.
- Feature flags.
- Global configuration.
- System announcements.
- Background job monitoring.
- Integration health monitoring.
- Security and audit logs.

### Platform analytics

Show:

- Total organisations.
- Active subscriptions.
- Trial organisations.
- Expired subscriptions.
- Monthly recurring revenue.
- Annual recurring revenue.
- Churn.
- Total active students.
- Total active staff.
- Total attendance records.
- Total examination records.
- Storage consumption.
- Messaging usage.

### Support access

If support impersonation is implemented, require explicit permission, a recorded reason, strong authentication, and full audit logging.

Do not permit silent access to private student information.

---

## 21. Arabic and English Localisation

The entire platform must be bilingual.

### Language requirements

- Arabic.
- English.
- User-selectable interface language.
- Organisation default language.
- Persist user preference.
- Support language switching without losing work.

### RTL and LTR

Arabic must use RTL layout.

English must use LTR layout.

Correctly handle:

- Sidebar placement.
- Navigation.
- Tables.
- Forms.
- Dialogs.
- Dropdowns.
- Pagination.
- Icons.
- Breadcrumbs.
- Charts.
- Date pickers.
- Notifications.
- PDF reports.

Use logical CSS properties where possible.

Do not simply set `direction: rtl` on the entire application and assume the interface is correct.

### Arabic localisation

Use natural Egyptian-market terminology where appropriate.

Examples:

- Ø§Ù„Ø·Ù„Ø§Ø¨ â€” Students
- Ø§Ù„Ù…Ø¯Ø±Ø³ÙˆÙ† â€” Teachers
- Ø§Ù„Ù…Ø³Ø§Ø¹Ø¯ÙˆÙ† â€” Assistants
- Ø§Ù„Ù…Ø¬Ù…ÙˆØ¹Ø§Øª â€” Groups
- Ø§Ù„Ø­Ø¶ÙˆØ± ÙˆØ§Ù„ØºÙŠØ§Ø¨ â€” Attendance
- Ø§Ù„Ø§Ù…ØªØ­Ø§Ù†Ø§Øª â€” Examinations
- Ø§Ù„Ø¯Ø±Ø¬Ø§Øª â€” Marks
- Ø£ÙˆÙ„ÙŠØ§Ø¡ Ø§Ù„Ø£Ù…ÙˆØ± â€” Parents/Guardians
- Ø§Ù„Ù…ØµØ±ÙˆÙØ§Øª â€” Fees
- Ø§Ù„Ø³Ù†Ø© Ø§Ù„Ø¯Ø±Ø§Ø³ÙŠØ© â€” Academic Year

Keep translations in organised translation resources.

Do not hardcode UI labels.

### Dates and numbers

Store dates and times consistently.

Display dates using the organisation's configured locale and Africa/Cairo time zone.

Support configurable date formats and Arabic/Western numeral display.

Use EGP as the default currency.

---

## 22. UI/UX Design Requirements

The application must look like a premium commercial SaaS product.

Avoid generic Bootstrap-style administration screens.

### Visual direction

- Modern.
- Professional.
- Clean.
- Premium.
- Education-oriented.
- Easy to navigate.
- High information density without clutter.
- Consistent spacing.
- Excellent typography.
- Clear status indicators.
- Polished charts and tables.

### Main layout

Desktop:

- Sidebar navigation.
- Top navigation bar.
- Organisation selector if authorised.
- Branch selector.
- Academic year selector.
- Search.
- Notifications.
- User profile menu.
- Language switcher.

Mobile:

- Responsive navigation.
- Touch-friendly buttons.
- Simplified tables.
- Accessible forms.
- Fast attendance scanning interface.

### UX standards

- Skeleton loading states.
- Empty states.
- Confirmation dialogs.
- Toast notifications.
- Validation messages.
- Clear saving indicators.
- Unsaved-change warnings.
- Keyboard navigation.
- Accessible colour contrast.
- Responsive tables.
- Searchable dropdowns.
- Reusable filter components.

### High-volume workflows

Optimise for staff who perform repetitive operations throughout the day.

For example, a receptionist should be able to:

1. Find a student quickly.
2. Register attendance.
3. Record a payment.
4. Print a receipt.

Without navigating through multiple unnecessary pages.

---

## 23. Advanced Search

Provide global search within each organisation.

Searchable entities:

- Students.
- Student codes.
- Guardian phone numbers.
- Groups.
- Examinations.
- Teachers.
- Payment references.

Use efficient indexing and tenant-scoped search.

For student names, support Arabic text normalisation for searching, including common variations of Alef, Hamza, and Yeh where appropriate.

Preserve original names in storage.

---

## 24. Reports and Exporting

Implement a reporting centre.

Reports should include:

1. Student registration report.
2. Attendance report.
3. Absence report.
4. Examination marks report.
5. Student performance report.
6. Group performance report.
7. Teacher performance summary.
8. Academic year comparison.
9. Revenue report.
10. Outstanding payments report.
11. Staff activity report.
12. WhatsApp communication report.

All reports should support relevant filters.

Large reports must be generated asynchronously.

Provide downloadable files with access controls and expiration.

Support Arabic PDF rendering.

---

## 25. Additional Egypt-Specific Features

### A. Parent absence alerts

Allow centres to send notifications when a student misses a session.

Include the session date, subject, and group.

### B. Student card replacement

Students frequently lose physical cards.

Provide a simple replacement workflow that revokes the old card.

### C. Multiple guardians

Support separate parent and guardian phone numbers and private links.

### D. Cash collection workflow

Support cashiers, payment receipts, daily cash reconciliation, and collection summaries.

### E. Group transfers

Students may move from one scheduled group to another.

Preserve previous attendance and examination history.

### F. Monthly fee tracking

Support monthly payment periods, overdue balances, and configurable reminders.

### G. Exam result announcements

Allow teachers to publish results and notify parents through WhatsApp.

### H. Printable examination sheets

Generate printable examination attendance sheets with student names, codes, and optional barcodes.

### I. Printable student lists

Generate class lists for teachers and assistants.

### J. Teacher comments

Allow authorised teachers to add comments to student performance records.

Mark comments as either internal-only or visible to guardians.

### K. Holiday and schedule management

Allow centres to configure Egyptian public holidays and centre-specific closures.

Do not hardcode annual holiday dates.

### L. Parent contact verification

Provide optional phone verification before granting sensitive parent access.

### M. Academic year rollover

Allow organisations to create a new academic year and bulk-promote eligible students into new grades.

Preserve historical records.

Do not automatically promote students without confirmation.

---

## 26. Extra Commercial Features

Design the architecture to accommodate these additional capabilities.

### Referral and discount codes

Centres may offer discounts to students referred by existing students.

### Student loyalty and rewards

Optional recognition for attendance consistency and academic improvement.

### Certificates

Generate configurable certificates for courses and academic achievements.

### Organisation branding

Allow centres to customise:

- Logo.
- Primary colour.
- Display name.
- Printed card design.
- Receipt design.
- Parent portal appearance.

### Announcements

Teachers can publish announcements for selected groups or academic years.

### Parent feedback

Provide an optional structured feedback form for guardians.

### Student notes

Allow authorised staff to record academic support notes, with strict visibility controls.

### Centre expenses

Track rent, utilities, staff costs, printing, and other operational expenses.

### Teacher compensation

Optional configuration for fixed salary, per-session payment, or revenue-share calculations.

### Resource inventory

Track printed books, worksheets, and other educational materials sold or distributed by centres.

These features should be modular and controlled by subscription-plan entitlements.

---

## 27. Database Design

Design a properly normalised relational database.

At minimum, evaluate the following tables and relationships:

### SaaS and identity

- organizations
- organization_settings
- branches
- users
- organization_users
- roles
- permissions
- subscriptions
- subscription_plans
- subscription_invoices
- subscription_payments
- plan_entitlements
- audit_logs

### Academic

- academic_years
- educational_levels
- grades
- subjects
- teachers
- classrooms
- teaching_groups
- group_schedules
- scheduled_sessions
- session_exceptions
- group_enrollments

### Students

- students
- guardians
- student_guardians
- student_enrollments
- student_cards
- student_documents
- student_notes
- student_custom_fields
- student_registration_requests

### Attendance

- attendance_records
- attendance_corrections
- attendance_scan_events

### Examinations

- examinations
- examination_groups
- examination_participants
- examination_attendance
- examination_results
- examination_result_revisions
- examination_publications

### Parent access

- parent_access_links
- parent_access_events

### Finance

- fee_plans
- student_charges
- student_payments
- payment_allocations
- payment_adjustments
- payment_refunds
- receipts
- expenses
- cash_reconciliations

### Communications

- communication_providers
- whatsapp_integrations
- message_templates
- message_campaigns
- message_recipients
- outbound_messages
- message_delivery_events
- communication_consents

### Additional modules

- homework_assignments
- homework_student_records
- educational_resources
- certificates
- announcements
- notifications
- import_batches
- export_jobs
- generated_reports

This list is a starting point, not an instruction to create unnecessary tables.

Design the final schema around actual relationships and business invariants.

### Database rules

- Use proper foreign keys.
- Use appropriate unique constraints.
- Use composite indexes for tenant-scoped queries.
- Index attendance by session and student.
- Index examination results by examination and student.
- Index enrolments by academic year, group, and student.
- Avoid unnecessary duplicated data.
- Preserve financial and academic history.
- Use soft deletion only where appropriate.
- Never cascade-delete important examination or financial history accidentally.
- Use database transactions for multi-step critical operations.

---

## 28. Performance and Scalability

Design for thousands of active students per organisation.

### Performance requirements

- Server-side pagination.
- Efficient filtering.
- Proper indexes.
- Avoid N+1 queries.
- Cache expensive aggregates.
- Queue bulk imports.
- Queue PDF generation.
- Queue WhatsApp messages.
- Queue large exports.
- Process notifications asynchronously.
- Use efficient bulk database operations.

### Attendance performance

Barcode scanning must feel immediate.

Target fast responses under normal operating conditions, with a practical p95 target of under 500 ms for the core check-in API, excluding unusually slow networks.

### Large datasets

Test with at least:

- 100 organisations.
- 10,000 students in one organisation.
- 100,000 student records across the platform.
- 1,000,000 attendance records.
- 500,000 examination result records.

Use synthetic test data.

Benchmark and document the results.

---

## 29. Security Requirements

Security must be built into the architecture.

Implement:

- Strong password hashing.
- CSRF protection.
- XSS protection.
- SQL injection prevention.
- Secure session management.
- Login rate limiting.
- Password reset.
- Optional multi-factor authentication.
- Tenant isolation.
- Authorisation policies.
- Secure file access.
- Encrypted API credentials.
- Webhook signature verification.
- Audit logging.
- Secure token generation.
- Private parent links.
- Sensitive-data redaction.
- Secure backups.
- Environment-based secrets.
- Appropriate security headers.

Never store plain-text passwords.

Never expose WhatsApp access tokens or payment credentials.

Never trust a client-supplied organisation ID without verifying authorisation.

Do not allow assistants to access restricted student information by modifying request parameters.

### Data privacy

Treat student and guardian data as sensitive personal information, especially because many students are minors.

Include configurable data retention, access and deletion workflows, consent/notice records where required, and the ability to export a student's records subject to appropriate authorisation.

Do not include student personal information in third-party analytics events.

---

## 30. Testing Requirements

Implement automated tests for critical workflows.

### Essential tests

- Organisation registration.
- Tenant isolation.
- Role permissions.
- Student registration.
- Unique student code generation.
- Card creation and replacement.
- Barcode attendance.
- Duplicate scan prevention.
- Group enrolment.
- Session scheduling.
- Examination creation.
- Examination participant assignment.
- Marks entry.
- Marks validation.
- Result publication.
- Published result correction.
- Parent link access.
- Parent link revocation.
- Payment recording.
- Payment reversal.
- Subscription restrictions.
- WhatsApp webhook verification.
- Background import processing.

### Concurrency tests

Specifically test:

- Simultaneous student creation.
- Simultaneous scans of the same card.
- Concurrent marks updates.
- Duplicate payment webhook delivery.
- Concurrent subscription changes.

### Frontend tests

Include end-to-end tests for critical staff workflows.

Test both Arabic RTL and English LTR layouts.

---

## 31. Deployment and Operations

Prepare the application for production deployment on a Linux server.

Provide:

- Environment configuration example.
- Database migration instructions.
- Queue worker configuration.
- Scheduler configuration.
- Redis configuration.
- File storage configuration.
- Web server configuration.
- HTTPS requirements.
- Backup and restore procedures.
- Logging configuration.
- Monitoring guidance.
- Deployment instructions.

Use environment variables for credentials.

Do not include production secrets in source control.

Provide health checks for database, Redis, queues, and critical integrations.

Include failed-job monitoring and retry procedures.

---

## 32. Implementation Plan

Do not attempt to implement the entire application in one uncontrolled code-generation step.

Work through clear phases.

### Phase 1 â€” Foundation

- Laravel project setup.
- Database configuration.
- Authentication.
- Multi-tenancy.
- Roles and permissions.
- Organisation registration.
- Organisation settings.
- Arabic and English localisation.
- RTL/LTR layouts.
- Main dashboard shell.
- Shared UI components.

### Phase 2 â€” Academic and Student Management

- Academic years.
- Educational levels.
- Grades.
- Subjects.
- Teachers.
- Branches.
- Groups.
- Student registration.
- Student enrolments.
- Bulk student import.
- Student cards.
- Barcode generation.

### Phase 3 â€” Attendance

- Session scheduling.
- Attendance scanner.
- Manual attendance.
- Duplicate scan prevention.
- Attendance correction.
- Absence calculation.
- Attendance reports.

### Phase 4 â€” Examinations and Performance

- Examination creation.
- Examination assignment.
- Examination attendance.
- Marks entry.
- Bulk marks import.
- Result publication.
- Performance analytics.
- Student reports.

### Phase 5 â€” Parent Portal

- Secure parent links.
- Guardian management.
- Parent performance pages.
- Link revocation.
- Parent-facing reports.
- Mobile optimisation.

### Phase 6 â€” Finance

- Fee plans.
- Student charges.
- Payments.
- Receipts.
- Outstanding balances.
- Revenue reporting.
- Payment gateway abstraction.

### Phase 7 â€” Communications

- WhatsApp integration settings.
- Message templates.
- Notification events.
- Queued sending.
- Delivery tracking.
- Communication history.
- Consent and opt-out handling.

### Phase 8 â€” SaaS Commercialisation

- Subscription plans.
- Plan limits.
- Trial periods.
- Billing.
- Super-admin portal.
- Platform analytics.
- Subscription lifecycle management.

### Phase 9 â€” Additional Features

- Homework tracking.
- Educational resources.
- Certificates.
- Announcements.
- Waiting lists.
- Advanced reporting.
- Branding customisation.
- Optional offline attendance.

### Phase 10 â€” Production Hardening

- Security review.
- Performance optimisation.
- Load testing.
- Accessibility testing.
- Arabic RTL testing.
- Backup verification.
- Production deployment documentation.
- End-to-end testing.

---

## 33. Engineering Quality Standards

Follow these rules throughout development.

1. Write maintainable, production-quality code.
2. Do not create placeholder buttons that do nothing.
3. Do not create fake statistics or hardcoded dashboard metrics.
4. Do not use mock records in production functionality.
5. Do not bypass authentication or authorisation.
6. Do not implement tenant isolation only in the frontend.
7. Do not place business logic inside Blade or Vue templates.
8. Do not duplicate business logic across controllers.
9. Use database transactions for critical operations.
10. Validate all user input.
11. Use proper error handling.
12. Use queued jobs for expensive operations.
13. Make financial and attendance operations idempotent where necessary.
14. Write meaningful automated tests.
15. Document important architectural decisions.
16. Use clean naming conventions.
17. Keep the interface consistent.
18. Ensure Arabic translations are complete.
19. Ensure all features work on mobile.
20. Avoid unnecessary dependencies and overengineering.

---

## 34. Acceptance Criteria

The application is not considered complete until the following end-to-end scenario works:

1. A new teacher registers.
2. The teacher creates an organisation.
3. The teacher activates a subscription or trial.
4. The teacher configures an academic year.
5. The teacher adds Secondary 3 as an educational grade.
6. The teacher creates Physics as a subject.
7. The teacher creates two teaching groups.
8. The teacher registers 2,000 students through Excel import.
9. Every student receives a unique identifier.
10. The teacher generates printable student cards with barcodes.
11. The teacher creates an assistant account.
12. The assistant logs in with restricted permissions.
13. The assistant selects a scheduled Physics session.
14. The assistant scans a student's card.
15. The student is marked present.
16. Scanning the same card again does not create duplicate attendance.
17. The teacher creates a physical examination.
18. The examination is assigned to the correct groups.
19. The assistant records examination attendance.
20. The assistant enters examination marks.
21. The teacher reviews and publishes the results.
22. The student's performance dashboard updates correctly.
23. The teacher generates a secure parent link.
24. The parent opens the link without logging in.
25. The parent sees only their student's published performance.
26. The teacher revokes the link.
27. The old link stops working immediately.
28. The assistant records a student fee payment.
29. The payment appears in the student's financial history.
30. The organisation's dashboard reflects the updated attendance, results, and collections.
31. The user switches from English to Arabic.
32. The entire interface correctly switches to RTL.
33. A different organisation cannot access any of these records.
34. All critical automated tests pass.

---

## 35. Egypt-market competitiveness requirements (added 2026-11-06)

Benchmarking Egyptian centre products shows that a credible alternative must make the daily front-desk workflow faster than paper, Excel and WhatsApp. Keep these requirements in the queued roadmap:

- Arabic-first mobile/PWA operation with reliable RTL, fast search and large touch targets.
- QR/barcode attendance with manual fallback, duplicate-safe processing, late/absence states and instant parent notifications.
- Student profile, groups, schedules, capacity, transfers, guardian portal, attendance history and academic results in one timeline.
- Fees, instalments, discounts, outstanding balances, receipts and cash/Fawry/card payment reconciliation with branch-level reporting.
- Parent communication centre with consent, templates, delivery status, WhatsApp-ready messages and in-app announcements.
- Offline-ready attendance/marks/payment queue with explicit pending and conflict states; never claim a record synced until the server confirms it.
- Online registration/application intake, staged CSV/XLSX import with duplicate detection, and printable QR cards.
- Optional student portal for lessons, homework and timed online exams, while keeping the core centre operations usable without an LMS.
- Owner dashboards for daily collections, attendance, debtors, teacher workload, retention and branch comparison, with exportable reports.
- Differentiation: transparent per-centre tenancy, granular audit/security controls, bilingual UX, parent privacy by default and an Arabic operational assistant that only answers from authorised centre data.

Prioritise the operational wedge in this order: student editing/archival → groups and schedules → attendance → fees/collections → parent notifications/portal → exams and online learning → analytics/assistant.

## 36. Required Deliverables

Produce:

- Complete application source code.
- Database migrations.
- Models and relationships.
- Controllers and business services.
- Authorisation policies.
- Background jobs.
- Frontend pages and components.
- Arabic and English translations.
- Database seeders.
- Test suites.
- Sample environment configuration.
- Deployment documentation.
- Architecture documentation.
- Database relationship documentation.
- Administrator guide.
- Teacher and assistant user guide.
- Integration setup guides.

Include sample demonstration data in development environments only.

---

## 37. How You Must Work

Before writing implementation code:

1. Inspect the existing repository and environment.
2. Identify the installed versions and existing conventions.
3. Produce a proposed application architecture.
4. Produce a complete database relationship design.
5. Identify critical security and tenancy boundaries.
6. Produce a dependency-aware implementation plan.
7. Identify any genuinely blocking decisions.
8. Make sensible documented assumptions for non-blocking details.

Then begin implementing Phase 1.

After each phase:

- Run migrations.
- Run relevant automated tests.
- Fix discovered errors.
- Verify the main workflows.
- Update project documentation.
- Report completed functionality.
- Report remaining functionality.
- Identify any blockers.

Do not claim that a feature is complete unless it has actually been implemented and verified.

Do not silently skip difficult requirements.

Do not substitute fake data or static UI demonstrations for working functionality.

Continue implementing the agreed phases in sequence, using the project plan as the source of truth.

**The final objective is a polished, secure, scalable, bilingual SaaS platform that Egyptian teachers and educational centres can realistically subscribe to and use every day to manage thousands of students, attendance, examinations, academic performance, parents, staff, and payments.**

