import { APP_ABILITIES } from '@/features/auth/access/model/access.schema'
import { AccessDeniedState } from '@/views/shared/AccessDeniedState'
import { serverCan } from '@/lib/server-access'
import { AccountsPage } from '@views/(protected)/accounting/setup/chart-of-accounts/AccountsPage'

export default async function Page() {
  const canReadAccounts = await serverCan(APP_ABILITIES.accountingRead)

  if (!canReadAccounts) {
    return (
      <AccessDeniedState
        title='Chart of accounts is not available'
        description='Your current company role does not include permission to view account structure in this workspace.'
      />
    )
  }

  return <AccountsPage />
}
