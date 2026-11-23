import { APP_ABILITIES } from '@/features/auth/access/model/access.schema'
import { AccessDeniedState } from '@/views/shared/AccessDeniedState'
import { serverCan } from '@/lib/server-access'
import BalanceSheetPage from '@views/(protected)/accounting/reports/balance-sheet/BalanceSheetPage'

export default async function Page() {
  const canReadAccounting = await serverCan(APP_ABILITIES.accountingRead)

  if (!canReadAccounting) {
    return (
      <AccessDeniedState
        title='Balance sheet is not available'
        description='Your current company role does not include permission to view accounting reports.'
      />
    )
  }

  return <BalanceSheetPage />
}
