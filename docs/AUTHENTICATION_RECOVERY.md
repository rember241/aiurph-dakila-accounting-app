# Authentication and recovery

Authentication uses Laravel Sanctum browser sessions and CSRF cookies. Session identity is handled separately from temporary server availability. IdentityAccess owns the login, logout, and current-user routes. The legacy route file must not register a second `/api/me` handler.

Browser API calls use the same-origin `/api/laravel` proxy, including `/sanctum/csrf-cookie`. Next.js forwards these calls to the configured `LARAVEL_API_URL`, preserving session cookies and CSRF headers. Set the backend session cookie domain to host-only for local installations. The browser must allow site cookies; if bootstrap succeeds without storing `XSRF-TOKEN`, login reports that condition before sending credentials. CSRF validation remains enabled on the backend.

- Network errors and server errors preserve browser cookies, cached user data, and the mounted page. They do not invoke logout or reload the page.
- A 401 triggers session verification. The browser returns to sign-in when Laravel confirms expiry. A 419 refreshes CSRF once and retries the rejected request once. Concurrent failures share one CSRF bootstrap. Network failures and 5xx never replay writes automatically.
- After a connection error, the frontend checks `/api/me` automatically while the page is visible and when the user returns to the tab or comes back online. A successful check refreshes failed queries. The connection notice also provides a manual check. Failed writes must be checked and resubmitted by the user because the server may have committed a write before the connection failed.
- Server-rendered auth checks return unauthenticated only for a missing session cookie or a 401. Outages, timeouts, and malformed responses reach the retry boundary rather than redirecting to login or replacing permissions with an empty snapshot.
- Authenticated responses include the Laravel session deadline. The warning appears during the last 20% of the configured idle lifetime, capped at two minutes. **Extend session** renews the active browser session through a CSRF-protected POST. At zero, the app verifies the current session and signs out if it has expired. If the backend is unreachable, it keeps the page open and checks again once connectivity returns.
- The sidebar refreshes stale permissions when the tab regains focus. Company access comes from the authenticated session. A rejected session sends protected navigation to sign-in.
- Successful login rotates the session and opens the workspace on the same browser origin that received the cookie. Failed credentials do not log out an already authenticated browser. Login requires CSRF protection. Explicit logout still invalidates the backend session.

Restart persistence depends on the configured session store. Keep `SESSION_DRIVER=database` (or another durable shared store), retain the sessions table and stable `APP_KEY` across deployments, and use consistent cookie domain, secure-cookie, and Sanctum stateful-domain settings. Normal idle session expiry still applies; an outage longer than the session lifetime can require signing in again. The application cannot preserve authentication after session records or encryption keys are deleted.

## Checks

From `accountingsystem-frontend`: `pnpm exec tsx --test tests/auth/session-recovery.test.ts`, `pnpm exec tsc --noEmit`, and ESLint on changed files.

From `accountingsystem-backend`: `php artisan test --filter='ReLoginResilienceTest|AuthenticationArchitectureTest|LoginTenantAccessTest'`.

For deployment verification, open a form, stop the backend, issue a read, and confirm the form remains open with a connection notice. Restart the backend with the same session store and key, then use Try again. Also verify actual session expiry prompts sign-in and explicit logout revokes access. These live restart checks are separate from the automated adapter and backend feature tests.
