import type { ReactNode } from 'react'

import { APP_ABILITIES } from '@/features/auth/access/model/access.schema'
import { AccessDeniedState } from '@/views/shared/AccessDeniedState'
import { requireServerCurrentTenantId } from '@/lib/current-tenant'
import { serverCanAny } from '@/lib/server-access'

export default async function UsersLayout({ children }: { children: ReactNode }) {
  await requireServerCurrentTenantId()

  const canOpenUsers = await serverCanAny([
    APP_ABILITIES.orgUnitsRead,
    APP_ABILITIES.usersRead,
    APP_ABILITIES.rolesRead
  ])

  if (!canOpenUsers) {
    return (
      <AccessDeniedState
        title='Users and roles are not available'
        description='Your current company role does not include access to company structure, user management, or role administration.'
      />
    )
  }

  return children
}
