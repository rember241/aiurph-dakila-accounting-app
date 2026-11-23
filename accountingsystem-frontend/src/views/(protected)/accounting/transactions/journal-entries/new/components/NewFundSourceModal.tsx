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
  Stack,
  Switch,
  TextField
} from '@mui/material'

import type {
  FundSourceFormValues,
  FundSourceSubmitValues
} from '@/features/(protected)/accounting/setup/fund-sources/model/fund-sources.types'

import { CreateFundSourceSchema } from '@/features/(protected)/accounting/setup/fund-sources/model/fund-sources.schema'

// Props to pass to this functional component
interface ModalProps {
  open: boolean
  onClose: () => void
  onSubmit: (newFundSource: FundSourceSubmitValues) => void
  isSubmitting: boolean
}

// Default form values
const defaultValues: FundSourceFormValues = {
  name: '',
  status: 'active'
}

function NewFundSourceModal({ open, onClose, onSubmit, isSubmitting }: ModalProps) {
  // Form hook
  const {
    reset,
    control,
    register,
    handleSubmit,
    formState: { errors, isDirty }
  } = useForm<FundSourceFormValues>({
    resolver: zodResolver(CreateFundSourceSchema),
    defaultValues: defaultValues
  })

  return (
    <Dialog fullWidth maxWidth='sm' open={open} onClose={onClose}>
      <DialogTitle>Add New Fund Source</DialogTitle>

      {/* Fields */}
      <DialogContent>
        <Stack gap={5} onSubmit={event => event.preventDefault()} component={'form'} alignItems='stretch' justifyContent='flex-start'>
          {/* Fund Source Name */}
          <Stack gap={2}>
            <DialogContentText>Fund Source Name</DialogContentText>
            <TextField
              placeholder='Enter a fund source name'
              error={!!errors.name}
              disabled={isSubmitting}
              {...register('name')}
              helperText={errors.name?.message}
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
                  label={`${field.value === 'active' ? 'Active' : 'Inactive'} Fund Source`}
                  control={
                    <Switch
                      size='medium'
                      id='fund-source-status'
                      checked={field.value === 'active'}
                      onChange={(_, checked) => field.onChange(checked ? 'active' : 'inactive')}
                    />
                  }
                />
                <FormHelperText>Inactive fund sources cannot be used when creating journal entries</FormHelperText>
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
        <Button variant='contained' onClick={() => handleSubmit(data => onSubmit(data as FundSourceSubmitValues))()}>
          Add
        </Button>
      </DialogActions>
    </Dialog>
  )
}

export default NewFundSourceModal
