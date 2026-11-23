/**
 * Whether auth/session cookies should carry the `Secure` attribute.
 *
 * This must track the actual transport, NOT `NODE_ENV`. The ERP is deployed
 * over plain HTTP on a LAN address while running in production mode, and
 * browsers silently DROP any `Set-Cookie` that carries `Secure` when the
 * connection is not HTTPS. Keying `Secure` off `NODE_ENV === 'production'`
 * therefore meant logout could never delete `aiur_laravel_session` / the
 * XSRF cookie over HTTP, so the session survived logout and the back button
 * (and blocked the next login).
 *
 * Deriving it from the public app URL scheme keeps cookies working on the
 * current HTTP deployment and automatically re-enables `Secure` once the app
 * is served over HTTPS.
 */
export function cookiesAreSecure(): boolean {
  const appUrl = process.env.NEXT_PUBLIC_APP_URL ?? process.env.NEXT_PUBLIC_LARAVEL_API_URL ?? ''

  return appUrl.startsWith('https://')
}
