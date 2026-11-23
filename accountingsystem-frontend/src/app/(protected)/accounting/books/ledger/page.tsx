import { APP_ABILITIES } from '@/features/auth/access/model/access.schema'
import { AccessDeniedState } from '@/views/shared/AccessDeniedState'
import { serverCan } from '@/lib/server-access'
import GeneralLedgerPage from '@views/(protected)/accounting/books/general-ledger/GeneralLedgerPage'

export default async function Page() {
  const canReadAccounting = await serverCan(APP_ABILITIES.accountingRead)

  if (!canReadAccounting) {
    return (
      <AccessDeniedState
        title='General ledger is not available'
        description='Your current company role does not include permission to view accounting books.'
      />
    )
  }

  return <GeneralLedgerPage />
}
