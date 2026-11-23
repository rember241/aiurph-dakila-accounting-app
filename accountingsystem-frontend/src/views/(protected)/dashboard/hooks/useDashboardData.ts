'use client'

import { useQuery } from '@tanstack/react-query'
import { format } from 'date-fns'

import { laravelBrowserClient } from '@/lib/axios/auth/laravel-browser-client'
import type { GenerateIncomeStatementResponse } from '@/features/(protected)/accounting/reports/income-statement/model/income-statement.types'
import type { GenerateBalanceSheetResponse } from '@/features/(protected)/accounting/reports/balance-sheet/model/balance-sheet.types'
import { useCurrentTenantAccess } from '@/features/auth/access/components/CurrentTenantAccessProvider'

export function useDashboardData() {
  const { tenantId, can } = useCurrentTenantAccess()
  const today = new Date()
  const date = format(today, 'yyyy-MM-dd')
  const startDate = format(new Date(today.getFullYear(), today.getMonth(), 1), 'yyyy-MM-dd')
  const enabled = Boolean(tenantId) && can('accounting.read')

  const options = {
    queryKey: ['accountingDashboard', tenantId, startDate, date],
    queryFn: async (): Promise<{ income: GenerateIncomeStatementResponse; balance: GenerateBalanceSheetResponse }> => {
      const response = await laravelBrowserClient.get('/api/v1/accounting/dashboard', { params: { startDate, date } })

      return response.data
    },
    enabled,
    staleTime: 30_000
  }

  const income = useQuery({ ...options, select: data => data.income })
  const balance = useQuery({ ...options, select: data => data.balance })

  return { income, balance, date, startDate, can }
}
