import { APP_ABILITIES } from '@/features/auth/access/model/access.schema'
import { AccessDeniedState } from '@/views/shared/AccessDeniedState'
import { serverCanAny } from '@/lib/server-access'
import TransactionSeriesPage from '@views/(protected)/accounting/setup/transaction-series/TransactionSeriesPage'

export default async function Page() {
  const canReadTransactionSeries = await serverCanAny([APP_ABILITIES.accountingRead, APP_ABILITIES.journalRead])

  if (!canReadTransactionSeries) {
    return (
      <AccessDeniedState
        title='Transaction series are not available'
        description='Your current company role does not include permission to view accounting setup data for journal transactions.'
      />
    )
  }

  return <TransactionSeriesPage />
}
