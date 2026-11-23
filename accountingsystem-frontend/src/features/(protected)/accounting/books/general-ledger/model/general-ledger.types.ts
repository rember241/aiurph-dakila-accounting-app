import type {
  AccountTransaction,
  CoreType
} from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'

// Type for querying accounts in the general ledger accounts page
export type GeneralLedgerAccountQuery = {
  startDate: string
  endDate: string
  accountIds: string[]
}

// Type for the accounts table in the general ledger page
export type GeneralLedgerAccount = {
  id: string
  code: string
  name: string
  type: CoreType // Account core types
  normalBalance: 'debit' | 'credit'
  openingBalance: number
  debit: number
  credit: number
  balance: number
  transactions: AccountTransaction[]
}
