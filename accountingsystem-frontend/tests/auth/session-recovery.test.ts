import assert from 'node:assert/strict'
import { before, test } from 'node:test'
import { AxiosError, AxiosHeaders, type InternalAxiosRequestConfig } from 'axios'

import { SESSION_STATUS_EVENT, type SessionIssue } from '../../src/features/auth/model/session-status'

process.env.NEXT_PUBLIC_LARAVEL_API_URL = 'http://localhost:8000'
let laravelBrowserClient: typeof import('../../src/lib/axios/auth/laravel-browser-client').laravelBrowserClient
before(async () => {
  Object.defineProperty(globalThis, 'window', { configurable: true, value: Object.assign(new EventTarget(), {
    location: { pathname: '/sales/invoices', replace: () => { throw new Error('Unexpected navigation') } }
  }) })
  Object.defineProperty(globalThis, 'document', { configurable: true, value: {
    get cookie() { return 'XSRF-TOKEN=test-token' },
    set cookie(_value: string) { throw new Error('Unexpected cookie deletion') }
  } })
  ;({ laravelBrowserClient } = await import('../../src/lib/axios/auth/laravel-browser-client'))
})

function reject(config: InternalAxiosRequestConfig, status?: number): never {
  throw new AxiosError('Request failed', undefined, config, undefined,
    status ? { status, statusText: 'Failed', data: {}, headers: {}, config } : undefined)
}

function ok(config: InternalAxiosRequestConfig) {
  return { status: 200, statusText: 'OK', data: {}, headers: new AxiosHeaders(), config }
}

test('network failures, 503, and 401 never retry mutations or navigate to logout', async () => {
  for (const status of [undefined, 503, 401]) {
    let calls = 0
    laravelBrowserClient.defaults.adapter = async config => { calls++; return reject(config, status) }
    await assert.rejects(laravelBrowserClient.post('/api/v1/sales-invoices', {}))
    assert.equal(calls, 1)
  }
})

test('a 419 refreshes CSRF once and replays the rejected request once', async () => {
  const paths: string[] = []
  laravelBrowserClient.defaults.adapter = async config => {
    paths.push(config.url!)
    if (paths.length === 1) return reject(config, 419)
    return ok(config)
  }
  await laravelBrowserClient.post('/api/v1/sales-invoices', {})
  assert.deepEqual(paths, ['/api/v1/sales-invoices', '/sanctum/csrf-cookie', '/api/v1/sales-invoices'])
})

test('persistent CSRF mismatch terminates without a redirect loop', async () => {
  let attempts = 0
  laravelBrowserClient.defaults.adapter = async config => {
    if (config.url === '/sanctum/csrf-cookie') return ok(config)
    attempts++
    return reject(config, 419)
  }
  await assert.rejects(laravelBrowserClient.post('/api/logout'))
  assert.equal(attempts, 2)
})

test('concurrent CSRF failures share one bootstrap request', async () => {
  let bootstrapCalls = 0
  const attempts = new Set<string>()
  laravelBrowserClient.defaults.adapter = async config => {
    if (config.url === '/sanctum/csrf-cookie') {
      bootstrapCalls++
      await new Promise(resolve => setTimeout(resolve, 10))
      return ok(config)
    }
    if (!attempts.has(config.url!)) {
      attempts.add(config.url!)
      return reject(config, 419)
    }
    return ok(config)
  }
  await Promise.all([laravelBrowserClient.post('/api/a'), laravelBrowserClient.post('/api/b')])
  assert.equal(bootstrapCalls, 1)
})

test('failed CSRF bootstrap can recover on the next attempt', async () => {
  laravelBrowserClient.defaults.adapter = async config => reject(config, config.url === '/sanctum/csrf-cookie' ? 503 : 419)
  await assert.rejects(laravelBrowserClient.post('/api/a'))
  const paths: string[] = []
  laravelBrowserClient.defaults.adapter = async config => {
    paths.push(config.url!)
    if (paths.length === 1) return reject(config, 419)
    return ok(config)
  }
  await laravelBrowserClient.post('/api/a')
  assert.equal(paths.length, 3)
})


test('a request succeeds after an outage without clearing the existing CSRF cookie', async () => {
  laravelBrowserClient.defaults.adapter = async config => reject(config)
  await assert.rejects(laravelBrowserClient.get('/api/me'))
  laravelBrowserClient.defaults.adapter = async config => {
    assert.equal(config.headers.get('X-XSRF-TOKEN'), 'test-token')
    return ok(config)
  }
  await laravelBrowserClient.get('/api/me')
})

test('login stops before sending credentials if the browser rejects the CSRF cookie', async () => {
  const originalDocument = Object.getOwnPropertyDescriptor(globalThis, 'document')!
  const paths: string[] = []

  Object.defineProperty(globalThis, 'document', { configurable: true, value: { cookie: '' } })

  laravelBrowserClient.defaults.adapter = async config => {
    paths.push(config.url!)

    return ok(config)
  }

  try {
    const { login } = await import('../../src/features/auth/api/auth.api')

    await assert.rejects(login({ email: 'aiur_admin@demo.com', password: 'admin1234' }), /did not store the sign-in cookie/)
    assert.deepEqual(paths, ['/sanctum/csrf-cookie'])
  } finally {
    Object.defineProperty(globalThis, 'document', originalDocument)
  }
})


test('a successful unrelated response does not clear an outage before the session is verified', async () => {
  const statuses: SessionIssue[] = []
  const listener = (event: Event) => statuses.push((event as CustomEvent<SessionIssue>).detail)

  window.addEventListener(SESSION_STATUS_EVENT, listener)

  try {
    laravelBrowserClient.defaults.adapter = async config => reject(config, 503)
    await assert.rejects(laravelBrowserClient.get('/api/me'))

    laravelBrowserClient.defaults.adapter = async config => ok(config)
    await laravelBrowserClient.get('/sanctum/csrf-cookie')

    assert.deepEqual(statuses, ['unavailable'])
  } finally {
    window.removeEventListener(SESSION_STATUS_EVENT, listener)
  }
})
