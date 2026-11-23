import {
  useAccountStatsQuery,
  useCreateAccountMutation,
  useDeleteAccountMutation,
  useUpdateAccountMutation
} from '@/features/(protected)/accounting/setup/chart-of-accounts/api/accounts.queries'

export function useAccountsController() {
  const create = useCreateAccountMutation()
  const update = useUpdateAccountMutation()
  const remove = useDeleteAccountMutation()
  const stats = useAccountStatsQuery()

  return { create, update, remove, stats }
}
