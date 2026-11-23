# Dakila Accounting Frontend

Next.js 16 and React 19 using the existing Sneat/MUI template, TanStack Query, and feature-based folders.

Follow the [root setup guide](../README.md#local-setup) for installation. The [developer guide](../docs/PROJECT_DOCUMENTATION.md) explains routing, permissions, and troubleshooting.

From this directory:

```bash
pnpm dev                       # Development server on port 3001
pnpm exec tsc --noEmit          # Type checking
pnpm lint                      # Linting
pnpm exec tsx --test tests/auth/session-recovery.test.ts
pnpm build                     # Production build
pnpm start                     # Serve a completed production build
```

Both `NEXT_PUBLIC_LARAVEL_API_URL` and `LARAVEL_API_URL` are required. Copy `.env.example` to `.env.local` and adapt the URLs to your environment. Never put secrets in `NEXT_PUBLIC_*` variables.

`next.config.ts` pins the Turbopack root to this directory so CSS and dependencies resolve from the frontend installation. See the developer guide if a stale `.next` cache prevents compilation.

The template's commercial licensing is separate from the repository's MIT license; see the [root license notes](../README.md#license-and-credits).
