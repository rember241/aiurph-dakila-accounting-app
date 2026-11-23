import { APP_ABILITIES } from '@/features/auth/access/model/access.schema'
import { AccessDeniedState } from '@/views/shared/AccessDeniedState'
import { serverCan } from '@/lib/server-access'
import JournalEntriesPage from '@views/(protected)/accounting/transactions/journal-entries/JournalEntriesPage'

export default async function Page() {
  const canReadJournalEntries = await serverCan(APP_ABILITIES.journalRead)

  if (!canReadJournalEntries) {
    return (
      <AccessDeniedState
        title='Journal entries are not available'
        description='Your current company role does not include permission to view journal entries in this workspace.'
      />
    )
  }

  return <JournalEntriesPage />
}
