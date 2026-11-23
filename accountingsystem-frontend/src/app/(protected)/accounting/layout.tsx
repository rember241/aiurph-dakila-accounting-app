import type { ChildrenType } from '@core/types'

import { APP_ABILITIES } from '@/features/auth/access/model/access.schema'
import { AccessDeniedState } from '@/views/shared/AccessDeniedState'
import { requireServerCurrentTenantId } from '@/lib/current-tenant'
import { serverCanAny } from '@/lib/server-access'

export default async function Layout({ children }: ChildrenType) {
  await requireServerCurrentTenantId()

  const canOpenAccounting = await serverCanAny([
    APP_ABILITIES.accountingRead,
    APP_ABILITIES.periodRead,
    APP_ABILITIES.journalRead
  ])

  if (!canOpenAccounting) {
    return (
      <AccessDeniedState
        title='Accounting access is not available'
        description='Your current company role does not include access to accounting setup, journals, or period management.'
      />
    )
  }

  return children
}
