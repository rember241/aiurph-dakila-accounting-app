import { APP_ABILITIES } from '@/features/auth/access/model/access.schema'
import { AccessDeniedState } from '@/views/shared/AccessDeniedState'
import { serverCan } from '@/lib/server-access'

export default async function Page() {
  const canReadPeriods = await serverCan(APP_ABILITIES.periodRead)

  if (!canReadPeriods) {
    return (
      <AccessDeniedState
        title='Period close is not available'
        description='Your current company role does not include permission to manage accounting periods.'
      />
    )
  }

  return <h1>Closing Period</h1>
}
