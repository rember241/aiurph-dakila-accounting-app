import { APP_ABILITIES } from '@/features/auth/access/model/access.schema'
import { AccessDeniedState } from '@/views/shared/AccessDeniedState'
import { serverCan } from '@/lib/server-access'
import RolesPage from '@views/(protected)/users/roles/RolesPage'

export default async function Page() {
  const canReadRoles = await serverCan(APP_ABILITIES.rolesRead)

  if (!canReadRoles) {
    return (
      <AccessDeniedState
        title='Roles are not available'
        description='Your current company role does not include permission to view role definitions in this workspace.'
      />
    )
  }

  return <RolesPage />
}
