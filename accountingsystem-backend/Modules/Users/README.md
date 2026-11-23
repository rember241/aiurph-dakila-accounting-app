# Users Module (Backend)

Enterprise-grade user, role, onboarding, and company-access APIs for the ERP.

## What This Module Adds

- `roles` table (UUID primary key) with JSON module permissions.
- `role_user` pivot table for global/shared role assignments.
- Additional user-management fields on `users` (`status`, `department`, `access_level`, `avatar_url`, `last_active_at`).
- Company foundation stored on the existing legacy tenancy tables for compatibility:
  - `tenants` as the current company master table
- `tenant_memberships` as company membership rows
- `membership_roles` as company-scoped role assignments
- `org_units` as company organization-structure units
- `tenant_databases` retained only for future compatibility
  - `invitations` retained for future compatibility
- Versioned API endpoints under `/api/v1/*` protected by Sanctum auth.
- Audit logging via the existing `App\\Services\\Audit\\AuditLogService`.

## Setup

1. Run migrations:
   - `php artisan migrate`
2. Seed defaults:
   - `php artisan db:seed`
3. Login to receive a Sanctum token for authenticated API access.
4. Call the APIs through the frontend proxy with the Bearer token.

## Current Domain Model

- Company is now the single organization boundary.
- Each user must belong to exactly one company.
- New onboarding and company-bootstrap flows enforce the single-company rule.
- Legacy tenant-named tables and services remain in place as a compatibility layer while the domain language moves to company-centric APIs.
- Runtime company switching is not supported.

## Authorization

- Global/shared APIs still use Sanctum token abilities from global `role_user` assignments:
  - `users.read`
  - `users.write`
  - `roles.read`
  - `roles.write`
- Company-scoped business APIs resolve the current company from the authenticated user.
- Company-scoped business APIs then enforce:
  - company access (owner or active membership)
  - company role permissions at request time
- Company org-structure APIs use:
  - `org_units.read`
  - `org_units.write`
- Legacy `X-Company-ID` and `X-Tenant-ID` headers are accepted only as compatibility hints. They must match the authenticated user's resolved company and cannot be used for switching.

## Company Context Contract

- Shared/auth/onboarding endpoints do not require company context.
- Company-scoped endpoints resolve and attach these request attributes:
  - `company_id`
  - `company`
  - `tenant_id`
  - `tenant`
- If the authenticated user has no assigned company, company-scoped routes return `404` with `Company context not found.`
- If a compatibility header is malformed, company-scoped routes return `400`.
- If a compatibility header tries to point at a different company, company-scoped routes return `409` because runtime switching is not supported.

## Endpoints

Current user and onboarding:
- `GET /api/v1/me/onboarding-status`
- `POST /api/v1/me/onboarding/company-setup`
- `GET /api/v1/me/access`
- `GET /api/me`

Company reads:
- `GET /api/v1/company` (current company snapshot)
- `GET /api/v1/companies` (current company list for compatibility; resolves to a single company)
- `GET /api/v1/companies/{company}`

Settings:
- `GET /api/v1/settings/user`
- `PUT /api/v1/settings/user`
- `GET /api/v1/settings/company`
- `PUT /api/v1/settings/company`

Users:
- `GET /api/v1/users`
- `GET /api/v1/users/stats`
- `POST /api/v1/users`
- `GET /api/v1/users/{user}`
- `PUT /api/v1/users/{user}`
- `PATCH /api/v1/users/{user}/status`
- `DELETE /api/v1/users/{user}`

Roles:
- `GET /api/v1/roles`
- `GET /api/v1/roles/stats`
- `POST /api/v1/roles`
- `GET /api/v1/roles/{role}`
- `PUT /api/v1/roles/{role}`
- `PATCH /api/v1/roles/{role}/status`
- `DELETE /api/v1/roles/{role}`

Organization units:
- `GET /api/v1/org-units`
- `GET /api/v1/org-units/stats`
- `POST /api/v1/org-units`
- `GET /api/v1/org-units/{orgUnit}`
- `PUT /api/v1/org-units/{orgUnit}`
- `PATCH /api/v1/org-units/{orgUnit}/status`
- `DELETE /api/v1/org-units/{orgUnit}`

Legacy compatibility routes:
- `GET /api/v1/tenants`
- `POST /api/v1/tenants`
- `POST /api/v1/tenants/company-setup`
- `GET /api/v1/tenants/{tenant}`

## Onboarding and Company Bootstrap

- `POST /api/v1/me/onboarding/company-setup` is the canonical initial setup flow.
- It creates:
  - the company master record
  - the owner membership
  - default company roles (`admin`, `accountant`, `viewer`)
  - an optional default root org unit (`Head Office`)
  - the initial fiscal year
  - the generated accounting periods
  - optional starter chart of accounts when requested
- It does not create a dedicated tenant database.
- `POST /api/v1/tenants` remains as a compatibility bootstrap path, but it still resolves to the same single-company onboarding rule.
- Creating an additional company for the same user is no longer supported.

## Audit Events

This module records audit entries for:
- `user.settings.updated`
- `company.settings.updated`
- `user.created`, `user.updated`, `user.activated`, `user.deactivated`, `user.deleted`
- `role.created`, `role.updated`, `role.activated`, `role.deactivated`, `role.deleted`
- `tenant.created`, `tenant_membership.created`, `tenant_membership.role_assigned`
- `org_unit.created`, `org_unit.updated`, `org_unit.activated`, `org_unit.deactivated`, `org_unit.deleted`
- `fiscal_year.created` during onboarding company setup

Audit rows remain in the shared `audit_logs` table.
Legacy storage still uses the `tenant_id` column, while company-aware flows also include `company_id` in audit metadata where applicable.
