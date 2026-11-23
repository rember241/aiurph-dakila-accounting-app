import { laravelBrowserClient } from '@/lib/axios/auth/laravel-browser-client'

import type {
  TrialBalanceAccount,
  GenerateTrialBalanceQuery,
  DownloadTrialBalanceQuery
} from '@/features/(protected)/accounting/reports/trial-balance/model/trial-balance.types'

// Accepted types for the toQueryString function
type QueryTypes = GenerateTrialBalanceQuery | DownloadTrialBalanceQuery

// Map for changing the accept header in the downloadTrialBalance function based on the entered file type
const acceptHeader = new Map([
  ['pdf', 'application/pdf'],
  ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
  ['csv', 'text/csv']
])

/**
 * Retrieves account data for the Trial Balance report from the backend server
 *
 * @param {GenerateTrialBalanceQuery} query Includes the date
 * @return {Promise<TrialBalanceAccount[]>} The account data to be displayed in the table
 */
export async function fetchTrialBalance(query: GenerateTrialBalanceQuery): Promise<TrialBalanceAccount[]> {
  const queryString = toQueryString(query)

  const response = await laravelBrowserClient.get(`/api/v1/trial-balance?${queryString}`)

  return response.data as TrialBalanceAccount[]
}

export async function downloadTrialBalance(query: DownloadTrialBalanceQuery): Promise<Blob> {
  const queryString = toQueryString(query)

  const response = await laravelBrowserClient.get(`/api/v1/trial-balance/download?${queryString}`, {
    headers: {
      Accept: acceptHeader.get(query.fileType)
    },
    responseType: 'blob'
  })

  return response.data as Blob
}

/**
 * Parses the fields from the Trial Balance
 * query into a URL-encoded string
 *
 * @param query
 */
function toQueryString(query: QueryTypes): string {
  const queryParameters = new URLSearchParams()

  queryParameters.set('date', query.date)

  // Only parsed when trying to download the trial balance
  if ('fileType' in query) queryParameters.set('fileType', query.fileType)

  return queryParameters.toString()
}
