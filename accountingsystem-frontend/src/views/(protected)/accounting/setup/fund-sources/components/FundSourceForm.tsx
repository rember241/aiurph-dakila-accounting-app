import { useEffect } from 'react'

import { Controller, useForm } from 'react-hook-form'

import { zodResolver } from '@hookform/resolvers/zod'

import { Box, Button, FormControlLabel, FormHelperText, Stack, Switch, TextField, Typography } from '@mui/material'

import type {
  FundSourceColumn,
  FundSourceFormValues,
  FundSourceSubmitValues
} from '@/features/(protected)/accounting/setup/fund-sources/model/fund-sources.types'

import { CreateFundSourceSchema } from '@/features/(protected)/accounting/setup/fund-sources/model/fund-sources.schema'

// Props passed into the FundSourceForm component
interface FundSourceFormProps {
  isSubmitting: boolean
  handleClose: () => void
  fundSourceToEdit: FundSourceColumn | null
  onCreate: (newFundSource: FundSourceSubmitValues) => void
  onEdit: (modifiedFundSource: FundSourceSubmitValues) => void
}

// Default form values
const defaultValues: FundSourceFormValues = {
  name: '',
  status: 'active'
}

function FundSourceForm({ onEdit, onCreate, handleClose, isSubmitting, fundSourceToEdit }: FundSourceFormProps) {
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

  // Populate the fields with the data of the fund source to edit if fundSourceToEdit !== null
  useEffect(() => {
    if (fundSourceToEdit) {
      reset(fundSourceToEdit)
    } else reset(defaultValues)
  }, [fundSourceToEdit, reset])

  return (
    <Stack onSubmit={event => event.preventDefault()} component={'form'} alignItems='stretch' justifyContent='space-between' sx={{ height: '100%', p: 5 }}>
      {/* Fields */}
      <Stack spacing={5}>
        <Typography variant='h5'>Fund Source Details</Typography>

        {/* Fund Source Name */}
        <TextField
          label='Name'
          error={!!errors.name}
          helperText={errors.name?.message}
          {...register('name')}
          slotProps={{
            inputLabel: { shrink: true }
          }}
        />

        {/* Fund Source Status */}
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

      {/* Buttons */}
      <Stack spacing={2} direction='row' alignItems='center' justifyContent='start'>
        {/* Create Button */}
        <Button
          variant='contained'
          disabled={!isDirty || isSubmitting}
          onClick={() =>
            handleSubmit(
              data => {
                fundSourceToEdit ? onEdit(data as FundSourceSubmitValues) : onCreate(data as FundSourceSubmitValues)
              },
              err => console.log(err)
            )()
          }
        >
          {fundSourceToEdit ? 'Edit' : 'Create'}
        </Button>

        {/* Close Button */}
        <Button
          color='secondary'
          disabled={isSubmitting}
          onClick={() => {
            reset(defaultValues)
            handleClose()
          }}
        >
          Close
        </Button>
      </Stack>
    </Stack>
  )
}

export default FundSourceForm
