'use client'

import { useEffect, useMemo, useState } from 'react'

import { Button, Paper, Stack } from '@mui/material'

import { toast, ToastContainer } from 'react-toastify'

import type { GridPaginationModel, GridSortModel } from '@mui/x-data-grid'

import CustomPageHeader from '@components/app/CustomPageHeader'
import TransactionSeriesSearch from '@views/(protected)/accounting/setup/transaction-series/components/TransactionSeriesSearch'
import { useDebouncedValue } from '@/hooks/useDebouncedValue'
import FilterTabs, { ACTIVE_STATUS_FILTERS } from '@components/app/FilterTabs'
import TransactionSeriesFilter from '@views/(protected)/accounting/setup/transaction-series/components/TransactionSeriesFilter'
import type {
  JournalNumberColumn,
  JournalNumberResetInterval,
  JournalNumberStatus,
  JournalNumberSubmitValues,
  ModifiedJournalNumber
} from '@/features/(protected)/accounting/setup/transaction-series/model/transaction-series.types'
import {
  useCreateJournalNumberMutation,
  useDeleteJournalNumberMutation,
  useEditJournalNumberMutation,
  useJournalNumbersQuery
} from '@/features/(protected)/accounting/setup/transaction-series/api/transaction-series.queries'
import getTransactionSeriesColumns from '@views/(protected)/accounting/setup/transaction-series/components/TransactionSeriesColumns'
import CustomTable from '@components/app/CustomTable'
import CustomDrawer from '@components/app/CustomDrawer'
import TransactionSeriesForm from '@views/(protected)/accounting/setup/transaction-series/components/TransactionSeriesForm'
import EditConfirmationModal from '@views/(protected)/accounting/setup/transaction-series/components/EditConfirmationModal'
import DeleteConfirmationModal from '@views/(protected)/accounting/setup/transaction-series/components/DeleteConfirmationModal'
import PromptUserCard from '@components/app/Accounting/PromptUserCard'

/**
 * Transaction series setup workspace.
 *
 * The underlying API and types still use the older "journal number" wording,
 * but this page is the shared sequence manager for accounting document series.
 */
