import { useEffect, useState } from 'react'

import { Controller, useFieldArray, useForm, useWatch } from 'react-hook-form'

import { zodResolver } from '@hookform/resolvers/zod'

import {
  Autocomplete,
  Button,
  MenuItem,
  Paper,
  Select,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableFooter,
  TableHead,
  TableRow,
  TextField,
  Typography
} from '@mui/material'

import { DatePicker, LocalizationProvider } from '@mui/x-date-pickers'

import { AdapterDayjs } from '@mui/x-date-pickers/AdapterDayjs'

import { toast } from 'react-toastify'

import dayjs from 'dayjs'

import type {
  CreateEntryFormValues,
  CreateEntrySubmitValues
} from '@/features/(protected)/accounting/transactions/journal-entries/model/journal-entries.types'
import type { FundSourceColumn } from '@/features/(protected)/accounting/setup/fund-sources/model/fund-sources.types'
import type { TaxColumn } from '@/features/(protected)/accounting/setup/taxes/model/taxes.types'

import { CreateJournalEntrySchema } from '@/features/(protected)/accounting/transactions/journal-entries/model/journal-entries.schema'

import JournalItemRow from '../../components/JournalItemRow'
import { currencyFormatter } from '@/utils/journal-entries/currencyFormatter'

import CustomModal from '@components/app/CustomModal'
import JournalEntryFormSkeleton from '@views/(protected)/accounting/transactions/journal-entries/components/JournalEntryFormSkeleton'
import type { JournalNumberColumn } from '@/features/(protected)/accounting/setup/transaction-series/model/transaction-series.types'
import { computeTaxTotals } from '@/utils/journal-entries/computeTaxTotals'
import type { AccountGroup } from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'

// Props to pass into this component
interface FormProps {
  journalNumbers: JournalNumberColumn[]
  fundSources: FundSourceColumn[]
  taxes: TaxColumn[]
  accounts: AccountGroup[]
  isLoading: boolean
  isSubmitting: boolean
  isSuccess: boolean
  onSubmit: (entryData: CreateEntrySubmitValues) => void
  onClose: () => void
  openJournalNumberModal: () => void
  openFundSourceModal: () => void
  openTaxModal: () => void
}

// Default form values
const defaultValues: CreateEntryFormValues = {
  journalNumberId: '',
  date: dayjs(),
  type: 'general',
  fundSourceId: '',
  referenceNumber: '',
  note: '',
  status: '',
  journalItems: [
    { accountId: '', description: '', taxId: '', debit: '0.00', credit: '0.00' },
    { accountId: '', description: '', taxId: '', debit: '0.00', credit: '0.00' }
  ]
}

// Dropdown options for journal type
const journalTypes = [
  {
    label: 'General',
    value: 'general'
  },
  {
    label: 'Adjusting',
    value: 'adjusting'
  },
  {
    label: 'Closing',
    value: 'closing'
  }
]

