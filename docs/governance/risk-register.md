# Risk Register

[← Documentation hub](../README.md)

| ID | Risk | Likelihood | Impact | Mitigation | Owner |
|----|------|------------|--------|------------|-------|
| R-01 | Duplicate master on concurrent upload | Medium | Critical | Unique DB constraint; transactional import | Developer |
| R-02 | Cross-center data leak | Low | Critical | Middleware, policies, scopes, tests | Developer |
| R-03 | Report double-counting | Medium | High | Active snapshot-only rule; tests | Developer |
| R-04 | WhatsApp API outage | Medium | Medium | Queue retries; import not rolled back | Developer |
| R-05 | Real CSV committed to Git | Medium | High | .gitignore; sanitized fixtures only | Developer |
| R-06 | Verification token reuse / expiry bugs | Medium | High | Single-use token; short TTL; security tests | Developer |
| R-07 | Large file memory / timeout | Medium | High | Stream parsing; queued verify + commit; chunked inserts/ledger; 600s job timeouts | Developer |
| R-08 | Normalization policy change invalidates hashes | Low | Critical | Version column; migration plan if changed | Developer |
| R-09 | Owner unavailable for revisions | Medium | Medium | Dashboard pending queue | Business Owner |
| R-10 | Scope creep (wizard, email, extra roles) | Medium | Medium | plan.md §42; change control | Business Owner |

Review quarterly and after each sprint gate.
