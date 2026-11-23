import { APP_ABILITIES } from '@/features/auth/access/model/access.schema'
import { AccessDeniedState } from '@/views/shared/AccessDeniedState'
import { serverCanAny } from '@/lib/server-access'
import TaxesPage from '@views/(protected)/accounting/setup/taxes/TaxesPage'

export default async function Page() {
  const canReadTaxes = await serverCanAny([APP_ABILITIES.accountingRead, APP_ABILITIES.journalRead])

  if (!canReadTaxes) {
    return (
      <AccessDeniedState
        title='Taxes are not available'
        description='Your current company role does not include permission to view accounting setup data for journal transactions.'
      />
    )
  }

  return <TaxesPage />
}
