import { laravelBrowserClient } from '@/lib/axios/auth/laravel-browser-client'

import type {
  CashFlowData,
  CashFlowQuery
} from '@/features/(protected)/accounting/reports/cash-flow/model/cash-flow.types'

/**
 * Retrieves data for the Cash Flow statement from the backend server
 *
 * @param {CashFlowQuery} query Includes the date ranges
 * @return {Promise<CashFlowData>} The data to be displayed in the report
 */
export async function fetchCashFlow(query: CashFlowQuery): Promise<CashFlowData> {
  const queryString = toQueryString(query)

  const response = await laravelBrowserClient.get(`/api/v1/cash-flow?${queryString}`)

  return response.data as CashFlowData
}

/**
 * Parses the fields from the Cash Flow
 * query into a URL-encoded string
 *
 * @param query
 */
function toQueryString(query: CashFlowQuery): string {
  const queryParameters = new URLSearchParams()

  queryParameters.set('startDate', query.startDate)
  queryParameters.set('endDate', query.endDate)
  queryParameters.set('compareEnabled', query.compareEnabled ? 'true' : 'false')

  if (query.compareEnabled && query.comparisonMode) {
    queryParameters.set('comparisonMode', query.comparisonMode)
  }

  if (query.compareEnabled && query.comparisonMode === 'custom') {
    if (query.comparisonStartDate) queryParameters.set('comparisonStartDate', query.comparisonStartDate)
    if (query.comparisonEndDate) queryParameters.set('comparisonEndDate', query.comparisonEndDate)
  }

  return queryParameters.toString()
}
