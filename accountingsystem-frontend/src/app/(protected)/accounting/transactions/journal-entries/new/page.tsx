import { APP_ABILITIES } from '@/features/auth/access/model/access.schema'
import { AccessDeniedState } from '@/views/shared/AccessDeniedState'
import { serverCan } from '@/lib/server-access'
import NewJournalEntryPage from '@views/(protected)/accounting/transactions/journal-entries/new/NewJournalEntryPage'

export default async function Page() {
  const canCreateJournalEntries = await serverCan(APP_ABILITIES.journalCreate)

  if (!canCreateJournalEntries) {
    return (
      <AccessDeniedState
        title='Creating journal entries is not available'
        description='Your current company role does not include permission to create journal entries in this workspace.'
      />
    )
  }

  return <NewJournalEntryPage />
}
