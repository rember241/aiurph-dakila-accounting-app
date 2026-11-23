import type {
  AccountTransaction,
  CoreType
} from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'

// Type for the query parameters to generate the trial balance report
export type GenerateTrialBalanceQuery = {
  date: string
}

// Query parameters for downloading the trial balance report
export type DownloadTrialBalanceQuery = GenerateTrialBalanceQuery & { fileType: 'pdf' | 'csv' | 'xlsx' }

// Type for each account in the Trial Balance table
export type TrialBalanceAccount = {
  id: string
  code: string
  name: string
  type: CoreType
  isContra: boolean
  normalBalance: 'debit' | 'credit'
  debit: number
  credit: number
  transactions: AccountTransaction[]
}
