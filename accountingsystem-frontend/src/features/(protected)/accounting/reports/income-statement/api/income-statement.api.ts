import { laravelBrowserClient } from '@/lib/axios/auth/laravel-browser-client'

import type {
  GenerateIncomeStatementQuery,
  DownloadIncomeStatementQuery
} from '@/features/(protected)/accounting/reports/income-statement/model/income-statement.types'

// Accepted types for the toQueryString function
type QueryTypes = GenerateIncomeStatementQuery | DownloadIncomeStatementQuery

// Map for changing the accept header in the downloadIncomeStatement function based on the entered file type
const acceptHeader = new Map([
  ['pdf', 'application/pdf'],
  ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
  ['csv', 'text/csv']
])

/**
 * Retrieves account data for the income statement report from the backend server
 *
 * @param {GenerateIncomeStatementQuery} query Includes the date ranges and account IDs
 */
export async function generateIncomeStatement(query: GenerateIncomeStatementQuery) {
  const queryString = toQueryString(query)

  const response = await laravelBrowserClient.get(`/api/v1/income-statement?${queryString}`)

  return response.data
}

/**
 * Downloads the income statement
 *
 * @param query Contains the date range, account IDs, and the file type
 */
export async function downloadIncomeStatement(query: DownloadIncomeStatementQuery): Promise<Blob> {
  const queryString = toQueryString(query)

  const response = await laravelBrowserClient.get(`/api/v1/income-statement/download?${queryString}`, {
    headers: {
      Accept: acceptHeader.get(query.fileType)
    },
    responseType: 'blob'
  })

  return response.data as Blob
}

/**
 * Parses the fields from the income statement
 * query into a URL-encoded string
 *
 * @param query
 */
function toQueryString(query: QueryTypes): string {
  const queryParameters = new URLSearchParams()

  queryParameters.set('startDate', query.startDate)
  queryParameters.set('endDate', query.endDate)

  query.accountIds.forEach(id => {
    queryParameters.append('accountIds[]', id)
  })

  if (query.compareWith !== 'specific') {
    queryParameters.set('compareWith', query.compareWith)
    queryParameters.set('numberOfPeriods', String(query.numberOfPeriods))
  }

  if (query.compareWith === 'specific') {
    queryParameters.set('compareWith', query.compareWith)
    queryParameters.set('compareStartDate', String(query.compareStartDate))
    queryParameters.set('compareEndDate', String(query.compareEndDate))
  }

  // Only parsed when trying to download the income statement
  if ('fileType' in query) queryParameters.set('fileType', query.fileType)

  return queryParameters.toString()
}
