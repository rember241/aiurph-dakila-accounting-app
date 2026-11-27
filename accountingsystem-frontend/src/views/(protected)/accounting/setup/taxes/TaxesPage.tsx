'use client'

import { useEffect, useMemo, useState } from 'react'

import { Button, Dialog, DialogActions, DialogContent, DialogContentText, DialogTitle, Paper } from '@mui/material'

import type { GridPaginationModel, GridRowSelectionModel, GridSortModel } from '@mui/x-data-grid'

import { toast, ToastContainer } from 'react-toastify'

import CustomPageHeader from '@components/app/CustomPageHeader'
import TaxSearch from '@views/(protected)/accounting/setup/taxes/components/TaxSearch'
import getTaxColumns from '@views/(protected)/accounting/setup/taxes/components/TaxColumns'
import {
  useCreateTaxMutation,
  useDeleteTaxMutation,
  useEditTaxMutation,
  useTaxesQuery
} from '@/features/(protected)/accounting/setup/taxes/api/taxes.queries'
import CustomTable from '@components/app/CustomTable'
import CustomDrawer from '@components/app/CustomDrawer'
import TaxForm from '@views/(protected)/accounting/setup/taxes/components/TaxForm'
import { useDebouncedValue } from '@/hooks/useDebouncedValue'
import type {
  ModifiedTax,
  TaxColumn,
  TaxStatus,
  TaxSubmitValues
} from '@/features/(protected)/accounting/setup/taxes/model/taxes.types'
import FilterTabs, { ACTIVE_STATUS_FILTERS } from '@components/app/FilterTabs'
import PromptUserCard from '@components/app/Accounting/PromptUserCard'

