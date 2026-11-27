'use client'

import { useQuery } from '@tanstack/react-query'
import { format, startOfMonth } from 'date-fns'

import { APP_ABILITIES } from '@/features/auth/access/model/access.schema'
import { useCurrentTenantAccess } from '@/features/auth/access/components/CurrentTenantAccessProvider'
import { laravelBrowserClient } from '@/lib/axios/auth/laravel-browser-client'

export type DashboardMonth = {
  month: string
  totalIncome: string
  totalExpenses: string
  netProfit: string
  cashBalance: string | null
  receivables: string | null
  payables: string | null
}

export type RankedAccount = { id: string; name: string; amount: string }

type OverviewData = {
  months: DashboardMonth[]
  rankings: { totalIncome: RankedAccount[]; totalExpenses: RankedAccount[] }
}

export function useDashboardData() {
  const { tenantId, can, canAny } = useCurrentTenantAccess()
  const today = new Date()
  const date = format(today, 'yyyy-MM-dd')
  const startDate = format(startOfMonth(today), 'yyyy-MM-dd')

  const metrics = useQuery({
    queryKey: ['accountingDashboard', tenantId, date],
    queryFn: async () => {
      const { data } = await laravelBrowserClient.get<OverviewData>('/api/v1/accounting/dashboard', {
        params: { startDate, date, summaryOnly: 1 }
      })

      return data
    },
    enabled: Boolean(tenantId) && can(APP_ABILITIES.accountingRead),
    staleTime: 30_000
  })

  return { metrics, today, canAny }
}
