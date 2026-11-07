# Database relationship design

## Foundation implemented

- `users` M:N `organizations` through `organization_memberships`
- `organizations` 1:N `branches`
- `branches` 1:N `academic_years`, `educational_levels`, `subjects`
- `users` M:N `branches` through `branch_user` for non-owner centre access
- `organizations` 1:N `organization_roles`; memberships reference a role definition while retaining a stable role key.
- `organizations` 1:N `organization_invitations`; invitations hold hashed tokens, expiry and explicit branch IDs.
- `audit_logs` retain organisation, optional branch, actor, event, target and before/after metadata.

## Planned domain graph

- Academic: branches own academic years, educational levels, subjects, classrooms, teachers, groups and scheduled sessions. Every row also retains its parent `organization_id` for defence-in-depth scoping.
- Students: branches own students, guardians, enrolments, custom-field values and cards. Cards use a hashed opaque token and lifecycle status. Cross-centre transfers or shared enrolments are explicit workflows.
- Attendance: sessions have expected enrolments; attendance records reference session and student with a unique `(organization_id, session_id, student_id)` constraint. Corrections append audit events.
- Examinations: exams target groups/levels/students through assignments, snapshot expected participants and own attendance plus results. Results move through draft, review, published and locked states.
- Performance: derived aggregates are rebuilt from published results and confirmed attendance; comparisons use percentages.
- Parent portal: student-scoped access links store token hashes, expiry, revocation and access history.
- Finance: fee plans create student invoices; invoices own items and payments. Amounts are integer minor units and payment attempts have idempotency keys.
- Communications: branch-scoped templates, current guardian consent, and WhatsApp outbox/delivery records reference organisation and student/guardian context; message parameters are snapshotted and consent is rechecked by the worker before sending.
- Platform: plans are global; subscriptions, usage and gateway attempts belong to organisations; platform actions are separately audited.

- student_imports and student_import_rows stage private, branch-scoped CSV/XLSX uploads, row validation outcomes and queued processing progress.

## Required indexing pattern

Every operational table starts indexes with `(organization_id, branch_id)`. High-volume lists add composite indexes matching filters, such as `(organization_id, branch_id, status, created_at)`, student code and normalized guardian phone. Uniqueness that belongs to a centre includes `branch_id`. Foreign keys never substitute for authorisation.
