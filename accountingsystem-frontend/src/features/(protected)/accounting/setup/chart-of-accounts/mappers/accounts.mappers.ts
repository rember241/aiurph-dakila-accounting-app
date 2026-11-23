import type { AccountColumn } from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'
import type {
  AccountCreateFormValues,
  AccountCreateSubmitValues
} from '@/features/(protected)/accounting/setup/chart-of-accounts/model/account.schema'

export type AccountUpsertPayload = {
  name: string
  type: AccountCreateSubmitValues['type']
  account_type_id: AccountCreateSubmitValues['account_type_id']
  detail_type_id: AccountCreateSubmitValues['detail_type_id']
  parent_id: string | null
  is_posting: boolean
  status: AccountCreateSubmitValues['status']
  contra_account_id?: string | null
  description?: string | null
}

export function toPayload(values: AccountCreateSubmitValues, existing?: AccountColumn): AccountUpsertPayload {
  return {
    name: values.name,
    type: values.type,
    account_type_id: values.account_type_id,
    detail_type_id: values.detail_type_id,
    parent_id: values.parent_id ?? existing?.parent_id ?? null,
    is_posting: Boolean(values.is_posting),
    status: values.status,
    contra_account_id: values.contra_account_id ?? null,
    description: values.description ?? null
  }
}

export function toDefaultValues(account?: AccountColumn): Partial<AccountCreateFormValues> | undefined {
  if (!account) return undefined

  return {
    name: account.name,
    type: account.type,
    account_type_id: account.account_type_id ?? null,
    detail_type_id: account.detail_type_id ?? null,
    parent_id: account.parent_id ?? null,
    status: account.status,
    is_posting: account.is_posting,

    /* TODO: fix contra account boolean.
     */
    // contra_account_id: account.contra_account_id ?? null,
    description: account.description ?? null
  }
}
