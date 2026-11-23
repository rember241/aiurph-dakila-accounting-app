import { APP_ABILITIES } from '@/features/auth/access/model/access.schema'
import { AccessDeniedState } from '@/views/shared/AccessDeniedState'
import { serverCan } from '@/lib/server-access'
import AccountingPeriodPage from '@views/(protected)/accounting/setup/period-management/AccountingPeriodPage'

export default async function Page() {
  const canReadPeriods = await serverCan(APP_ABILITIES.periodRead)

  if (!canReadPeriods) {
    return (
      <AccessDeniedState
        title='Period management is not available'
        description='Your current company role does not include permission to view fiscal years and accounting periods.'
      />
    )
  }

  return <AccountingPeriodPage />
}
