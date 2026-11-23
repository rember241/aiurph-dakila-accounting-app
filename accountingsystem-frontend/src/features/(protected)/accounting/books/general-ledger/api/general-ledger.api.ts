import { laravelBrowserClient } from '@/lib/axios/auth/laravel-browser-client'

import type {
  GeneralLedgerAccount,
  GeneralLedgerAccountQuery
} from '@/features/(protected)/accounting/books/general-ledger/model/general-ledger.types'

/**
 * Retrieves account data for the general ledger account page from the backend server
 *
 * @param {GeneralLedgerAccountQuery} query Includes the date ranges and account IDs
 * @return {Promise<GeneralLedgerAccount[]>} The account data to be displayed in the table
 */
export async function fetchGeneralLedgerAccounts(query: GeneralLedgerAccountQuery): Promise<GeneralLedgerAccount[]> {
  const queryString = toQueryString(query)

  const response = await laravelBrowserClient.get(`/api/v1/general-ledger/accounts?${queryString}`)

  return response.data as GeneralLedgerAccount[]
}

/**
 * Parses the fields from the GeneralLedgerAccountQuery
 * into a URL-encoded query string.
 *
 * @param query
 */
function toQueryString(query: GeneralLedgerAccountQuery): string {
  const queryParameters = new URLSearchParams()

  queryParameters.set('startDate', query.startDate)
  queryParameters.set('endDate', query.endDate)

  query.accountIds.forEach(id => {
    queryParameters.append('accountIds[]', id)
  })

  return queryParameters.toString()
}
