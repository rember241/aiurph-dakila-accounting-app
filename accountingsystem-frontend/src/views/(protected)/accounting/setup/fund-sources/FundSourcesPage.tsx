'use client'

import { useEffect, useMemo, useState } from 'react'

import { toast, ToastContainer } from 'react-toastify'
import { Button, Dialog, DialogActions, DialogContent, DialogContentText, DialogTitle, Paper } from '@mui/material'

import type { GridPaginationModel, GridSortModel } from '@mui/x-data-grid'

import CustomPageHeader from '@components/app/CustomPageHeader'
import CustomDrawer from '@components/app/CustomDrawer'
import {
  useCreateFundSourceMutation,
  useDeleteFundSourceMutation,
  useEditFundSourceMutation,
  useFundSourcesQuery
} from '@/features/(protected)/accounting/setup/fund-sources/api/fund-sources.queries'
import getFundSourceColumns from '@views/(protected)/accounting/setup/fund-sources/components/FundSourceColumns'
import FundSourceForm from '@views/(protected)/accounting/setup/fund-sources/components/FundSourceForm'

import CustomTable from '@components/app/CustomTable'
import type {
  FundSourceColumn,
  FundSourceStatus,
  FundSourceSubmitValues,
  ModifiedFundSource
} from '@/features/(protected)/accounting/setup/fund-sources/model/fund-sources.types'
import { useDebouncedValue } from '@/hooks/useDebouncedValue'
import FundSourceSearch from '@views/(protected)/accounting/setup/fund-sources/components/FundSourceSearch'
import FilterTabs, { ACTIVE_STATUS_FILTERS } from '@components/app/FilterTabs'
import PromptUserCard from '@components/app/Accounting/PromptUserCard'

