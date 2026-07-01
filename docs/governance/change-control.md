# Change Control

[← Documentation hub](../README.md)

---

## Purpose

Ensure changes to requirements, schema, calculations, or UX are reviewed before implementation.

## Change request contents

1. Summary and business justification
2. Affected REQ-xxx IDs
3. Effect on database ([data-model.md](../design/data-model.md))
4. Effect on calculations / normalization ([calculations.md](../design/calculations.md), [normalization-policy.md](../design/normalization-policy.md))
5. Effect on permissions ([permission-matrix.md](../product/permission-matrix.md))
6. Test impact
7. Migration / rollback plan for production

## Approval

| Change type | Approver |
|-------------|----------|
| Business rule | Business Owner |
| Schema | Developer + Owner if financial impact |
| Normalization policy version | Business Owner (may require data migration) |
| Security | Developer + Owner |
| Deployment / infra | Developer |

## Documentation updates

Every approved change updates the primary doc(s) per [docs/README.md](../README.md) ownership matrix and [CHANGELOG.md](../../CHANGELOG.md).

## Emergency fixes

Production hotfixes may ship first; documentation and retrospective change request within 48 hours.
