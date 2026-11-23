import { APP_ABILITIES } from '@/features/auth/access/model/access.schema'
import { AccessDeniedState } from '@/views/shared/AccessDeniedState'
import { serverCanAny } from '@/lib/server-access'
import FundSourcesPage from '@views/(protected)/accounting/setup/fund-sources/FundSourcesPage'

export default async function Page() {
  const canReadFundSources = await serverCanAny([APP_ABILITIES.accountingRead, APP_ABILITIES.journalRead])

  if (!canReadFundSources) {
    return (
      <AccessDeniedState
        title='Fund sources are not available'
        description='Your current company role does not include permission to view accounting setup data for journal transactions.'
      />
    )
  }

  return <FundSourcesPage />
}