function CreateJournalEntryForm({
  journalNumbers,
  fundSources,
  taxes,
  accounts,
  isLoading,
  isSubmitting,
  isSuccess,
  onSubmit,
  onClose,
  openJournalNumberModal,
  openFundSourceModal,
  openTaxModal
}: FormProps) {
  // Form hook
  const {
    control,
    register,
    setValue,
    handleSubmit,
    formState: { isDirty }
  } = useForm<CreateEntryFormValues>({
    resolver: zodResolver(CreateJournalEntrySchema),
    defaultValues: defaultValues
  })

  // Used to work with the dynamic journalItems array
  const { fields, append, remove } = useFieldArray({
    control,
    name: 'journalItems'
  })

  // Function to add a new row in the journal item table
  const handleAddRow = () => append({ accountId: '', description: '', taxId: '', debit: '0.00', credit: '0.00' })

  // Cancel modal state
  const [isCancelModalOpen, setIsCancelModalOpen] = useState<boolean>(false)
  const [isDiscarding, setIsDiscarding] = useState<boolean>(false)

  // The journal number to show in the journal number field
  const [journalNumber, setJournalNumber] = useState<string>('')

  // Total balance states
  const [isTotalBalanced, setIsTotalBalanced] = useState<boolean>(false)
  const [balanceDifference, setBalanceDifference] = useState<string>('0')

  // Used to watch the journalItems array and check if debits and credits are equal
  const journalItems = useWatch({ control, name: 'journalItems' })
  const debitSubTotal = journalItems?.reduce((sum, item) => sum + (Number(item.debit) || 0), 0) || 0
  const creditSubTotal = journalItems?.reduce((sum, item) => sum + (Number(item.credit) || 0), 0) || 0

  // Compute tax totals grouped by taxId
  const taxTotals = computeTaxTotals(journalItems, taxes) ?? {}

  // Compute the total of the debit and credit side with taxes
  const debitTotalWithTax = taxTotals.reduce((sum, t) => sum + t.debit, debitSubTotal)
  const creditTotalWithTax = taxTotals.reduce((sum, t) => sum + t.credit, creditSubTotal)

  // Checks the balance of debit and credit totals with tax
  useEffect(() => {
    const isBalanced =
      Math.round(debitTotalWithTax * 100) === Math.round(creditTotalWithTax * 100) &&
      debitTotalWithTax + creditTotalWithTax !== 0

    setIsTotalBalanced(isBalanced)
    setBalanceDifference(Math.abs(debitTotalWithTax - creditTotalWithTax).toFixed(2))
  }, [debitTotalWithTax, creditTotalWithTax])

  // Don't display form yet if data is still being fetched
  if (isLoading) return <JournalEntryFormSkeleton />

  return (
    <>
      <Stack spacing={5} onSubmit={event => event.preventDefault()} component='form' alignItems='stretch' justifyContent='flex-start'>
        {/* Entry Details */}
        <Paper sx={{ p: 4, gap: 8, display: 'flex', justifyContent: 'space-between' }}>
          {/* Left Column */}
          <Stack spacing={4} sx={{ flex: 2 }}>
            <Stack spacing={2}>
              <Typography>Journal #</Typography>
              <Stack
                direction='row'
                sx={{ gap: 2, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}
              >
                <Controller
                  name='journalNumberId'
                  control={control}
                  render={({ field: { onChange, value }, fieldState }) => (
                    <Autocomplete
                      openOnFocus
                      size='small'
                      sx={{ flex: 1 }}
                      options={journalNumbers}
                      disabled={isSubmitting || isSuccess}
                      getOptionLabel={journalNumber => journalNumber.prefix}
                      value={journalNumbers.find(journalNumber => journalNumber.id === value) || null}
                      isOptionEqualToValue={(journalNumber, value) => journalNumber.id === value.id}
                      filterOptions={options => [
                        ...options,
                        {
                          id: 'add-new',
                          prefix: ''
                        } as JournalNumberColumn
                      ]}
                      renderInput={params => <TextField {...params} error={!!fieldState.error} placeholder='Prefix' />}
                      onChange={(_, journalNumber) => {
                        if (journalNumber?.id === 'add-new') return

                        onChange(journalNumber ? journalNumber.id : '')
                        setJournalNumber(journalNumber ? String(journalNumber.startingNumber).padStart(4, '0') : '')
                      }}
                      renderOption={(props, journalNumber) => {
                        if (journalNumber.id === 'add-new') {
                          return (
                            <li
                              {...props}
                              key={journalNumber.id}
                              onClick={e => {
                                e.stopPropagation()
                                openJournalNumberModal()
                              }}
                            >
                              <Typography color='primary.main'>+ Add Journal Number</Typography>
                            </li>
                          )
                        }

                        return (
                          <li {...props} key={journalNumber.id}>
                            {`${journalNumber.prefix} (${journalNumber.name})`}
                          </li>
                        )
                      }}
                    />
                  )}
                />
                -
                <TextField
                  disabled
                  fullWidth
                  size='small'
                  sx={{ flex: 1 }}
                  value={journalNumber}
                  placeholder='Journal Number'
                />
              </Stack>
            </Stack>

            <Stack spacing={2}>
              <LocalizationProvider dateAdapter={AdapterDayjs}>
                <Typography>Entry Date</Typography>
                <Controller
                  name='date'
                  control={control}
                  render={({ field, fieldState }) => (
                    <DatePicker
                      value={field.value}
                      disabled={isSubmitting || isSuccess}
                      onChange={newDate => field.onChange(newDate)}
                      slotProps={{
                        textField: {
                          size: 'small',
                          fullWidth: true,
                          error: !!fieldState.error
                        }
                      }}
                    />
                  )}
                />
              </LocalizationProvider>
            </Stack>

            <Stack spacing={2}>
              <Typography>Journal Type</Typography>
              <Controller
                name='type'
                control={control}
                render={({ field, fieldState }) => (
                  <Select
                    {...field}
                    fullWidth
                    size='small'
                    error={!!fieldState.error}
                    disabled={isSubmitting || isSuccess}
                  >
                    {journalTypes.map(type => (
                      <MenuItem key={type.value} value={type.value}>
                        {type.label}
                      </MenuItem>
                    ))}
                  </Select>
                )}
              />
            </Stack>

            <Stack spacing={2}>
              <Typography>Fund Source</Typography>
              <Controller
                name='fundSourceId'
                control={control}
                render={({ field: { onChange, value }, fieldState }) => (
                  <Autocomplete
                    openOnFocus
                    size='small'
                    options={fundSources}
                    disabled={isSubmitting || isSuccess}
                    getOptionLabel={fundSource => fundSource.name}
                    value={fundSources.find(fundSource => fundSource.id === value) || null}
                    isOptionEqualToValue={(fundSource, value) => fundSource.id === value.id}
                    renderInput={params => (
                      <TextField {...params} error={!!fieldState.error} placeholder='Select fund source' />
                    )}
                    filterOptions={options => [
                      ...options,
                      {
                        id: 'add-new',
                        name: ''
                      } as FundSourceColumn
                    ]}
                    onChange={(_, fundSource) => {
                      if (fundSource?.id === 'add-new') return
                      onChange(fundSource ? fundSource.id : '')
                    }}
                    renderOption={(props, fundSource) => {
                      if (fundSource.id === 'add-new') {
                        return (
                          <li
                            {...props}
                            key={fundSource.id}
                            onClick={e => {
                              e.stopPropagation()
                              openFundSourceModal()
                            }}
                          >
                            <Typography color='primary.main'>+ Add Fund Source</Typography>
                          </li>
                        )
                      }

                      return (
                        <li {...props} key={fundSource.id}>
                          {fundSource.name}
                        </li>
                      )
                    }}
                  />
                )}
              />
            </Stack>

            <Stack spacing={2}>
              <Typography>Reference #</Typography>
              <TextField
                fullWidth
                size='small'
                placeholder='Reference Number'
                disabled={isSubmitting || isSuccess}
                {...register('referenceNumber')}
              />
            </Stack>
          </Stack>

          {/* Right Column */}
          <Stack spacing={2} sx={{ flex: 3 }}>
            <Typography>Note</Typography>
            <TextField
              multiline
              {...register('note')}
              disabled={isSubmitting || isSuccess}
              placeholder='Max. 500 characters'
              slotProps={{
                htmlInput: { maxLength: 200 }
              }}
              sx={{
                flex: 1,
                '& .MuiInputBase-root': {
                  height: '100%',
                  alignItems: 'flex-start'
                }
              }}
            />
          </Stack>
        </Paper>

        <Stack spacing={2} alignItems='stretch' justifyContent='flex-start'>
          {/* Add New Row Button */}
          <Stack direction='row' alignItems='center' justifyContent='flex-start'>
            <Button
              onClick={handleAddRow}
              disabled={isSubmitting || isSuccess}
              startIcon={<i className='bx bx-plus' />}
            >
              Add New Row
            </Button>
          </Stack>

          {/* Entry Items Table */}
          <Paper sx={{ p: 4 }}>
            <Table>
              <TableHead sx={{ backgroundColor: theme => theme.palette.background.default }}>
                <TableRow>
                  <TableCell sx={{ width: '20%', fontWeight: 'bold' }}>ACCOUNT</TableCell>
                  <TableCell sx={{ width: '25%', fontWeight: 'bold' }}>DESCRIPTION</TableCell>
                  <TableCell sx={{ width: '15%', fontWeight: 'bold' }}>TAX</TableCell>
                  <TableCell sx={{ width: '15%', fontWeight: 'bold' }} align='right'>
                    DEBIT
                  </TableCell>
                  <TableCell sx={{ width: '15%', fontWeight: 'bold' }} align='right'>
                    CREDIT
                  </TableCell>
                  <TableCell sx={{ width: '10%', fontWeight: 'bold' }} align='center'>
                    REMOVE
                  </TableCell>
                </TableRow>
              </TableHead>

              <TableBody>
                {fields.map((field, index) => (
                  <JournalItemRow
                    mode='create'
                    index={index}
                    key={field.id}
                    shouldAutoFocus
                    control={control}
                    register={register}
                    isSuccess={isSuccess}
                    onAppend={handleAddRow}
                    isSubmitting={isSubmitting}
                    availableTaxes={taxes}
                    openTaxModal={openTaxModal}
                    availableAccounts={accounts}
                    isDeleteDisabled={fields.length <= 2}
                    handleRemoveRow={() => remove(index)}
                    isLastRow={index === fields.length - 1}
                  />
                ))}
              </TableBody>

              <TableFooter>
                {/* Sub Total Row */}
                <TableRow>
                  <TableCell
                    colSpan={3}
                    align='right'
                    sx={{
                      fontWeight: 'bold',
                      borderBottom: taxTotals.length !== 0 ? 'none' : theme => `1px solid ${theme.palette.divider}`
                    }}
                  >
                    <Typography variant='subtitle2'>Sub Total</Typography>
                  </TableCell>
                  <TableCell
                    align='right'
                    sx={{
                      borderBottom: taxTotals.length !== 0 ? 'none' : theme => `1px solid ${theme.palette.divider}`
                    }}
                  >
                    <Typography variant='caption'>{currencyFormatter.format(debitSubTotal)}</Typography>
                  </TableCell>
                  <TableCell
                    align='right'
                    sx={{
                      borderBottom: taxTotals.length !== 0 ? 'none' : theme => `1px solid ${theme.palette.divider}`
                    }}
                  >
                    <Typography variant='caption'>{currencyFormatter.format(creditSubTotal)}</Typography>
                  </TableCell>
                  <TableCell
                    sx={{
                      borderBottom: taxTotals.length !== 0 ? 'none' : theme => `1px solid ${theme.palette.divider}`
                    }}
                  />
                </TableRow>

                {/* Tax Rows */}
                {taxTotals.length !== 0 &&
                  taxTotals.map((currentTax, index) => (
                    <TableRow key={currentTax.id}>
                      <TableCell
                        colSpan={3}
                        align='right'
                        sx={{
                          fontWeight: 'bold',
                          borderBottom:
                            index + 1 !== taxTotals.length ? 'none' : theme => `1px solid ${theme.palette.divider}`
                        }}
                      >
                        <Typography variant='caption'>{`${currentTax.name} ( ${currentTax.rate}% )`}</Typography>
                      </TableCell>
                      <TableCell
                        align='right'
                        sx={{
                          borderBottom:
                            index + 1 !== taxTotals.length ? 'none' : theme => `1px solid ${theme.palette.divider}`
                        }}
                      >
                        <Typography variant='caption'>{currencyFormatter.format(currentTax.debit)}</Typography>
                      </TableCell>
                      <TableCell
                        align='right'
                        sx={{
                          borderBottom:
                            index + 1 !== taxTotals.length ? 'none' : theme => `1px solid ${theme.palette.divider}`
                        }}
                      >
                        <Typography variant='caption'>{currencyFormatter.format(currentTax.credit)}</Typography>
                      </TableCell>
                      <TableCell
                        sx={{
                          borderBottom:
                            index + 1 !== taxTotals.length ? 'none' : theme => `1px solid ${theme.palette.divider}`
                        }}
                      />
                    </TableRow>
                  ))}

                {/* Total Row */}
                <TableRow>
                  <TableCell colSpan={3} align='right' sx={{ fontWeight: 'bold' }}>
                    <Typography variant='caption'>
                      TOTAL
                      {!isTotalBalanced && debitSubTotal + creditSubTotal > 0 && (
                        <>
                          <br />{' '}
                          <Typography variant='caption' color='#e53935'>
                            Out of balance by: {currencyFormatter.format(Number(balanceDifference))}
                          </Typography>
                        </>
                      )}
                    </Typography>
                  </TableCell>
                  <TableCell align='right' sx={{ fontWeight: 'bold' }}>
                    <Typography variant='caption'>{currencyFormatter.format(debitTotalWithTax)}</Typography>
                  </TableCell>
                  <TableCell align='right' sx={{ fontWeight: 'bold' }}>
                    <Typography variant='caption'>{currencyFormatter.format(creditTotalWithTax)}</Typography>
                  </TableCell>
                  <TableCell />
                </TableRow>
              </TableFooter>
            </Table>
          </Paper>

          {/* Action Buttons */}
          <Stack spacing={2} direction='row' alignItems='center' justifyContent='flex-start'>
            <Button
              variant='contained'
              disabled={!isTotalBalanced || !isDirty || isSubmitting || isSuccess}
              onClick={() => {
                toast.dismiss()
                setValue('status', 'posted')
                handleSubmit(
                  newJournalEntry => onSubmit(newJournalEntry as CreateEntrySubmitValues),
                  () => toast.error('Please fill all required fields')
                )()
              }}
            >
              Post Entry
            </Button>

            <Button
              color='secondary'
              variant='outlined'
              disabled={!isTotalBalanced || !isDirty || isSubmitting || isSuccess}
              onClick={() => {
                toast.dismiss()
                setValue('status', 'draft')
                handleSubmit(
                  newJournalEntry => onSubmit(newJournalEntry as CreateEntrySubmitValues),
                  () => toast.error('Please fill all required fields')
                )()
              }}
            >
              Save as Draft
            </Button>

            <Button
              color={isDirty ? 'error' : 'secondary'}
              disabled={isSubmitting || isSuccess}
              onClick={() => {
                if (isDirty) setIsCancelModalOpen(true)
                else {
                  setIsDiscarding(true)
                  onClose()
                }
              }}
            >
              {isDirty ? 'Discard' : 'Cancel'}
            </Button>
          </Stack>
        </Stack>
      </Stack>

      {/* Cancel Confirmation Modal */}
      <CustomModal
        closeAfterTransition={true}
        open={isCancelModalOpen}
        onClose={onClose}
        title='Discard Changes'
        description='Are you sure you want to discard your changes?'
        actions={
          <>
            <Button
              color='secondary'
              variant='outlined'
              disabled={isDiscarding}
              onClick={() => setIsCancelModalOpen(false)}
            >
              Cancel
            </Button>
            <Button
              color='error'
              variant='contained'
              disabled={isDiscarding}
              onClick={() => {
                setIsDiscarding(true)
                onClose()
              }}
            >
              Discard
            </Button>
          </>
        }
      />
    </>
  )
}

export default CreateJournalEntryForm
