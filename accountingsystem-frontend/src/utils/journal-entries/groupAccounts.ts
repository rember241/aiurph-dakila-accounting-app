import type {
  AccountGroup,
  AccountTreeNode
} from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'
import {
  compareAccountsByCoreTypeAndCode,
  getAccountCoreTypeLabel
} from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'

/**
 * Grabs all posting accounts and groups them under canonical accounting
 * categories: Assets, Liabilities, Equity, Revenue, Expenses.
 *
 * @param data Accounts data
 */
export function groupAccounts(data: AccountTreeNode[]): AccountGroup[] {
  const flattenNode = (node: AccountTreeNode): AccountGroup[] => {
    const { children, ...account } = node
    const current = account.is_posting ? [{ ...account, coreType: getAccountCoreTypeLabel(account.type) }] : []

    return [...current, ...children.flatMap(flattenNode)]
  }

  return data.flatMap(flattenNode).sort(compareAccountsByCoreTypeAndCode)
}
