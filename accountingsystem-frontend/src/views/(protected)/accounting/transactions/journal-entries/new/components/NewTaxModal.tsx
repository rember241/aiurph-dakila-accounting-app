import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'

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
  InputAdornment,
  Stack,
  Switch,
  TextField
} from '@mui/material'

import type { TaxFormValues, TaxSubmitValues } from '@/features/(protected)/accounting/setup/taxes/model/taxes.types'
import { CreateTaxSchema } from '@/features/(protected)/accounting/setup/taxes/model/taxes.schema'
import { numberInputFormatter } from '@/utils/numberInputFormatter'

// Props to pass to this functional component
interface ModalProps {
  open: boolean
  onClose: () => void
  onSubmit: (newFundSource: TaxSubmitValues) => void
  isSubmitting: boolean
}

// Default form values
const defaultValues: TaxFormValues = {
  name: '',
  rate: '',
  status: 'active'
}

function NewTaxModal({ open, onClose, onSubmit, isSubmitting }: ModalProps) {
  // Form hook
  const {
    reset,
    control,
    register,
    handleSubmit,
    formState: { errors, isDirty }
  } = useForm<TaxFormValues>({
    resolver: zodResolver(CreateTaxSchema),
    defaultValues: defaultValues
  })

  return (
    <Dialog fullWidth maxWidth='sm' open={open} onClose={onClose}>
      <DialogTitle>Add New Tax Rate</DialogTitle>

      {/* Fields */}
      <DialogContent>
        <Stack gap={5} onSubmit={event => event.preventDefault()} component={'form'} alignItems='stretch' justifyContent='flex-start'>
          {/* Tax Name */}
          <Stack gap={2}>
            <DialogContentText>Tax Name</DialogContentText>
            <TextField
              placeholder='Enter a tax name'
              error={!!errors.name}
              disabled={isSubmitting}
              {...register('name')}
              helperText={errors.name?.message}
            />
          </Stack>

          {/* Tax rate */}
          <Stack gap={2}>
            <DialogContentText>Tax Rate</DialogContentText>
            <TextField
              placeholder="Enter the tax's rate"
              error={!!errors.rate}
              disabled={isSubmitting}
              helperText={errors.rate?.message}
              {...register('rate', {
                onChange: numberInputFormatter
              })}
              slotProps={{
                input: { endAdornment: <InputAdornment position='end'>&#37;</InputAdornment> }
              }}
            />
          </Stack>

          {/* Tax Status */}
          <Controller
            name='status'
            control={control}
            render={({ field }) => (
              <Box>
                <FormControlLabel
                  disabled={isSubmitting}
                  label={`${field.value === 'active' ? 'Active' : 'Inactive'} Tax Rate`}
                  control={
                    <Switch
                      size='medium'
                      id='tax-status'
                      checked={field.value === 'active'}
                      onChange={(_, checked) => field.onChange(checked ? 'active' : 'inactive')}
                    />
                  }
                />
                <FormHelperText>Inactive taxes cannot be used when creating journal entries</FormHelperText>
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
        <Button variant='contained' onClick={() => handleSubmit(data => onSubmit(data as TaxSubmitValues))()}>
          Add
        </Button>
      </DialogActions>
    </Dialog>
  )
}

export default NewTaxModal
