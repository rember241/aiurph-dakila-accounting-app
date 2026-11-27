import { useQuery } from '@tanstack/react-query'

import { fetchCurrentTenantAccess } from '@/features/auth/access/api/current-tenant-access.api'
import type { CurrentTenantAccessState } from '@/features/auth/access/model/access.schema'

export function useCurrentTenantAccessQuery(initialData: CurrentTenantAccessState) {
  return useQuery({
    queryKey: ['auth', 'tenant-access'],
    queryFn: fetchCurrentTenantAccess,
    initialData,
    staleTime: 5 * 60 * 1000,
    refetchOnWindowFocus: true,
    retry: false
  })
}
