'use client'

import { useEffect, useMemo, useState } from 'react'

import { useRouter } from 'next/navigation'

import {
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  Paper,
  Stack
} from '@mui/material'

import type { GridPaginationModel, GridSortModel } from '@mui/x-data-grid'

import { toast, ToastContainer } from 'react-toastify'

import CustomPageHeader from '@components/app/CustomPageHeader'
import JournalEntrySearch from '@views/(protected)/accounting/transactions/journal-entries/components/JournalEntrySearch'
import FilterTabs from '@components/app/FilterTabs'
import JournalEntryFilter from '@views/(protected)/accounting/transactions/journal-entries/components/JournalEntryFilter'
import {
  useJournalEntriesQuery,
  useVoidJournalEntryMutation
} from '@/features/(protected)/accounting/transactions/journal-entries/api/journal-entries.queries'

import type {
  JournalEntryColumn,
  JournalEntryStatus,
  JournalEntryType
} from '@/features/(protected)/accounting/transactions/journal-entries/model/journal-entries.types'
import CustomTable from '@components/app/CustomTable'
import getJournalEntryColumns from '@views/(protected)/accounting/transactions/journal-entries/components/JournalEntryColumns'

import JournalEntryViewModal from '@views/(protected)/accounting/transactions/journal-entries/components/JournalEntryViewModal'
import { useDebouncedValue } from '@/hooks/useDebouncedValue'
import PromptUserCard from '@components/app/Accounting/PromptUserCard'
import { useCurrentTenantAccess } from '@/features/auth/access/components/CurrentTenantAccessProvider'
import { APP_ABILITIES } from '@/features/auth/access/model/access.schema'

const journalTypes = [
  { value: '', label: 'All Entries', icon: 'bx-list-ul' },
  { value: 'general', label: 'General', icon: 'bx-book' },
  { value: 'adjusting', label: 'Adjusting', icon: 'bx-adjust' },
  { value: 'closing', label: 'Closing', icon: 'bx-lock-alt' }
] as const

