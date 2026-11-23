import { laravelBrowserClient } from '@/lib/axios/auth/laravel-browser-client'

import type {
  DownloadBalanceSheetQuery,
  GenerateBalanceSheetQuery
} from '@/features/(protected)/accounting/reports/balance-sheet/model/balance-sheet.types'

// Accepted types for the toQueryString function
type QueryTypes = GenerateBalanceSheetQuery | DownloadBalanceSheetQuery

// Map for changing the accept header in the downloadBalanceSheet function based on the entered file type
const acceptHeader = new Map([
  ['pdf', 'application/pdf'],
  ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
  ['csv', 'text/csv']
])

/**
 * Retrieves account data for the Balance Sheet report from the backend server
 *
 * @param {GenerateBalanceSheetQuery} query Includes the date ranges and account IDs
 */
export async function fetchBalanceSheet(query: GenerateBalanceSheetQuery) {
  const queryString = toQueryString(query)

  const response = await laravelBrowserClient.get(`/api/v1/balance-sheet?${queryString}`)

  return response.data
}

export async function downloadBalanceSheet(query: DownloadBalanceSheetQuery): Promise<Blob> {
  const queryString = toQueryString(query)

  const response = await laravelBrowserClient.get(`/api/v1/balance-sheet/download?${queryString}`, {
    headers: {
      Accept: acceptHeader.get(query.fileType)
    },
    responseType: 'blob'
  })

  return response.data as Blob
}

/**
 * Parses the fields from the Balance Sheet
 * query into a URL-encoded string
 *
 * @param query
 */
function toQueryString(query: QueryTypes): string {
  const queryParameters = new URLSearchParams()

  queryParameters.set('date', query.date)

  query.accountIds.forEach(id => {
    queryParameters.append('accountIds[]', id)
  })

  if (query.compareWith !== 'specific') {
    queryParameters.set('compareWith', query.compareWith)
    queryParameters.set('numberOfPeriods', String(query.numberOfPeriods))
  }

  if (query.compareWith === 'specific') {
    queryParameters.set('compareWith', query.compareWith)
    queryParameters.set('compareDate', query.compareDate)
  }

  // Only parsed when trying to download the balance sheet
  if ('fileType' in query) queryParameters.set('fileType', query.fileType)

  return queryParameters.toString()
}
