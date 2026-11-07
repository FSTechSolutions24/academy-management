# Security and tenancy boundaries

1. Resolve organisations only through an authenticated active membership.
2. Scope every query, uniqueness rule, cache key, job payload, export and private file path to that organisation.
3. Use policies plus server-side validation for all writes; hiding a UI control is not authorisation.
4. Rate-limit authentication, scanning, parent-link access and outbound-message endpoints.
5. Encrypt provider credentials and never return secrets after storage.
6. Use transactions and database uniqueness for scans, codes, payments and publication transitions.
7. Record actor, organisation, branch, event, target, before/after metadata, IP and user agent for sensitive actions.
8. Reject spreadsheet formula injection on export and validate file type, size and ownership on upload.
9. Hash parent/card tokens and compare in constant time; revoke old card/link tokens immediately.
10. Add a cross-tenant denial test to every tenant-owned module before marking it complete.
11. Resolve the active branch through the authenticated organisation membership and server-side session; never trust a submitted branch ID as authorisation.
12. Scope operational queries, validation, jobs, caches, exports, files and aggregate reports to both organisation and branch.
13. Owners may access all branches in their organisation; staff require an active `branch_user` assignment. A branch assignment can never cross its organisation.
14. Add cross-branch denial and dashboard-isolation tests to every operational module in addition to cross-organisation tests.
15. Store invitation tokens only as SHA-256 hashes, expire them after seven days and invalidate them immediately after acceptance or revocation.
16. Validate every invited role and centre against the active organisation; foreign IDs must fail validation without creating partial access.
17. Protect the last owner and prevent users from changing their own role/status through the team-administration endpoint.
18. Development uses the log mailer; configure authenticated SMTP and a trusted sender domain before production invitations are enabled.
19. Require verified email ownership before organisation or centre middleware executes. Keep only session inspection, verification resend and logout available to an unverified account.
20. Verification URLs must be signed and throttled; validate both the user ID and a constant-time hash of the verification email before marking ownership verified.
21. Password-recovery requests must return the same response for known and unknown addresses. Use expiring, single-use password-broker tokens and rotate the remember token after reset.
22. Store member-specific permission exceptions as separate allow and deny lists. A deny must take precedence over both the role baseline and individual allows; owner access cannot be edited through this workflow.
23. Scope audit queries to the resolved organisation before applying event, actor or centre filters. Audit history is read-only and unavailable to ordinary teaching roles.

24. Stage student imports only in private storage; enforce upload/row/expanded-XLSX limits, reject spreadsheet formulas, scope every batch to the active organisation and branch, recheck import permission on each endpoint, and neutralize formula-leading cells in downloadable error CSVs.
25. Store only SHA-256 hashes of student-card tokens; return a new token only in the one-time issuance response, revoke the previous token transactionally on reissue, scope issuance/revocation/scans to the active organisation and branch, and audit card lifecycle actions.
26. Restrict platform account and subscription writes to verified platform administrators; confirm user deactivation and manual renewals in the interface, audit all such writes, require a renewal note, prevent self-deactivation and protect the last active platform administrator, and reject inactive accounts on every web request and login.
27. Mark complimentary subscription renewals explicitly as gifts, require a reason, force their amount to zero and exclude them from collected-revenue totals.
28. Treat platform-administrator accounts as protected identities: omit edit/deactivate controls in the user directory and reject every platform user-management write against them on the server.
29. Require explicit, auditable guardian WhatsApp opt-in before queueing messages; scope templates, consent and delivery records to the active branch, recheck consent in the worker, rate-limit sends/retries and never expose provider credentials.