function JournalEntriesPage() {
  // Router hook
  const router = useRouter()
  const access = useCurrentTenantAccess()
  const canCreateJournalEntries = access.can(APP_ABILITIES.journalCreate)
  const canManageJournalEntries = access.canAny([APP_ABILITIES.journalWrite, APP_ABILITIES.accountingWrite])

  // Custom TanStack Query hook to void journal entries
  const { mutateAsync, isPending } = useVoidJournalEntryMutation()

  // The journal to either view, edit, or void
  const [selectedJournal, setSelectedJournal] = useState<JournalEntryColumn | null>(null)

  // Journal entry view modal state
  const [isViewModalOpen, setIsViewModalOpen] = useState<boolean>(false)

  // Journal entry void modal state
  const [isVoidModalOpen, setIsVoidModalOpen] = useState<boolean>(false)

  // For searching
  const [searchText, setSearchText] = useState<string>('')
  const debouncedSearchText = useDebouncedValue(searchText, 400)

  // For filtering
  const [journalType, setJournalType] = useState<JournalEntryType | ''>('')
  const [journalStatus, setJournalStatus] = useState<JournalEntryStatus | ''>('')

  // For sorting
  const [sortModel, setSortModel] = useState<GridSortModel>([{ field: 'date', sort: undefined }])

  // For pagination
  const [paginationModel, setPaginationModel] = useState<GridPaginationModel>({ page: 0, pageSize: 10 })

  // "Dirty" state of the page to flag if search/filters are applied
  const areFiltersApplied = debouncedSearchText.length > 0 || journalType !== '' || journalStatus !== ''

  // Return the user to the first page in the table when searching, sorting, or filtering
  useEffect(() => {
    setPaginationModel(prev => ({ ...prev, page: 0 }))
  }, [searchText, journalType, journalStatus, sortModel[0]?.sort, sortModel[0]?.field])

  // Load the journal entry columns
  const columns = useMemo(
    () =>
      getJournalEntryColumns({
        onView: entry => {
          setSelectedJournal(entry)
          setIsViewModalOpen(true)
        },
        onEdit: entry => router.push(`/accounting/transactions/journal-entries/${entry.id}/edit`),
        onDelete: entry => {
          setSelectedJournal(entry)
          setIsVoidModalOpen(true)
        },
        canManageEntries: canManageJournalEntries
      }),
    [canManageJournalEntries]
  )

  // Fetch journal entries. Automatically re-fetches data when searching, sorting, filtering, or traversing pages
  const journalEntries = useJournalEntriesQuery({
    type: journalType,
    search: debouncedSearchText,
    status: journalStatus,
    perPage: paginationModel.pageSize,
    sort: sortModel[0]?.field ?? 'date',
    currentPage: paginationModel.page + 1, // The +1 is for Laravel's 1-based index on its pages
    order: sortModel[0]?.sort?.toString() ?? 'desc'
  })

  const filtersDisabled = !journalEntries?.data?.data?.length && !areFiltersApplied

  return (
    <>
      {/* Toast Notification */}
      <ToastContainer stacked autoClose={2500} position='top-center' />

      {/* Journal Entry Header */}
      <CustomPageHeader title='Journal Entries' description='Record and manage transactions'>
        {canCreateJournalEntries && (
          <Button
            variant={'contained'}
            size={'medium'}
            onClick={() => router.push('/accounting/transactions/journal-entries/new')}
            startIcon={<i className={'bx-plus'} />}
          >
            New Entry
          </Button>
        )}
      </CustomPageHeader>

      <Paper sx={{ mb: 5, pt: 2 }}>
        <FilterTabs
          label='Journal entry type'
          value={journalType}
          onChange={setJournalType}
          options={journalTypes}
          disabled={filtersDisabled}
        />
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={3} sx={{ p: 5 }}>
          <JournalEntrySearch
            disabled={filtersDisabled}
            setSearchText={setSearchText}
          />
          <JournalEntryFilter
            setJournalStatus={setJournalStatus}
            disabled={filtersDisabled}
          />
        </Stack>
      </Paper>

      {journalEntries?.data?.data.length === 0 && !areFiltersApplied ? (
        <>
          {/* Prompt user to create an entry if table data is empty */}
          <PromptUserCard
            icon='bx-book'
            mainText='No transactions found'
            subText='Create a journal entry to begin recording financial activity.'
            buttonText='Create Entry'
            buttonAction={() => router.push('/accounting/transactions/journal-entries/new')}
          />
        </>
      ) : (
        <Paper>
          {/* Table Section */}
          <CustomTable
            columns={columns}
            sortingMode='server'
            sortModel={sortModel}
            paginationMode='server'
            onSortModelChange={setSortModel}
            paginationModel={paginationModel}
            loading={journalEntries.isFetching}
            rows={journalEntries?.data?.data ?? []}
            onPaginationModelChange={setPaginationModel}
            rowCount={journalEntries?.data?.meta.total ?? 0}
            getRowClassName={params => (params.row.status === 'void' ? 'row-disabled' : '')}
            sx={{
              '& .row-disabled .MuiDataGrid-cell:not([data-field="actions"])': {
                opacity: 0.4,
                pointerEvents: 'none'
              }
            }}
          />
        </Paper>
      )}

      {/* View Journal Entry Modal */}
      <JournalEntryViewModal
        journalEntry={selectedJournal}
        isOpen={isViewModalOpen}
        handleClose={() => {
          setIsViewModalOpen(false)
          setTimeout(() => setSelectedJournal(null), 130)
        }}
      />

      {/* Void Journal Entry Modal */}
      <Dialog disableEscapeKeyDown open={isVoidModalOpen}>
        <DialogTitle>
          {`Confirm Voiding Journal Entry
          #${selectedJournal?.prefix}
          -
          ${String(selectedJournal?.journalNumber).padStart(4, '0')}`}
        </DialogTitle>

        <DialogContent>
          <DialogContentText>
            Voiding this entry will permanently lock the record and remove its values from all financial reports. You
            will not be able to reactivate or edit this entry once the voiding process is complete.{' '}
            <span style={{ fontWeight: 'bold', textDecoration: 'underline' }}>This cannot be undone</span>.
          </DialogContentText>
        </DialogContent>

        <DialogActions>
          <Button
            color='secondary'
            variant='outlined'
            disabled={isPending}
            onClick={() => {
              setIsVoidModalOpen(false)
              setTimeout(() => setSelectedJournal(null), 160)
            }}
          >
            Cancel
          </Button>
          <Button
            color='error'
            variant='contained'
            disabled={isPending || !canManageJournalEntries}
            onClick={async () => {
              if (selectedJournal?.id != null) {
                await toast.promise(mutateAsync(selectedJournal?.id), {
                  pending: 'Voiding journal entry...',
                  success: 'Journal entry voided successfully!',
                  error: {
                    render: ({ data }: { data: any }) => {
                      const backendMessage = data?.response?.data?.message

                      return backendMessage ?? 'Error voiding journal entry'
                    }
                  }
                })
              }

              setIsVoidModalOpen(false)
            }}
          >
            Void
          </Button>
        </DialogActions>
      </Dialog>
    </>
  )
}

export default JournalEntriesPage