function FundSourcesPage() {
  // Fund source drawer state
  const [isDrawerOpen, setIsDrawerOpen] = useState<boolean>(false)

  // Confirmation modal states
  const [isEditModalOpen, setIsEditModalOpen] = useState<boolean>(false)
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState<boolean>(false)

  // The selected fund source to edit or delete
  const [selectedFundSource, setSelectedFundSource] = useState<FundSourceColumn | null>(null)

  // The modified data of the fund source to be edited
  const [modifiedFundSourceValues, setModifiedFundSourceValues] = useState<FundSourceSubmitValues | null>(null)

  // For submitting a new fund source
  const { mutateAsync: createFundSource, isPending: isCreateFundSourcePending } = useCreateFundSourceMutation()

  // For editing a fund source
  const { mutateAsync: editFundSource, isPending: isEditFundSourcePending } = useEditFundSourceMutation()

  // For deleting a fund source
  const { mutateAsync: deleteFundSource, isPending: isDeleteFundSourcePending } = useDeleteFundSourceMutation()

  // For searching
  const [searchText, setSearchText] = useState<string>('')
  const debouncedSearchText = useDebouncedValue(searchText, 400)

  // For filtering
  const [status, setStatus] = useState<FundSourceStatus | ''>('')

  // For sorting
  const [sortModel, setSortModel] = useState<GridSortModel>([{ field: 'name', sort: undefined }])

  // For pagination
  const [paginationModel, setPaginationModel] = useState<GridPaginationModel>({ page: 0, pageSize: 10 })

  // "Dirty" state of the page to flag if search/filters are applied
  const areFiltersApplied = debouncedSearchText.length > 0 || status !== ''

  // Return the user to the first page in the table when searching, sorting, or filtering
  useEffect(() => {
    setPaginationModel(prev => ({ ...prev, page: 0 }))
  }, [searchText, sortModel[0]?.sort, sortModel[0]?.field])

  // Load the fund source columns
  const columns = useMemo(
    () =>
      getFundSourceColumns({
        onEdit: fundSourceRow => {
          setSelectedFundSource(fundSourceRow)
          setIsDrawerOpen(true)
        },
        onDelete: fundSourceRow => {
          setSelectedFundSource(fundSourceRow)
          setIsDeleteModalOpen(true)
        }
      }),
    []
  )

  // Fetch fund sources
  const fundSources = useFundSourcesQuery({
    isPaginated: true,
    search: debouncedSearchText,
    status: status,
    perPage: paginationModel.pageSize,
    sort: sortModel[0]?.field ?? 'name',
    currentPage: paginationModel.page + 1, // The +1 is for Laravel's 1-based index on its pages
    order: sortModel[0]?.sort?.toString() ?? 'asc'
  })

  // Function to close the edit drawer and edit modal confirmation
  const handleClose = () => {
    setIsDrawerOpen(false)
    setSelectedFundSource(null)
    setModifiedFundSourceValues(null)
  }

  // Function to handle submission of a new fund source
  const handleCreate = async (newFundSource: FundSourceSubmitValues) => {
    await toast.promise(createFundSource(newFundSource), {
      pending: 'Saving fund source...',
      success: 'Fund source created!',
      error: {
        render: ({ data }: { data: any }) => {
          const backendMessage = data?.response?.data?.message

          return backendMessage ?? `Error saving fund source`
        }
      }
    })

    handleClose()
  }

  // Function to handle editing of fund source
  const handleEdit = async (fundSourceData: FundSourceSubmitValues) => {
    if (selectedFundSource) {
      const modifiedFundSource: ModifiedFundSource = {
        id: selectedFundSource.id,
        data: fundSourceData
      }

      await toast.promise(editFundSource(modifiedFundSource), {
        pending: 'Modifying fund source...',
        success: 'Fund source edited successfully!',
        error: {
          render: ({ data }: { data: any }) => {
            const backendMessage = data?.response?.data?.message

            return backendMessage ?? `Error editing fund source`
          }
        }
      })

      handleClose()
    }
  }

  // Function to handle deletion of fund source
  const handleDelete = async () => {
    if (selectedFundSource) {
      await toast.promise(deleteFundSource(selectedFundSource.id), {
        pending: 'Deleting fund source...',
        success: 'Fund source deleted successfully!',
        error: {
          render: ({ data }: { data: any }) => {
            const backendMessage = data?.response?.data?.message

            return backendMessage ?? `Error deleting fund source`
          }
        }
      })

      setIsDeleteModalOpen(false)
      setSelectedFundSource(null)
    }
  }

  const filtersDisabled = fundSources?.data?.data?.length === 0 && !areFiltersApplied

  return (
    <>
      {/* Toast Notification */}
      <ToastContainer autoClose={2500} position={'top-center'} pauseOnFocusLoss={false} />

      {/* Fund Source Header */}
      <CustomPageHeader
        title='Fund Sources'
        description='Define and manage the different sources of funds used for accounting transactions'
      >
        <Button
          variant={'contained'}
          size={'medium'}
          startIcon={<i className={'bx-plus'} />}
          onClick={() => {
            setSelectedFundSource(null)
            setIsDrawerOpen(true)
          }}
        >
          New Fund Source
        </Button>
      </CustomPageHeader>

      {/* New Fund Source Drawer */}
      <CustomDrawer
        width={450}
        open={isDrawerOpen}
        onClose={handleClose}
        title={selectedFundSource ? 'Edit Fund Source' : 'Create Fund Source'}
        subtitle={selectedFundSource ? 'Modify the details of this fund source' : 'Define a new fund source'}
      >
        <FundSourceForm
          onCreate={handleCreate}
          handleClose={handleClose}
          fundSourceToEdit={selectedFundSource}
          isSubmitting={isCreateFundSourcePending || isEditFundSourcePending}
          onEdit={modifiedFundSource => {
            if (selectedFundSource?.isUsed) {
              setModifiedFundSourceValues(modifiedFundSource)
              setIsEditModalOpen(true)
            } else {
              handleEdit(modifiedFundSource)
            }
          }}
        />
      </CustomDrawer>

      <Paper sx={{ mb: 5, pt: 2 }}>
        <FilterTabs
          label='Fund source status'
          value={status}
          onChange={setStatus}
          options={ACTIVE_STATUS_FILTERS}
          disabled={filtersDisabled}
        />
        <div className='p-5'>
          <FundSourceSearch
            disabled={filtersDisabled}
            setSearchText={setSearchText}
          />
        </div>
      </Paper>

      {fundSources?.data?.data?.length === 0 && !areFiltersApplied ? (
        <PromptUserCard
          icon='bx-wallet-alt'
          mainText='No fund sources found'
          subText='Add one to organize your transaction sources.'
          buttonText='Create Fund Source'
          buttonAction={() => {
            setSelectedFundSource(null)
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
              loading={fundSources.isFetching}
              onSortModelChange={setSortModel}
              paginationModel={paginationModel}
              rows={fundSources?.data?.data ?? []}
              onPaginationModelChange={setPaginationModel}
              rowCount={fundSources?.data?.meta.total ?? 0}
            />
          </Paper>
        </>
      )}

      {/* Edit Confirmation Modal */}
      <Dialog disableEscapeKeyDown open={isEditModalOpen} onClose={() => setIsEditModalOpen(false)}>
        <DialogTitle>Confirm Update</DialogTitle>
        <DialogContent>
          <DialogContentText>
            <span style={{ fontWeight: 'bold', textDecoration: 'underline' }}>
              This fund source has been used in previous transactions.
            </span>{' '}
            Editing it will archive the current record and create a new one with your changes. This ensures that
            existing transactions referencing this fund source remain consistent.
          </DialogContentText>
        </DialogContent>
        <DialogActions>
          <Button
            key='cancel-edit'
            color='secondary'
            onClick={() => {
              setModifiedFundSourceValues(null)
              setIsEditModalOpen(false)
            }}
          >
            Cancel
          </Button>
          <Button
            key='confirm-edit'
            variant='contained'
            onClick={() => {
              setIsEditModalOpen(false)
              handleEdit(modifiedFundSourceValues as FundSourceSubmitValues)
            }}
          >
            Confirm
          </Button>
        </DialogActions>
      </Dialog>

      {/* Delete Confirmation Modal */}
      <Dialog disableEscapeKeyDown open={isDeleteModalOpen} onClose={() => setIsDeleteModalOpen(false)}>
        <DialogTitle>Confirm Delete</DialogTitle>
        <DialogContent>
          <DialogContentText>
            {selectedFundSource?.isUsed ? (
              <>
                <span style={{ fontWeight: 'bold', textDecoration: 'underline' }}>
                  Deleting this fund source will not remove it from existing transactions.
                </span>{' '}
                It will only be removed from the active list and can no longer be selected for new transactions.
              </>
            ) : (
              `Are you sure you want to delete "${selectedFundSource?.name}"`
            )}
          </DialogContentText>
        </DialogContent>
        <DialogActions>
          <Button
            color='secondary'
            key='cancel-delete'
            disabled={isDeleteFundSourcePending}
            onClick={() => {
              setIsDeleteModalOpen(false)
              setTimeout(() => setSelectedFundSource(null), 200)
            }}
          >
            Cancel
          </Button>
          <Button
            color='error'
            variant='contained'
            key='confirm-delete'
            onClick={handleDelete}
            disabled={isDeleteFundSourcePending}
          >
            Delete
          </Button>
        </DialogActions>
      </Dialog>
    </>
  )
}

export default FundSourcesPage
