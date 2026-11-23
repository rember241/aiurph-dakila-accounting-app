import { APP_ABILITIES } from '@/features/auth/access/model/access.schema'
import { AccessDeniedState } from '@/views/shared/AccessDeniedState'
import { serverCanAny } from '@/lib/server-access'
import EditJournalEntryPage from '@views/(protected)/accounting/transactions/journal-entries/edit/EditJournalEntryPage'

export default async function Page({ params }: { params: Promise<{ id: string }> }) {
  const canEditJournalEntries = await serverCanAny([APP_ABILITIES.journalWrite, APP_ABILITIES.accountingWrite])

  if (!canEditJournalEntries) {
    return (
      <AccessDeniedState
        title='Editing journal entries is not available'
        description='Your current company role does not include permission to edit journal entries in this workspace.'
      />
    )
  }

  // Grabs the ID of the journal entry to edit from the URL path
  const { id } = await params

  return <EditJournalEntryPage journalEntryId={id} />
}
