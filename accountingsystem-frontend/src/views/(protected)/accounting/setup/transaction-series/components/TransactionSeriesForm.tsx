import { useEffect } from 'react'

import { zodResolver } from '@hookform/resolvers/zod'

import { Controller, useForm } from 'react-hook-form'

import {
  Box,
  Button,
  FormControlLabel,
  FormHelperText,
  MenuItem,
  Select,
  Stack,
  Switch,
  TextField,
  Typography
} from '@mui/material'

import Tooltip from '@mui/material/Tooltip'

import type {
  JournalNumberColumn,
  JournalNumberFormValues,
  JournalNumberSubmitValues
} from '@/features/(protected)/accounting/setup/transaction-series/model/transaction-series.types'

import { CreateJournalNumberSchema } from '@/features/(protected)/accounting/setup/transaction-series/model/transaction-series.schema'

// Props for this functional component
interface FormProps {
  isSubmitting: boolean
  handleClose: () => void
  numberToShow: string
  journalNumberToEdit: JournalNumberColumn | null
  onCreate: (newJournalNumber: JournalNumberSubmitValues) => void
  onEdit: (modifiedJournalNumber: JournalNumberSubmitValues) => void
}

// Default form values
const defaultValues: JournalNumberFormValues = {
  name: '',
  prefix: '',
  resetInterval: 'daily',
  status: 'active'
}

// Dropdown options for the reset intervals
const resetIntervals = [
  {
    label: 'Daily',
    value: 'daily'
  },
  {
    label: 'Weekly',
    value: 'weekly'
  },
  {
    label: 'Monthly',
    value: 'monthly'
  },
  {
    label: 'Yearly',
    value: 'yearly'
  }
]

/**
 * Drawer form used for both creating and editing transaction-series records.
 */
function TransactionSeriesForm({
  isSubmitting,
  handleClose,
  numberToShow,
  journalNumberToEdit,
  onCreate,
  onEdit
}: FormProps) {
  // Form hook
  const {
    reset,
    control,
    register,
    handleSubmit,
    formState: { errors, isDirty }
  } = useForm<JournalNumberFormValues>({
    resolver: zodResolver(CreateJournalNumberSchema),
    defaultValues: defaultValues
  })

  // Populate the fields with the data of the journal number to edit if journalNumberToEdit !== null
  useEffect(() => {
    if (journalNumberToEdit) {
      reset(journalNumberToEdit)
    } else reset(defaultValues)
  }, [journalNumberToEdit, reset])

  return (
    <Stack onSubmit={event => event.preventDefault()} component={'form'} alignItems='stretch' justifyContent='space-between' sx={{ height: '100%', p: 5 }}>
      {/* Fields */}
      <Stack spacing={5}>
        <Typography variant='h5'>Journal Number Details</Typography>

        {/* Journal Number Name */}
        <Stack spacing={2}>
          <Typography>Transaction Name</Typography>
          <TextField
            placeholder='Enter a transaction name'
            error={!!errors.name}
            disabled={isSubmitting}
            {...register('name')}
            helperText={errors.name?.message}
          />
        </Stack>

        {/* Journal Number Prefix */}
        <Stack spacing={2}>
          <Stack direction='row' spacing={2} alignItems='center'>
            <Typography>Prefix</Typography>
            {!journalNumberToEdit && (
              <Tooltip placement='right' title='Journal numbers have a starting number of 1 by default'>
                <i className='bx bx-help-circle' />
              </Tooltip>
            )}
          </Stack>
          <Stack
            direction='row'
            sx={{ gap: 2, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}
          >
            <TextField
              sx={{ flex: 1 }}
              error={!!errors.prefix}
              disabled={isSubmitting}
              {...register('prefix')}
              helperText={errors.prefix?.message}
              placeholder='Enter a prefix'
            />
            -
            <Tooltip arrow disableInteractive title='Cannot be modified'>
              <span>
                <TextField sx={{ flex: 1 }} disabled value={numberToShow} />
              </span>
            </Tooltip>
          </Stack>
          <Typography variant='caption'>Must be a unique prefix</Typography>
        </Stack>

        {/* Journal Number Reset Interval */}
        <Stack spacing={2}>
          <Typography>Reset Interval</Typography>
          <Controller
            name='resetInterval'
            control={control}
            render={({ field, fieldState }) => (
              <Select {...field} fullWidth error={!!fieldState.error} disabled={isSubmitting}>
                {resetIntervals.map(interval => (
                  <MenuItem key={interval.value} value={interval.value}>
                    {interval.label}
                  </MenuItem>
                ))}
              </Select>
            )}
          />
        </Stack>

        {/* Journal Number Status */}
        <Controller
          name='status'
          control={control}
          render={({ field }) => (
            <Box>
              <FormControlLabel
                disabled={isSubmitting}
                label={`${field.value === 'active' ? 'Active' : 'Inactive'} Journal Number`}
                control={
                  <Switch
                    size='medium'
                    id='tax-status'
                    checked={field.value === 'active'}
                    onChange={(_, checked) => field.onChange(checked ? 'active' : 'inactive')}
                  />
                }
              />
              <FormHelperText>Inactive journal numbers cannot be used when creating journal entries</FormHelperText>
            </Box>
          )}
        />
      </Stack>

      {/* Buttons */}
      <Stack spacing={2} direction='row' alignItems='center' justifyContent='start'>
        {/* Create/Edit Button */}
        <Button
          variant='contained'
          disabled={!isDirty || isSubmitting}
          onClick={() =>
            handleSubmit(
              data => {
                journalNumberToEdit
                  ? onEdit(data as JournalNumberSubmitValues)
                  : onCreate(data as JournalNumberSubmitValues)
              },
              err => console.log(err)
            )()
          }
        >
          {journalNumberToEdit ? 'Edit' : 'Create'}
        </Button>

        {/* Close Button */}
        <Button
          color={isDirty ? 'error' : 'secondary'}
          disabled={isSubmitting}
          onClick={() => {
            reset(defaultValues)
            handleClose()
          }}
        >
          {isDirty ? 'Discard' : 'Cancel'}
        </Button>
      </Stack>
    </Stack>
  )
}

export default TransactionSeriesForm
