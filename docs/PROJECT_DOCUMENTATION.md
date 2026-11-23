# Dakila Free Accounting — Developer Guide

For installation, prerequisites, and local startup commands, use the [README](../README.md). This guide describes the free accounting edition, not the original ERP feature set.

## Product scope

The application includes an accounting dashboard, chart of accounts, journal entries, general ledger, financial reports, accounting setup, fiscal periods, and company-scoped roles. Roles & Permissions is the only settings page.

`accountingsystem-backend/modules_statuses.json` loads `IdentityAccess`, `Accounting`, and `Users`. The Users module supplies company context, onboarding, and role infrastructure; it does not expose the former user-management workspace in this edition.

`accountingsystem-backend/config/module_availability.php` controls effective feature access. The `free_accounting` profile enables `identityaccess`, `accounting`, `journal`, `period`, and `roles`. Dashboard data requires `accounting.read`; the legacy `dashboard_reports` permission is not a separate entitlement in this edition.

Sales, Customers, Inventory, and Expenses are disabled. Their frontend pages, feature implementations, and print pages have been removed. Shared backend models and historical migrations remain because journal voiding and legacy ledger balances still reference them. Do not delete those dependencies or their tables without a separate data migration and accounting reconciliation.

## Structure and conventions

| Location | Responsibility |
| --- | --- |
| Backend `Modules/IdentityAccess/` | Login, logout, current-user identity |
| Backend `Modules/Accounting/` | Accounting routes, validation, services, reports, tests |
| Backend `Modules/Users/` | Company context, onboarding, roles, access checks |
| Backend `app/Support/ModuleAvailability.php` | Feature availability decisions |
| Frontend `src/app/` | Next.js route entry points and layouts |
| Frontend `src/features/` | API clients, queries, schemas, domain types |
| Frontend `src/views/` | Page composition and feature UI |
| Frontend `src/components/`, `src/@core/`, `src/@menu/` | Shared components and template infrastructure |
| Frontend `src/lib/` | Server session/access helpers and HTTP clients |

Keep route entry points thin. Backend requests validate input, controllers delegate to services, and resources shape responses. Frontend API functions handle transport; query hooks manage server state; views compose the existing template components.

`tenant` names remain in some files, cookies, and tables for compatibility. Current authorization resolves company context on the server; a client-provided company ID is not proof of access.

## Authentication and authorization

Authentication uses Laravel Sanctum browser sessions and CSRF protection. IdentityAccess owns `/api/login`, `/api/logout`, and `/api/me`. Browser business requests go directly to Laravel using credentials and CSRF headers. Server-rendered pages forward session information using the server helpers.

Company routes run through `company.context`, `company.access`, and `company.ability`. The custom role system stores module permissions and computes effective access for the current company. Spatie Permission is not installed. Disabled modules stay unavailable even for company owners.

The frontend obtains effective abilities from `/api/v1/me/access`, filters navigation, and checks access at page boundaries. Backend checks remain authoritative. Login redirects accounting users to `/home`; roles-only users can land on `/users/roles`.

See [authentication recovery](AUTHENTICATION_RECOVERY.md) for expired sessions, temporary outages, CSRF retry behavior, and the related tests.

## Environment configuration

Frontend `.env.local`:

```dotenv
NEXT_PUBLIC_APP_URL=http://127.0.0.1:3001
NEXT_PUBLIC_LARAVEL_API_URL=http://127.0.0.1:8000
LARAVEL_API_URL=http://127.0.0.1:8000
LARAVEL_SESSION_COOKIE_NAME=aiur_laravel_session
```

`NEXT_PUBLIC_LARAVEL_API_URL` serves browser requests; `LARAVEL_API_URL` serves server-side requests. The cookie name must match backend `SESSION_COOKIE`. Public variables are embedded in the frontend build, so configure them before building.

Backend `.env` supplies database credentials, `APP_KEY`, `APP_URL`, `FRONTEND_URL`, session storage, CORS, and Sanctum stateful domains. Use the checked-in `.env.example` as the full reference. Use one hostname consistently: mixing `localhost` and `127.0.0.1` can prevent cookies from reaching the right origin.

Do not commit actual environment files. Preserve `APP_KEY` and the session store across ordinary restarts and deployments.

## Accounting behavior

The dashboard endpoint is `GET /api/v1/accounting/dashboard?startDate=YYYY-MM-DD&date=YYYY-MM-DD`. It resolves all posting accounts for the authenticated company and reuses the income statement and balance sheet services. It does not aggregate sales invoices or inventory quantities. Draft journals do not contribute to the report totals.

