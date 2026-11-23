import { useQuery } from '@tanstack/react-query'

import type { GeneralLedgerAccountQuery } from '@/features/(protected)/accounting/books/general-ledger/model/general-ledger.types'
import { fetchGeneralLedgerAccounts } from '@/features/(protected)/accounting/books/general-ledger/api/general-ledger.api'

/**
 * Custom hook to fetch account data for the general ledger
 * account page based on the provided date range parameters as
 * well as the IDs of the accounts to be displayed in the table
 *
 * @param {GeneralLedgerAccountQuery} query The date range parameters as well as the account IDs
 */
export function useGeneralLedgerAccountsQuery(query: GeneralLedgerAccountQuery) {
  return useQuery({
    queryKey: ['generalLedger', 'accounts', query],
    queryFn: () => fetchGeneralLedgerAccounts(query),
    placeholderData: previousData => previousData, // Prevents table flickering when fetching data
    staleTime: 10_000,
    enabled: query.accountIds.length > 0 && query.startDate <= query.endDate
  })
}
