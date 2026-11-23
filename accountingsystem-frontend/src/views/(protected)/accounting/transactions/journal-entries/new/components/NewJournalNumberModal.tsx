import {
  Box,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  FormControlLabel,
  FormHelperText,
  MenuItem,
  Select,
  Stack,
  Switch,
  TextField,
  Tooltip
} from '@mui/material'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'

import type {
  JournalNumberFormValues,
  JournalNumberSubmitValues
} from '@/features/(protected)/accounting/setup/transaction-series/model/transaction-series.types'

import { CreateJournalNumberSchema } from '@/features/(protected)/accounting/setup/transaction-series/model/transaction-series.schema'

// Props to pass to this functional component
interface ModalProps {
  open: boolean
  onClose: () => void
  onSubmit: (newJournalNumber: JournalNumberSubmitValues) => void
  isSubmitting: boolean
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
 * Simple modal to add a new journal number from the create journal entry form
 */
function NewJournalNumberModal({ open, onClose, onSubmit, isSubmitting }: ModalProps) {
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

  return (
    <Dialog fullWidth maxWidth='sm' open={open} onClose={onClose}>
      <DialogTitle>Add New Journal Number</DialogTitle>

      {/* Fields */}
      <DialogContent>
        <Stack gap={5} onSubmit={event => event.preventDefault()} component={'form'} alignItems='stretch' justifyContent='flex-start'>
          {/* Journal Number Name */}
          <Stack gap={2}>
            <DialogContentText>Transaction Name</DialogContentText>
            <TextField
              placeholder='Enter a transaction name'
              error={!!errors.name}
              disabled={isSubmitting}
              {...register('name')}
              helperText={errors.name?.message}
            />
          </Stack>

          {/* Prefix */}
          <Stack gap={2}>
            <Stack gap={2} direction='row' alignItems='center'>
              <DialogContentText>Prefix</DialogContentText>
              <Tooltip
                placement='right'
                disableInteractive
                title='Journal numbers have a starting number of 1 by default'
              >
                <i className='bx bx-help-circle' />
              </Tooltip>
            </Stack>

            <Stack
              direction='row'
              sx={{ gap: 2, direction: 'row', alignItems: 'center', justifyContent: 'flex-start' }}
            >
              <TextField
                fullWidth
                error={!!errors.prefix}
                disabled={isSubmitting}
                {...register('prefix')}
                helperText={errors.prefix?.message}
                placeholder='Enter a prefix'
              />
              -
              <Tooltip arrow disableInteractive title='Cannot be modified'>
                <span>
                  <TextField disabled value='0001' />
                </span>
              </Tooltip>
            </Stack>

            <DialogContentText variant='caption'>Must be a unique prefix</DialogContentText>
          </Stack>

          {/* Reset Interval */}
          <Stack gap={2}>
            <DialogContentText>Reset Interval</DialogContentText>
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

          {/* Status */}
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
      </DialogContent>

      {/* Buttons */}
      <DialogActions>
        {/* Close Button */}
        <Button
          color={isDirty ? 'error' : 'secondary'}
          onClick={() => {
            onClose()
            reset()
          }}
        >
          {isDirty ? 'Discard' : 'Close'}
        </Button>

        {/* Add Button */}
        <Button variant='contained' onClick={() => handleSubmit(data => onSubmit(data as JournalNumberSubmitValues))()}>
          Add
        </Button>
      </DialogActions>
    </Dialog>
  )
}

export default NewJournalNumberModal
