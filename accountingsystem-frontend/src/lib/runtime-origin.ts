import { headers } from 'next/headers'

export function originFromRequestHeaders(requestHeaders: Headers): string {
  const proto = requestHeaders.get('x-forwarded-proto') ?? 'http'
  const host = requestHeaders.get('x-forwarded-host') ?? requestHeaders.get('host') ?? 'localhost:3001'

  return `${proto}://${host}`
}

export async function currentRuntimeOrigin(): Promise<string> {
  const requestHeaders = await headers()

  return originFromRequestHeaders(requestHeaders)
}
