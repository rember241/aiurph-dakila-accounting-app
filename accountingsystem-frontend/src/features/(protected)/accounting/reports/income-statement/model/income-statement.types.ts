import type { AccountTransaction } from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'

// Type for the query parameters to generate the income statement report
export type GenerateIncomeStatementQuery = {
  startDate: string
  endDate: string
  accountIds: string[]
  compareWith: 'period' | 'fiscalYear' | 'specific' | ''
  numberOfPeriods: number
  compareStartDate: string
  compareEndDate: string
}

// Query parameters for downloading the income statement report
export type DownloadIncomeStatementQuery = GenerateIncomeStatementQuery & { fileType: 'pdf' | 'csv' | 'xlsx' }

// Type for each account in the income statement table
export type IncomeStatementAccount = {
  id: string
  code: string
  name: string
  type: 'revenue' | 'expense'
  normalBalance: 'debit' | 'credit'
  isContra: boolean
  balance: number
  transactions: AccountTransaction[]
  previousBalances?: number[]
}

// Shape of the API response when generating the income statement report
export type GenerateIncomeStatementResponse = {
  labels: string[] // Date labels on the report header
  accounts: IncomeStatementAccount[]
  totalRevenue: number[]
  totalExpense: number[]
  netTotal: number[]
}
