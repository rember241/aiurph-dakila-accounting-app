import { useQuery } from '@tanstack/react-query'

import type { CashFlowQuery } from '@/features/(protected)/accounting/reports/cash-flow/model/cash-flow.types'
import { fetchCashFlow } from '@/features/(protected)/accounting/reports/cash-flow/api/cash-flow.api'

/**
 * Custom hook to fetch account data for the Cash Flow
 * report based on the provided date ranges
 *
 * @param {CashFlowQuery} query The date ranges
 */
export function useCashFlowQuery(query: CashFlowQuery) {
  return useQuery({
    queryKey: ['cashFlow', query],
    queryFn: () => fetchCashFlow(query),
    placeholderData: previousData => previousData, // Prevents table flickering when fetching data
    staleTime: 10_000,
    enabled: false // Disable automatic fetching of cash flow data
  })
}
