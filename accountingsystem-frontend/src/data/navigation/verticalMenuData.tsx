import type { VerticalMenuDataType } from '@/types/menuTypes'
import { APP_ABILITIES } from '@/features/auth/access/model/access.schema'

// Keep everyday tasks at the top level so collapsed navigation remains useful.
const verticalMenuData = (): VerticalMenuDataType[] => [
  { label: 'Dashboard', href: '/home', icon: 'bx-home-circle', requiredAbilities: [APP_ABILITIES.accountingRead] },
  { label: 'Journal Entries', href: '/accounting/transactions/journal-entries', icon: 'bx-edit', exactMatch: false, activeUrl: '/accounting/transactions/journal-entries', requiredAbilities: [APP_ABILITIES.journalRead] },
  { label: 'General Ledger', href: '/accounting/books/ledger', icon: 'bx-book-open', requiredAbilities: [APP_ABILITIES.accountingRead] },
  { label: 'Chart of Accounts', href: '/accounting/setup/accounts', icon: 'bx-list-ul', requiredAbilities: [APP_ABILITIES.accountingRead] },
  {
    label: 'Financial Reports', icon: 'bx-bar-chart-alt-2', children: [
      { label: 'Trial Balance', href: '/accounting/reports/trial-balance', icon: 'bx-spreadsheet', requiredAbilities: [APP_ABILITIES.accountingRead] },
      { label: 'Income Statement', href: '/accounting/reports/income-statement', icon: 'bx-line-chart', requiredAbilities: [APP_ABILITIES.accountingRead] },
      { label: 'Balance Sheet', href: '/accounting/reports/balance-sheet', icon: 'bx-columns', requiredAbilities: [APP_ABILITIES.accountingRead] },
      { label: 'Cash Flow', href: '/accounting/reports/cash-flow', icon: 'bx-trending-up', requiredAbilities: [APP_ABILITIES.accountingRead] }
    ]
  },
  { label: 'Accounting Periods', href: '/accounting/setup/periods', icon: 'bx-calendar', requiredAbilities: [APP_ABILITIES.periodRead] },
  {
    label: 'Accounting Setup', icon: 'bx-slider-alt', children: [
      { label: 'Transaction Series', href: '/accounting/setup/transaction-series', icon: 'bx-hash', requiredAbilities: [APP_ABILITIES.accountingRead, APP_ABILITIES.journalRead] },
      { label: 'Fund Sources', href: '/accounting/setup/fund-sources', icon: 'bx-wallet-alt', requiredAbilities: [APP_ABILITIES.accountingRead, APP_ABILITIES.journalRead] },
      { label: 'Taxes', href: '/accounting/setup/taxes', icon: 'bx-receipt', requiredAbilities: [APP_ABILITIES.accountingRead, APP_ABILITIES.journalRead] }
    ]
  },
  { label: 'Roles & Permissions', href: '/users/roles', icon: 'bx-shield', requiredAbilities: [APP_ABILITIES.rolesRead] }
]

export default verticalMenuData