function TaxesPage() {
  // Tax drawer state
  const [isDrawerOpen, setIsDrawerOpen] = useState<boolean>(false)

  // Confirmation modal states
  const [isEditModalOpen, setIsEditModalOpen] = useState<boolean>(false)
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState<boolean>(false)

  // The selected tax to edit or delete
  const [selectedTax, setSelectedTax] = useState<TaxColumn | null>(null)

  // The modified data of the tax to be edited
  const [modifiedTaxValues, setModifiedTaxValues] = useState<TaxSubmitValues | null>(null)

  // For submitting a new tax rate
  const { mutateAsync: createTax, isPending: isCreateTaxPending } = useCreateTaxMutation()

  // For editing a tax
  const { mutateAsync: editTax, isPending: isEditTaxPending } = useEditTaxMutation()

  // For deleting a tax
  const { mutateAsync: deleteTax, isPending: isDeleteTaxPending } = useDeleteTaxMutation()

  // For searching
  const [searchText, setSearchText] = useState<string>('')
  const debouncedSearchText = useDebouncedValue(searchText, 400)

  // For filtering
  const [status, setStatus] = useState<TaxStatus | ''>('')

  // For sorting
  const [sortModel, setSortModel] = useState<GridSortModel>([{ field: 'name', sort: undefined }])
  const sortDirection = sortModel[0]?.sort
  const sortField = sortModel[0]?.field

  // For pagination
  const [paginationModel, setPaginationModel] = useState<GridPaginationModel>({ page: 0, pageSize: 10 })

  const [rowSelectionModel, setRowSelectionModel] = useState<GridRowSelectionModel>({
    type: 'include',
    ids: new Set()
  })

  // "Dirty" state of the page to flag if search/filters are applied
  const areFiltersApplied = debouncedSearchText.length > 0 || status !== ''

  // Return the user to the first page in the table when searching, sorting, or filtering
  useEffect(() => {
    setPaginationModel(prev => ({ ...prev, page: 0 }))
  }, [searchText, sortDirection, sortField])

  useEffect(() => {
    setRowSelectionModel({
      type: 'include',
      ids: new Set()
    })
  }, [debouncedSearchText, status, sortModel, paginationModel.page])

  // Load the tax columns
  const columns = useMemo(
    () =>
      getTaxColumns({
        onEdit: taxRow => {
          setSelectedTax(taxRow)
          setIsDrawerOpen(true)
        },
        onDelete: taxRow => {
          setSelectedTax(taxRow)
          setIsDeleteModalOpen(true)
        }
      }),
    []
  )

  // Fetch taxes
  const taxes = useTaxesQuery({
    isPaginated: true,
    search: debouncedSearchText,
    status,
    perPage: paginationModel.pageSize,
    sort: sortField ?? 'name',
    currentPage: paginationModel.page + 1, // The +1 is for Laravel's 1-based index on its pages
    order: sortDirection?.toString() ?? 'asc'
  })

  // Function to close the edit drawer and edit modal confirmation
  const handleClose = () => {
    setIsDrawerOpen(false)
    setSelectedTax(null)
    setModifiedTaxValues(null)
  }

  // Function to handle submission of a new tax
  const handleCreate = async (newTax: TaxSubmitValues) => {
    await toast.promise(createTax(newTax), {
      pending: 'Saving tax...',
      success: 'Tax created!',
      error: {
        render: ({ data }: { data: any }) => {
          const backendMessage = data?.response?.data?.message

          return backendMessage ?? `Error saving tax`
        }
      }
    })

    handleClose()
  }

  // Function to handle editing of tax
  const handleEdit = async (taxData: TaxSubmitValues) => {
    if (selectedTax) {
      const modifiedTax: ModifiedTax = {
        id: selectedTax.id,
        data: taxData
      }

      await toast.promise(editTax(modifiedTax), {
        pending: 'Modifying tax...',
        success: 'Tax edited successfully!',
        error: {
          render: ({ data }: { data: any }) => {
            const backendMessage = data?.response?.data?.message

            return backendMessage ?? `Error editing tax`
          }
        }
      })

      handleClose()
    }
  }

  // Function to delete a tax
  const handleDelete = async () => {
    if (selectedTax) {
      await toast.promise(deleteTax(selectedTax.id), {
        pending: 'Deleting tax...',
        success: 'Tax deleted successfully!',
        error: {
          render: ({ data }: { data: any }) => {
            const backendMessage = data?.response?.data?.message

            return backendMessage ?? `Error deleting tax`
          }
        }
      })

      setIsDeleteModalOpen(false)
      setSelectedTax(null)
    }
  }

  const filtersDisabled = taxes?.data?.data?.length === 0 && !areFiltersApplied

  return (
    <>
      {/* Toast Notification */}
      <ToastContainer autoClose={2500} position={'top-center'} pauseOnFocusLoss={false} />

      {/* Tax Header */}
      <CustomPageHeader title='Taxes' description='Manage tax rates and rules applied to journal transactions'>
        <Button
          variant={'contained'}
          size={'medium'}
          startIcon={<i className={'bx-plus'} />}
          onClick={() => {
            setSelectedTax(null)
            setIsDrawerOpen(true)
          }}
        >
          New Tax
        </Button>
      </CustomPageHeader>

      {/* Create/Edit Tax Drawer */}
      <CustomDrawer
        width={450}
        title={selectedTax ? 'Edit Tax' : 'Create New Tax'}
        open={isDrawerOpen}
        subtitle={selectedTax ? 'Modify the details of this tax' : 'Define a new tax rate'}
        onClose={handleClose}
      >
        <TaxForm
          onCreate={handleCreate}
          taxToEdit={selectedTax}
          handleClose={handleClose}
          isSubmitting={isCreateTaxPending || isEditTaxPending}
          onEdit={modifiedTax => {
            if (selectedTax?.isUsed) {
              setModifiedTaxValues(modifiedTax)
              setIsEditModalOpen(true)
            } else {
              handleEdit(modifiedTax)
            }
          }}
        />
      </CustomDrawer>

      <Paper sx={{ mb: 5, pt: 2 }}>
        <FilterTabs
          label='Tax status'
          value={status}
          onChange={setStatus}
          options={ACTIVE_STATUS_FILTERS}
          disabled={filtersDisabled}
        />
        <div className='p-5'>
          <TaxSearch disabled={filtersDisabled} handleSearch={setSearchText} />
        </div>
      </Paper>

      {taxes?.data?.data?.length === 0 && !areFiltersApplied ? (
        <PromptUserCard
          icon='bx-receipt'
          mainText='No taxes found'
          subText='Add one to manage and apply taxes in your transactions.'
          buttonText='Create Tax'
          buttonAction={() => {
            setSelectedTax(null)
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
              loading={taxes.isFetching}
              rows={taxes?.data?.data ?? []}
              onSortModelChange={setSortModel}
              paginationModel={paginationModel}
              rowCount={taxes?.data?.meta.total ?? 0}
              onPaginationModelChange={setPaginationModel}
              rowSelectionModel={rowSelectionModel}
              onRowSelectionModelChange={setRowSelectionModel}
              disableRowSelectionOnClick={true}
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
              This tax has been used in previous transactions.
            </span>{' '}
            Editing it will archive the current record and create a new one with your changes. This ensures that
            existing transactions referencing this tax remain consistent.
          </DialogContentText>
        </DialogContent>
        <DialogActions>
          <Button
            key='cancel-edit'
            color='secondary'
            onClick={() => {
              setModifiedTaxValues(null)
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
              handleEdit(modifiedTaxValues as TaxSubmitValues)
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
            {selectedTax?.isUsed ? (
              <>
                <span style={{ fontWeight: 'bold', textDecoration: 'underline' }}>
                  Deleting this tax will not remove it from existing transactions.
                </span>{' '}
                It will only be removed from the active list and can no longer be selected for new transactions.
              </>
            ) : (
              `Are you sure you want to delete "${selectedTax?.name}"`
            )}
          </DialogContentText>
        </DialogContent>
        <DialogActions>
          <Button
            color='secondary'
            key='cancel-delete'
            disabled={isDeleteTaxPending}
            onClick={() => {
              setIsDeleteModalOpen(false)
              setSelectedTax(null)
            }}
          >
            Cancel
          </Button>
          <Button
            color='error'
            variant='contained'
            key='confirm-delete'
            onClick={handleDelete}
            disabled={isDeleteTaxPending}
          >
            Delete
          </Button>
        </DialogActions>
      </Dialog>
    </>
  )
}

export default TaxesPage
