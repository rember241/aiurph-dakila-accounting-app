import { useEffect } from 'react'

import { Box, Button, FormControlLabel, FormHelperText, Stack, Switch, TextField, Typography } from '@mui/material'

import InputAdornment from '@mui/material/InputAdornment'

import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'

import type {
  TaxColumn,
  TaxFormValues,
  TaxSubmitValues
} from '@/features/(protected)/accounting/setup/taxes/model/taxes.types'
import { CreateTaxSchema } from '@/features/(protected)/accounting/setup/taxes/model/taxes.schema'
import { numberInputFormatter } from '@/utils/numberInputFormatter'

// Props passed into the TaxForm component
interface TaxFormProps {
  isSubmitting: boolean
  handleClose: () => void
  taxToEdit: TaxColumn | null
  onCreate: (newTax: TaxSubmitValues) => void
  onEdit: (modifiedTax: TaxSubmitValues) => void
}

// Default form values
const defaultValues: TaxFormValues = {
  name: '',
  rate: '',
  status: 'active'
}

function TaxForm({ onEdit, onCreate, taxToEdit, handleClose, isSubmitting }: TaxFormProps) {
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

  // Populate the fields with the data of the tax to edit if taxToEdit !== null
  useEffect(() => {
    if (taxToEdit) {
      reset(taxToEdit)
    } else reset(defaultValues)
  }, [taxToEdit, reset])

  return (
    <Stack onSubmit={event => event.preventDefault()} component={'form'} alignItems='stretch' justifyContent='space-between' sx={{ height: '100%', p: 5 }}>
      {/* Fields */}
      <Stack spacing={5}>
        <Typography variant='h5'>Tax Details</Typography>

        {/* Tax Name */}
        <TextField
          label='Name'
          error={!!errors.name}
          disabled={isSubmitting}
          {...register('name')}
          helperText={errors.name?.message}
          slotProps={{
            inputLabel: { shrink: true }
          }}
        />

        {/* Tax Rate */}
        <TextField
          label='Rate'
          error={!!errors.rate}
          disabled={isSubmitting}
          helperText={errors.rate?.message}
          {...register('rate', {
            onChange: numberInputFormatter
          })}
          slotProps={{
            inputLabel: { shrink: true },
            input: { endAdornment: <InputAdornment position='end'>&#37;</InputAdornment> }
          }}
        />

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

      {/* Buttons */}
      <Stack spacing={2} direction='row' alignItems='center' justifyContent='start'>
        {/* Create Button */}
        <Button
          variant='contained'
          disabled={!isDirty || isSubmitting}
          onClick={() =>
            handleSubmit(
              data => {
                taxToEdit ? onEdit(data as TaxSubmitValues) : onCreate(data as TaxSubmitValues)
              },
              err => console.log(err)
            )()
          }
        >
          {taxToEdit ? 'Edit' : 'Create'}
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

export default TaxForm
