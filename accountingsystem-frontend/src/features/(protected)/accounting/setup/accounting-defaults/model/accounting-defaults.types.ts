export type AccountingDefaultsAccountRef = {
  id: string
  code: string
  name: string
}

export type AccountingDefaults = {
  accounts_receivable_account_id: string | null
  accounts_receivable_account: AccountingDefaultsAccountRef | null
  sales_income_account_id: string | null
  sales_income_account: AccountingDefaultsAccountRef | null
  inventory_asset_account_id: string | null
  inventory_asset_account: AccountingDefaultsAccountRef | null
  cogs_account_id: string | null
  cogs_account: AccountingDefaultsAccountRef | null
  sales_tax_payable_account_id: string | null
  sales_tax_payable_account: AccountingDefaultsAccountRef | null
  updated_at: string | null
}

export type UpdateAccountingDefaultsPayload = {
  accounts_receivable_account_id?: string | null
  sales_income_account_id?: string | null
  inventory_asset_account_id?: string | null
  cogs_account_id?: string | null
  sales_tax_payable_account_id?: string | null
}
