import { APP_ABILITIES } from '@/features/auth/access/model/access.schema'
import { AccessDeniedState } from '@/views/shared/AccessDeniedState'
import { serverCan } from '@/lib/server-access'
import CashFlowPage from '@views/(protected)/accounting/reports/cash-flow/CashFlowPage'

export default async function Page() {
  const canReadAccounting = await serverCan(APP_ABILITIES.accountingRead)

  if (!canReadAccounting) {
    return (
      <AccessDeniedState
        title='Cash flow is not available'
        description='Your current company role does not include permission to view accounting reports.'
      />
    )
  }

  return <CashFlowPage />
}
