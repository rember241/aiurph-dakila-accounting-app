import { APP_ABILITIES } from '@/features/auth/access/model/access.schema'

import type { NavSearchEntry } from '@/features/navigation/model/nav-search.types'

/**
 * Static catalog of every navigable page in the application.
 *
 * Rules:
 *  - `id`          — kebab-case, unique, stable across deployments
 *  - `description` — one sentence, present tense, explains *what the page does*
 *  - `keywords`    — short aliases and synonyms that help users find the page
 *  - `requiredAbilities` — mirrors the `requiredAbilities` in verticalMenuData
 */
export const NAV_SEARCH_CATALOG: NavSearchEntry[] = [
  {
    id: 'dashboard',
    label: 'Dashboard',
    description: 'Accounting overview and financial position.',
    href: '/home',
    icon: 'bx-home-circle',
    section: 'Accounting',
    keywords: ['dashboard', 'home'],
    requiredAbilities: [APP_ABILITIES.accountingRead]
  },
  {
    id: 'roles',
    label: 'Roles & Permissions',
    description: 'Manage company role permissions.',
    href: '/users/roles',
    icon: 'bx-shield',
    section: 'Users',
    keywords: ['roles', 'permissions'],
    requiredAbilities: [APP_ABILITIES.rolesRead]
  },

  // ─── Accounting — Setup ─────────────────────────────────────────────────────
  {
    id: 'accounting-chart-of-accounts',
    label: 'Chart of Accounts',
    description: 'Configure the general ledger account hierarchy used across all transactions.',
    href: '/accounting/setup/accounts',
    icon: 'bx-book',
    section: 'Accounting',
    keywords: ['coa', 'accounts', 'ledger accounts', 'gl', 'account code'],
    requiredAbilities: [APP_ABILITIES.accountingRead]
  },
  {
    id: 'accounting-transaction-series',
    label: 'Transaction Series',
    description: 'Define journal number series for each transaction type to control document numbering.',
    href: '/accounting/setup/transaction-series',
    icon: 'bx-hash',
    section: 'Accounting',
    keywords: ['series', 'numbering', 'sequence', 'journal number', 'prefix'],
    requiredAbilities: [APP_ABILITIES.accountingRead, APP_ABILITIES.journalRead]
  },
  {
    id: 'accounting-fund-sources',
    label: 'Fund Sources',
    description: 'Register bank accounts and cash fund sources used in payment and journal transactions.',
    href: '/accounting/setup/fund-sources',
    icon: 'bx-wallet-alt',
    section: 'Accounting',
    keywords: ['bank', 'cash', 'fund', 'account', 'petty cash', 'bank account'],
    requiredAbilities: [APP_ABILITIES.accountingRead, APP_ABILITIES.journalRead]
  },
  {
    id: 'accounting-taxes',
    label: 'Taxes',
    description: 'Configure tax rates and codes applied to sales and purchase transactions.',
    href: '/accounting/setup/taxes',
    icon: 'bx-receipt',
    section: 'Accounting',
    keywords: ['tax', 'vat', 'gst', 'rate', 'withholding', 'percentage'],
    requiredAbilities: [APP_ABILITIES.accountingRead, APP_ABILITIES.journalRead]
  },
  {
    id: 'accounting-period-management',
    label: 'Period Management',
    description: 'Open, lock, and manage accounting periods and fiscal years.',
    href: '/accounting/setup/periods',
    icon: 'bx-calendar',
    section: 'Accounting',
    keywords: ['period', 'fiscal', 'month', 'year', 'lock', 'open', 'close'],
    requiredAbilities: [APP_ABILITIES.periodRead]
  },

  // ─── Accounting — Transactions ───────────────────────────────────────────────
  {
    id: 'accounting-journal-entries',
    label: 'Journal Entries',
    description: 'Record manual double-entry journal transactions in the general ledger.',
    href: '/accounting/transactions/journal-entries',
    icon: 'bx-book',
    section: 'Accounting',
    keywords: ['journal', 'entry', 'debit', 'credit', 'double entry', 'manual entry'],
    requiredAbilities: [APP_ABILITIES.journalRead]
  },

  // ─── Accounting — Books ──────────────────────────────────────────────────────
  {
    id: 'accounting-general-ledger',
    label: 'General Ledger',
    description: 'Browse the detailed transaction history of every account in the chart of accounts.',
    href: '/accounting/books/ledger',
    icon: 'bx-layer',
    section: 'Accounting',
    keywords: ['ledger', 'gl', 'transactions', 'history', 'account activity'],
    requiredAbilities: [APP_ABILITIES.accountingRead]
  },

  // ─── Accounting — Reports ─────────────────────────────────────────────────────
  {
    id: 'accounting-trial-balance',
    label: 'Trial Balance',
    description: 'Review the period-end debit and credit balance for every account.',
    href: '/accounting/reports/trial-balance',
    icon: 'bx-spreadsheet',
    section: 'Accounting',
    keywords: ['trial balance', 'debit credit', 'balance', 'period end'],
    requiredAbilities: [APP_ABILITIES.accountingRead]
  },
  {
    id: 'accounting-income-statement',
    label: 'Income Statement',
    description: 'View profit and loss performance over a selected date range.',
    href: '/accounting/reports/income-statement',
    icon: 'bx-line-chart',
    section: 'Accounting',
    keywords: ['income', 'profit', 'loss', 'p&l', 'revenue', 'expenses', 'pnl'],
    requiredAbilities: [APP_ABILITIES.accountingRead]
  },
  {
    id: 'accounting-balance-sheet',
    label: 'Balance Sheet',
    description: 'View assets, liabilities, and equity positions at a specific point in time.',
    href: '/accounting/reports/balance-sheet',
    icon: 'bx-columns',
    section: 'Accounting',
    keywords: ['balance sheet', 'assets', 'liabilities', 'equity', 'net worth'],
    requiredAbilities: [APP_ABILITIES.accountingRead]
  },
  {
    id: 'accounting-cash-flow',
    label: 'Cash Flow',
    description: 'Analyse cash inflows and outflows categorised by operating, investing, and financing.',
    href: '/accounting/reports/cash-flow',
    icon: 'bx-trending-up',
    section: 'Accounting',
    keywords: ['cash flow', 'liquidity', 'cash in', 'cash out', 'operating'],
    requiredAbilities: [APP_ABILITIES.accountingRead]
  },

  // ─── Accounting — Closing ─────────────────────────────────────────────────────
  {
    id: 'accounting-period-close',
    label: 'Period Close',
    description: 'Execute month-end closing procedures to lock the period and freeze balances.',
    href: '/accounting/closing/period',
    icon: 'bx-lock-alt',
    section: 'Accounting',
    keywords: ['period close', 'month end', 'closing', 'lock period'],
    requiredAbilities: [APP_ABILITIES.periodRead]
  },
  {
    id: 'accounting-year-end-close',
    label: 'Year-End Close',
    description: 'Execute year-end closing and carry forward retained earnings to the new fiscal year.',
    href: '/accounting/closing/year-end',
    icon: 'bx-flag',
    section: 'Accounting',
    keywords: ['year end', 'annual close', 'retained earnings', 'carry forward'],
    requiredAbilities: [APP_ABILITIES.periodRead]
  }
]
