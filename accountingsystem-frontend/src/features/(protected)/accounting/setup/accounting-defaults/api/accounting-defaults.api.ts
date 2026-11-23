import { laravelBrowserClient } from '@/lib/axios/auth/laravel-browser-client'

import type { AccountingDefaults, UpdateAccountingDefaultsPayload } from '../model/accounting-defaults.types'

export async function fetchAccountingDefaults(): Promise<{ data: AccountingDefaults }> {
  const res = await laravelBrowserClient.get('/api/v1/accounting/settings/defaults')

  return res.data
}

export async function updateAccountingDefaults(
  payload: UpdateAccountingDefaultsPayload
): Promise<{ data: AccountingDefaults; message: string }> {
  const res = await laravelBrowserClient.patch('/api/v1/accounting/settings/defaults', payload)

  return res.data
}
