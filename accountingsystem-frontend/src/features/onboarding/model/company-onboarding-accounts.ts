export const STARTER_ACCOUNTS_PREVIEW_GROUPS = [
  {
    id: 'assets',
    title: 'Assets',
    description: 'Cash, sales receivable, inventory, fixed assets, prepaids, deposits, and investment categories.',
    accounts: ['Cash', 'Sales Receivable', 'Stock In Hand', 'Employee Advances', 'Prepaid Expenses', 'Software']
  },
  {
    id: 'liabilities-equity',
    title: 'Liabilities and equity',
    description: 'Payables, taxes, loans, capital, retained earnings, and other balance sheet structure.',
    accounts: ['Creditors', 'Payroll Payable', 'Sales Tax Payable', 'VAT Payable', 'Capital Stock', 'Retained Earnings']
  },
  {
    id: 'income-expenses',
    title: 'Income and expenses',
    description: 'Sales, services, cost of goods sold, and a broad indirect expense structure.',
    accounts: ['Sales', 'Service', 'Cost of Goods Sold', 'Administrative Expenses', 'Salary', 'Bank Charges']
  }
] as const

export const STARTER_ACCOUNTS_PREVIEW_COUNT = 101

export function getAccountsSetupModeLabel(mode: 'generated' | 'empty') {
  return mode === 'generated' ? 'Starter chart of accounts' : 'Starter chart setup'
}

export function getAccountsSetupModeDescription(mode: 'generated' | 'empty') {
  return mode === 'generated'
    ? `${STARTER_ACCOUNTS_PREVIEW_COUNT} chart-of-accounts records, including group and posting accounts, will be created for you.`
    : 'Starter chart setup is the standard company setup path.'
}
