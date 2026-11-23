// Type for querying data in the Cash Flow page
export type CashFlowQuery = {
  startDate: string
  endDate: string
  compareEnabled?: boolean
  comparisonMode?: 'previous_period' | 'previous_year' | 'custom'
  comparisonStartDate?: string
  comparisonEndDate?: string
}

// Represents a single journal entry line contributing to a cash flow item
export type CashFlowTransaction = {
  id: string
  journalEntryId: string
  journalNumber: string
  date: string
  description: string
  amount: number
}

// Represents an item/row in the cash flow statement
export type CashFlowItem = {
  accountId?: string
  accountName: string
  total: number
  type: 'operating' | 'investing' | 'financing'
  transactions?: CashFlowTransaction[]
}

export type CashFlowUnmappedTransaction = {
  journalEntryId: string
  journalNumber: string
  entryDate: string
  referenceNumber?: string | null
  amount: number
  reason: string
  accounts: Array<{
    accountId: string
    accountName: string
    lineType: 'debit' | 'credit'
    amount: number
  }>
}

export type CashFlowUnmappedAccount = {
  accountId: string
  accountName: string
  accountType: string
  accountTypeCode?: string | null
  detailTypeCode?: string | null
}

export type CashFlowPeriod = {
  startDate: string
  endDate: string
  mode?: 'previous_period' | 'previous_year' | 'custom'
}

export type CashFlowComparisonLine = {
  accountId?: string | null
  accountName: string
  currentAmount: number
  previousAmount: number | null
  varianceAmount: number | null
  variancePercent: number | null
  transactions?: CashFlowTransaction[]
}

export type CashFlowSection = {
  key: 'operating' | 'investing' | 'financing'
  label: string
  lines: CashFlowComparisonLine[]
  currentTotal: number
  previousTotal: number | null
  varianceAmount: number | null
  variancePercent: number | null
}

export type CashFlowSummary = {
  openingCashCurrent: number
  netCashFlowCurrent: number
  endingCashCurrent: number
  openingCashPrevious: number | null
  netCashFlowPrevious: number | null
  endingCashPrevious: number | null
  reconciliationDifferenceCurrent?: number
  reconciliationDifferencePrevious?: number | null
  netCashFlowVariance: number | null
  endingCashVariance: number | null
}

// Type for the API response from the cash flow API
export type CashFlowData = {
  openingBalance: number
  endingBalance: number
  operatingTotal: number
  investingTotal: number
  financingTotal: number
  netChange: number
  items: CashFlowItem[]
  unmappedTransactions?: CashFlowUnmappedTransaction[]
  unmappedAccounts?: CashFlowUnmappedAccount[]
  warnings?: string[]
  reconciliationDifference?: number
  period?: CashFlowPeriod
  comparisonPeriod?: CashFlowPeriod | null
  comparison?: CashFlowData | null
  sections?: CashFlowSection[]
  summary?: CashFlowSummary
  basis?: 'direct_cash_account_movement'
}
