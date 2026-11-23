# Accounting API Structure

The Accounting API provides endpoints to manage period management, chart of accounts, journal entries and reports.

## Tenant Contract

- Base API prefix: `/api/v1`
- Tenant-scoped Accounting APIs require the `X-Tenant-ID` request header.
- The header must contain a valid tenant UUID from the shared database.
- The authenticated user must own the tenant or have an active membership in it.
- Tenant-scoped Accounting route permissions are resolved against the active tenant membership, not token-wide Sanctum abilities.
- Shared lookup endpoints such as account types do not require tenant context.
- Tenant resolution and tenant-access checks happen before Accounting business handlers run.
- Fiscal years are stored in the shared database with `tenant_id` and are unique per tenant, not globally.
- Accounting periods are also stored in the shared database with `tenant_id` and must stay aligned with their parent fiscal year tenant.
- Accounts are stored in the shared database with `tenant_id`, and account-code uniqueness is tenant-scoped.
- Tenant-scoped write actions emit audit rows into the shared `audit_logs` table with `tenant_id` populated from the resolved request context.

## Full Structure Overview

```markdown
/api/v1/
    account-types
    account-types/{accountType}/detail-types
    accounts
    journal-entries
    accounting/period-management/
        fiscal-years
        periods
        kpi
```

## Structure Definition

The current Accounting API is exposed under `/api/v1/`.

#### Period Management

The period management endpoints are located at `/api/v1/accounting/period-management/`.

- `/api/v1/accounting/period-management/fiscal-years`: manage the fiscal years.
- `/api/v1/accounting/period-management/periods`: manage the periods.
- `/api/v1/accounting/period-management/kpi`: retrieve accounting period KPIs.

#### Chart of Accounts

Shared lookups:

- `/api/v1/account-types`: list account types.
- `/api/v1/account-types/{accountType}/detail-types`: list detail types for one account type.

Tenant-scoped chart of accounts endpoints:

- `/api/v1/accounts`: manage the accounts.

#### Journal

The journal endpoints are located at `/api/v1/journal-entries`.

- `/api/v1/journal-entries`: manage the journal entries.

#### Reports

Report endpoints are not implemented in the current module routes yet.
