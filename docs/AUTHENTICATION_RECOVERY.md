# Authentication and recovery

Authentication uses Laravel Sanctum browser sessions and CSRF cookies. Session identity is handled separately from temporary server availability. IdentityAccess owns the login, logout, and current-user routes. The legacy route file must not register a second `/api/me` handler.

Browser API calls use the same-origin `/api/laravel` proxy, including `/sanctum/csrf-cookie`. Next.js forwards these calls to the configured `LARAVEL_API_URL`, preserving session cookies and CSRF headers. Set the backend session cookie domain to host-only for local installations. The browser must allow site cookies; if bootstrap succeeds without storing `XSRF-TOKEN`, login reports that condition before sending credentials. CSRF validation remains enabled on the backend.

- Network errors and server errors preserve browser cookies, cached user data, and the mounted page. They do not invoke logout or reload the page.
- A 401 displays a sign-in notice. A 419 refreshes CSRF once and retries the rejected request once. Concurrent failures share one CSRF bootstrap. Network failures and 5xx never replay writes automatically.
- The connection notice lets the user check `/api/me` and retry failed queries. Failed writes must be checked and resubmitted by the user because the server may have committed a write before the connection failed.
- Server-rendered auth checks return unauthenticated only for a missing session cookie or a 401. Outages, timeouts, and malformed responses reach the retry boundary rather than redirecting to login or replacing permissions with an empty snapshot.
- Successful login rotates the session. Failed credentials do not log out an already authenticated browser. Login requires CSRF protection. Explicit logout still invalidates the backend session.

Restart persistence depends on the configured session store. Keep `SESSION_DRIVER=database` (or another durable shared store), retain the sessions table and stable `APP_KEY` across deployments, and use consistent cookie domain, secure-cookie, and Sanctum stateful-domain settings. Normal idle session expiry still applies; an outage longer than the session lifetime can require signing in again. The application cannot preserve authentication after session records or encryption keys are deleted.

## Checks

From `accountingsystem-frontend`: `pnpm exec tsx --test tests/auth/session-recovery.test.ts`, `pnpm exec tsc --noEmit`, and ESLint on changed files.

From `accountingsystem-backend`: `php artisan test --filter='ReLoginResilienceTest|AuthenticationArchitectureTest|LoginTenantAccessTest'`.

For deployment verification, open a form, stop the backend, issue a read, and confirm the form remains open with a connection notice. Restart the backend with the same session store and key, then use Try again. Also verify actual session expiry prompts sign-in and explicit logout revokes access. These live restart checks are separate from the automated adapter and backend feature tests.
