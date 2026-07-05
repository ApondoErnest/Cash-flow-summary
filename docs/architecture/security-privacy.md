# Security and Privacy

[← Documentation hub](../README.md) | [permission-matrix.md](../product/permission-matrix.md)

---

## Authentication

- Username + password (no public registration)
- Minimum password strength enforced
- Temporary password → forced change on first login — **implemented Step 35** (`/password/change`)
- Login rate limiting (per IP + per username)
- Session timeout configurable
- Owner two-factor authentication (TOTP) — **implemented Step 34** (`/two-factor/setup`, `/two-factor/challenge`)
- Recovery codes stored encrypted

---

## Center isolation

Enforced at every layer:

| Layer | Mechanism |
|-------|-----------|
| Middleware | `EnsureAssignedCenter` (Manager/Cashier); `EnsureOwnerActiveCenter` (Owner operational routes) |
| Policies | `view`, `import`, `download` check center_id |
| Query scopes | `CenterScope` on tenant models |
| Services | Validate center from auth, not request alone |
| Exports | Filter by authorized centers |
| Downloads | Signed URLs scoped to user's center |
| Queue jobs | Serialize `center_id` from **import record**; re-validate on handle; never read Owner session in jobs |

**Owner:** selects **one active working center** in session after Center Selection. Operational routes use `EnsureOwnerActiveCenter`. Admin routes (manage centers/users) are organization-wide.

**Manager/Cashier:** `center_id` from user account — reject tampered `center_id` in requests.

---

## CSV and file security

| Control | Implementation |
|---------|----------------|
| Storage | `storage/app/private/` — not web-accessible |
| Temp files | `storage/app/temp/verifications/` — deleted on reject/expiry |
| Filename | Sanitize; store original name in DB only |
| Size limit | Configurable max upload (e.g. 50MB) |
| Content | Validate CSV structure before parse |
| Downloads | Authorized policy + short-lived signed route |
| Rejected files | Deleted; content not in audit log |

---

## Verification token security

- UUID v4 token; HTTPS only in production
- Bound to: user_id, center_id, file_hash
- Single-use on Import
- Expires after TTL (default 2 hours)
- Invalid after Reject
- Tests: reuse, cross-user, post-expiry, post-reject

---

## Financial integrity

- `DECIMAL(15,2)` for money columns
- Unique constraint on master records
- Database transactions around import commit
- Completed imports immutable — changes via revision workflow only
- Owner approval required for revisions
- Reports from active snapshots only

---

## Audit and privacy

Audit events per plan.md §33. **Do not** log:

- Full CSV content on rejection
- Customer lists in WhatsApp payloads
- Passwords or 2FA secrets

PII in import_rows protected by center isolation and private storage.

---

## Production hardening (S8)

- HTTPS only; HSTS
- Secure cookie flags
- CSP headers
- `.env` secrets not in Git
- WhatsApp tokens in encrypted settings (access token always; webhook verify token when production webhooks enabled)
- SSH key VPS access; firewall; non-root deploy user
- Regular dependency updates

---

## Related

- REQ-005, REQ-100–REQ-103, REQ-096
- [ADR 0005](decisions/0005-exact-duplicate-ledger.md)
- [ADR 0009](decisions/0009-verification-token-flow.md)
