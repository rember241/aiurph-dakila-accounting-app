import type { AccountTransaction } from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'

// Type for the query parameters to generate the balance sheet report
export type GenerateBalanceSheetQuery = {
  date: string
  accountIds: string[]
  compareWith: 'period' | 'fiscalYear' | 'specific' | ''
  numberOfPeriods: number
  compareDate: string
}

// Query parameters for downloading the balance sheet report
export type DownloadBalanceSheetQuery = GenerateBalanceSheetQuery & { fileType: 'pdf' | 'csv' | 'xlsx' }

// Type for each account in the Balance Sheet table
export type BalanceSheetAccount = {
  id: string
  code: string
  name: string
  coreType: 'asset' | 'liability' | 'equity'
  accountType: string // Defines whether an account is a current or non-current asset/liability or just an equity
  isContra: boolean
  normalBalance: 'debit' | 'credit'
  balance: number
  transactions: AccountTransaction[]
  previousBalances?: number[]
}

export type GenerateBalanceSheetResponse = {
  labels: string[] // Date labels on the report header
  accounts: BalanceSheetAccount[]
  totalCurrentAssets: number[]
  totalNonCurrentAssets: number[]
  totalAssets: number[]
  totalCurrentLiabilities: number[]
  totalNonCurrentLiabilities: number[]
  totalLiabilities: number[]
  totalEquities: number[]
  totalLiabilitiesEquities: number[]
}