function TransactionSeriesPage() {
  // Drawer state
  const [isDrawerOpen, setIsDrawerOpen] = useState<boolean>(false)

  // The number to show in the disabled journal number text field in the drawer
  const [textFieldNumber, setTextFieldNumber] = useState<string>('0001')

  // Confirmation modal states
  const [isEditModalOpen, setIsEditModalOpen] = useState<boolean>(false)
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState<boolean>(false)

  // The selected journal number to edit or delete
  const [selectedNumber, setSelectedNumber] = useState<JournalNumberColumn | null>(null)

  // The modified data of the journal number to be edited
  const [modifiedData, setModifiedData] = useState<JournalNumberSubmitValues | null>(null)

  // For searching
  const [searchText, setSearchText] = useState<string>('')
  const debouncedSearchText = useDebouncedValue(searchText, 400)

  // For filtering
  const [status, setStatus] = useState<JournalNumberStatus | ''>('')
  const [resetInterval, setResetInterval] = useState<JournalNumberResetInterval | ''>('')

  // For sorting
  const [sortModel, setSortModel] = useState<GridSortModel>([{ field: 'name', sort: undefined }])

  // For pagination
  const [paginationModel, setPaginationModel] = useState<GridPaginationModel>({ page: 0, pageSize: 10 })

  // "Dirty" state of the page to flag if search/filters are applied
  const areFiltersApplied = debouncedSearchText.length > 0 || status !== '' || resetInterval !== ''

  // Return the user to the first page in the table when searching, sorting, or filtering
  useEffect(() => {
    setPaginationModel(prev => ({ ...prev, page: 0 }))
  }, [searchText, sortModel[0]?.sort, sortModel[0]?.field])

  // For submitting a new journal number
  const { mutateAsync: createJournalNumber, isPending: isCreateJournalNumberPending } = useCreateJournalNumberMutation()

  // For editing a journal number
  const { mutateAsync: editJournalNumber, isPending: isEditJournalNumberPending } = useEditJournalNumberMutation()

  // For deleting a journal number
  const { mutateAsync: deleteJournalNumber, isPending: isDeleteJournalNumberPending } = useDeleteJournalNumberMutation()

  // For fetching journal numbers
  const journalNumbers = useJournalNumbersQuery({
    search: debouncedSearchText,
    status,
    resetInterval,
    sort: sortModel[0]?.field ?? 'name',
    order: sortModel[0]?.sort?.toString() ?? 'asc',
    perPage: paginationModel.pageSize,
    currentPage: paginationModel.page + 1, // The +1 is for Laravel's 1-based index on its pages
    isPaginated: true
  })

  // Load columns
  const columns = useMemo(
    () =>
      getTransactionSeriesColumns({
        onEdit: numberRow => {
          setSelectedNumber(numberRow)
          setTextFieldNumber(String(numberRow.startingNumber).padStart(4, '0'))
          setIsDrawerOpen(true)
        },
        onDelete: numberRow => {
          setSelectedNumber(numberRow)
          setIsDeleteModalOpen(true)
        }
      }),
    []
  )

  // Function to close drawer
  const handleClose = () => {
    setIsDrawerOpen(false)
    setTextFieldNumber('0001')
    setSelectedNumber(null)
    setModifiedData(null)
  }

  // Function to handle submission of a new journal number
  const handleCreate = async (newJournalNumber: JournalNumberSubmitValues) => {
    await toast.promise(createJournalNumber(newJournalNumber), {
      pending: 'Creating journal number...',
      success: 'Journal number created!',
      error: {
        render: ({ data }: { data: any }) => {
          const backendMessage = data?.response?.data?.message

          return backendMessage ?? `Error creating journal number`
        }
      }
    })

    handleClose()
  }

  // Function to handle editing of journal number
  const handleEdit = async (journalNumberData: JournalNumberSubmitValues) => {
    if (selectedNumber) {
      const modifiedJournalNumber: ModifiedJournalNumber = {
        id: selectedNumber.id,
        data: journalNumberData
      }

      await toast.promise(editJournalNumber(modifiedJournalNumber), {
        pending: 'Editing journal number...',
        success: 'Journal number edited successfully!',
        error: {
          render: ({ data }: { data: any }) => {
            const backendMessage = data?.response?.data?.message

            return backendMessage ?? `Error editing journal number`
          }
        }
      })

      handleClose()
    }
  }

  // Function to delete a journal number
  const handleDelete = async () => {
    if (selectedNumber) {
      await toast.promise(deleteJournalNumber(selectedNumber.id), {
        pending: 'Deleting journal number...',
        success: 'Journal number deleted successfully!',
        error: {
          render: ({ data }: { data: any }) => {
            const backendMessage = data?.response?.data?.message

            return backendMessage ?? `Error deleting journal number`
          }
        }
      })

      setIsDeleteModalOpen(false)
      setSelectedNumber(null)
    }
  }

  const filtersDisabled = !journalNumbers?.data?.data?.length && !areFiltersApplied

  return (
    <>
      {/* Toast Notification */}
      <ToastContainer stacked autoClose={2500} position='top-center' />

      {/* Transaction Series Header */}
      <CustomPageHeader
        title='Transaction Series'
        description='Configure unique prefixes and number sequences to identify journal transactions'
      >
        <Button
          variant={'contained'}
          size={'medium'}
          onClick={() => {
            setIsDrawerOpen(true)
          }}
          startIcon={<i className={'bx-plus'} />}
        >
          New Journal Number
        </Button>
      </CustomPageHeader>

      {/* Create/Edit Drawer */}
      <CustomDrawer
        width={465}
        title={selectedNumber ? 'Edit Journal Number' : 'Create New Journal Number'}
        open={isDrawerOpen}
        subtitle={selectedNumber ? 'Modify the details of this journal number' : 'Configure a new journal number'}
        onClose={handleClose}
      >
        <TransactionSeriesForm
          numberToShow={textFieldNumber}
          journalNumberToEdit={selectedNumber}
          handleClose={handleClose}
          isSubmitting={isCreateJournalNumberPending || isEditJournalNumberPending}
          onCreate={handleCreate}
          onEdit={modifiedJournalNumber => {
            if (selectedNumber?.isUsed) {
              setModifiedData(modifiedJournalNumber)
              setIsEditModalOpen(true)
            } else handleEdit(modifiedJournalNumber)
          }}
        />
      </CustomDrawer>

      <Paper sx={{ mb: 5, pt: 2 }}>
        <FilterTabs
          label='Transaction series status'
          value={status}
          onChange={setStatus}
          options={ACTIVE_STATUS_FILTERS}
          disabled={filtersDisabled}
        />
        <Stack direction={{ xs: 'column', sm: 'row' }} spacing={3} sx={{ p: 5 }}>
          <TransactionSeriesSearch
            disabled={filtersDisabled}
            setSearchText={setSearchText}
          />
          <TransactionSeriesFilter
            disabled={filtersDisabled}
            setResetInterval={setResetInterval}
          />
        </Stack>
      </Paper>

      {journalNumbers?.data?.data?.length === 0 && !areFiltersApplied ? (
        <PromptUserCard
          icon='bx-hash'
          mainText='No journal numbers found'
          subText='Create a journal number to start identifying transactions.'
          buttonText='Create Journal Number'
          buttonAction={() => {
            setIsDrawerOpen(true)
          }}
        />
      ) : (
        <>
          {/* Table Section */}
          <Paper>
            <CustomTable
              columns={columns}
              sortingMode='server'
              sortModel={sortModel}
              paginationMode='server'
              onSortModelChange={setSortModel}
              paginationModel={paginationModel}
              loading={journalNumbers.isFetching}
              rows={journalNumbers?.data?.data ?? []}
              onPaginationModelChange={setPaginationModel}
              rowCount={journalNumbers?.data?.meta.total ?? 0}
            />
          </Paper>
        </>
      )}

      {/* Edit Confirmation Modal */}
      <EditConfirmationModal
        open={isEditModalOpen}
        onClose={() => setIsEditModalOpen(false)}
        onCancel={() => {
          setModifiedData(null)
          setIsEditModalOpen(false)
        }}
        onConfirm={() => {
          setIsEditModalOpen(false)
          handleEdit(modifiedData as JournalNumberSubmitValues)
        }}
      />

      {/* Delete Confirmation Modal */}
      <DeleteConfirmationModal
        open={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        journalNumber={selectedNumber}
        isDeleting={isDeleteJournalNumberPending}
        onCancel={() => {
          setIsDeleteModalOpen(false)
          setSelectedNumber(null)
        }}
        onDelete={handleDelete}
      />
    </>
  )
}

export default TransactionSeriesPage