The dashboard shows month-to-date income and expenses and the financial position as of the displayed date. Amounts use the ledger's base currency. Loading, unavailable data, and successful zero balances have separate UI states.

Account codes and system-account flags are backend-controlled. When creating accounts, select a compatible core type, account type, and detail type. Do not submit generated codes or attempt to edit locked system accounts. Existing accounting validation and posting rules remain in the module's request and service classes.

Report pages may read company metadata through `GET /api/v1/settings/company` with `accounting.read` for report headers. This does not enable company settings editing or bring back its settings page.

## API discovery

Run from `accountingsystem-backend`:

```bash
php artisan route:list --path=api
```

Main route families include accounts and account-type lookups, journal entries, taxes, fund sources, journal numbering, fiscal periods, financial reports, roles, and current-company access. Check module route files for exact methods and paths instead of maintaining a second static endpoint catalog.

Authenticated API requests require a valid session; a bare curl request to protected endpoints is expected to return 401. A registered route can also return 403 because its feature or ability is disabled.

## Verification

From `accountingsystem-backend`:

```bash
php artisan test --filter='ModuleAvailabilityTest|CurrentTenantAccessApiTest|AccountingDashboardApiTest'
php artisan test --filter='ReLoginResilienceTest|AuthenticationArchitectureTest|LoginTenantAccessTest'
```

From `accountingsystem-frontend`:

```bash
pnpm exec next typegen
pnpm exec tsc --noEmit
pnpm lint
pnpm exec tsx --test tests/auth/session-recovery.test.ts
pnpm build
```

The full backend suite remains available with `php artisan test`. Some legacy tests target disabled ERP workflows; interpret those against the current feature profile. Focused checks are not a claim that the entire legacy suite passes.

For a browser smoke test, start both servers, sign in, open the dashboard and accounting reports, then verify Roles & Permissions with an authorized user. Confirm removed URLs cannot open their former workspaces. Check report amounts against posted journals, including an empty company and a user with restricted access.

## Deployment

Install backend production dependencies with `composer install --no-dev --optimize-autoloader`. Configure production environment values, set `APP_DEBUG=false`, preserve the application key, and back up the database before running `php artisan migrate --force`. Do not run development seeders against production. Serve Laravel through its `public/` directory and ensure storage/cache directories are writable. Run `php artisan storage:link` when serving public uploads.

Build the frontend with production API URLs using `pnpm build`, then run `pnpm start` under a process manager. Configure HTTPS, CORS, Sanctum domains, and secure session cookies for the actual deployment domains. Back up the database and uploaded files.

Before distributing the project as open source, confirm that the retained commercial frontend template and third-party assets permit the intended redistribution. The root MIT license is preserved and does not supersede those terms.

## Troubleshooting

### Frontend starts but fails compiling Tailwind

The frontend's `next.config.ts` sets `turbopack.root` to the frontend directory. Start from `accountingsystem-frontend`, where dependencies are installed. If a previous build cache still resolves imports from the repository root, stop the frontend server, then run:

```bash
pnpm clean
pnpm dev
```

`pnpm clean` removes generated `.next` output only. Do not clear that directory while the server is running.

### Missing API URL or sign-in failures

Check both API URL variables in `.env.local` and restart Next.js after changing them. Confirm Laravel is running, the session cookie name matches, and the frontend origin appears in the backend CORS/Sanctum configuration. See the authentication guide before clearing sessions.

### Port 3001 is already in use

An existing frontend process may already be running. Use that instance or stop it in its terminal before starting another. `pnpm start` needs a successful production build; use `pnpm dev` for development.

### Missing route types after removing pages

Stop the development server and clear its generated cache, then run `pnpm exec next typegen` and the TypeScript check again. Do not restore removed features just to satisfy stale generated route imports.

### Permission denied

Check the company's effective abilities and feature availability. Owners cannot bypass disabled modules. Dashboard access requires `accounting.read`; role administration requires the corresponding `roles.*` ability.

## Screenshot demo data

`php artisan accounting:screenshot-data <company-uuid> --date=YYYY-MM-DD` adds six months of balanced, fictional activity to an empty company. Run it from the backend directory. Production-configured databases require explicit approval and `--allow-production`.

Demo journals use the `DEMO` series, `SCREENSHOT-…` references, and `[Demo]` notes. The command preserves existing accounts, refuses to mix initial demo data with existing journals, and avoids duplicating its dataset on reruns. Where the dataset spans a configured fiscal-year boundary, it closes prior-year demo earnings into retained earnings. It is an optional screenshot tool and is never part of normal database seeding.
