import { useEffect, useRef } from 'react'

import { Autocomplete, TableCell, TableRow, Typography } from '@mui/material'
import TextField from '@mui/material/TextField'
import InputAdornment from '@mui/material/InputAdornment'
import IconButton from '@mui/material/IconButton'
import type { Control, UseFormRegister } from 'react-hook-form'
import { useWatch, Controller } from 'react-hook-form'

import { darken, lighten, styled } from '@mui/material/styles'

import { numberInputFormatter } from '@/utils/numberInputFormatter'
import { numberOutputFormatter } from '@/utils/journal-entries/numberOutputFormatter'
import type { TaxColumn } from '@/features/(protected)/accounting/setup/taxes/model/taxes.types'
import type { AccountGroup } from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'

// Props passed to the functional component
interface JournalItemRowProps {
  index: number
  isLastRow: boolean
  isSubmitting: boolean
  isSuccess: boolean
  onAppend: () => void
  isDeleteDisabled: boolean
  availableTaxes: TaxColumn[]
  handleRemoveRow: () => void
  availableAccounts: AccountGroup[]
  control: Control<any>
  register: UseFormRegister<any>
  mode: 'create' | 'edit'
  openTaxModal?: () => void
  shouldAutoFocus: boolean
}

function JournalItemRow({
  index,
  control,
  register,
  onAppend,
  isLastRow,
  isSubmitting,
  isSuccess,
  availableTaxes,
  handleRemoveRow,
  isDeleteDisabled,
  availableAccounts,
  mode,
  openTaxModal,
  shouldAutoFocus
}: JournalItemRowProps) {
  // Used to target the account Autocomplete component
  const accountRef = useRef<HTMLInputElement>(null)

  // Header style for the grouped account options
  const GroupHeader = styled('div')(({ theme }) => ({
    position: 'sticky',
    top: '-8px',
    padding: '5px 10px',
    color: 'white',
    backgroundColor: lighten(theme.palette.primary.light, 0.85),
    ...theme.applyStyles('dark', {
      backgroundColor: darken(theme.palette.primary.dark, 0)
    })
  }))

  // Menu item style for the grouped account options
  const GroupItems = styled('ul')({
    padding: 0
  })

  // Focus the account field automatically when a new row is added
  useEffect(() => {
    if (index > 1 && shouldAutoFocus && accountRef.current) accountRef.current.focus()
  }, [index, shouldAutoFocus])

  // Watch debit value
  const debitValue = useWatch({
    control,
    name: `journalItems.${index}.debit`
  })

  // Watch credit value
  const creditValue = useWatch({
    control,
    name: `journalItems.${index}.credit`
  })

  // Boolean flags to determine which field to disable when another has a value already
  const hasDebit = Number(debitValue) > 0
  const hasCredit = Number(creditValue) > 0

  return (
    <TableRow>
      <TableCell sx={{ borderBottom: isLastRow ? theme => `1px solid ${theme.palette.divider}` : 'none' }}>
        <Controller
          name={`journalItems.${index}.accountId`}
          control={control}
          render={({ field: { onChange, value }, fieldState }) => (
            <Autocomplete
              openOnFocus
              size='small'
              options={availableAccounts}
              disabled={isSubmitting || isSuccess}
              groupBy={option => option.coreType}
              getOptionLabel={account => `(${account.code}) ${account.name}`}
              onChange={(_, account) => onChange(account ? account.id : '')}
              isOptionEqualToValue={(account, value) => account.id === value.id}
              value={availableAccounts.find(account => account.id === value) || null}
              renderGroup={params => (
                <li key={params.key}>
                  <GroupHeader>{params.group}</GroupHeader>
                  <GroupItems>{params.children}</GroupItems>
                </li>
              )}
              renderInput={params => (
                <TextField {...params} inputRef={accountRef} error={!!fieldState.error} placeholder='Select account' />
              )}
            />
          )}
        />
      </TableCell>

      <TableCell sx={{ borderBottom: isLastRow ? theme => `1px solid ${theme.palette.divider}` : 'none' }}>
        <TextField
          {...register(`journalItems.${index}.description`)}
          fullWidth
          size='small'
          disabled={isSubmitting || isSuccess}
          placeholder='Max. 250 characters'
        />
      </TableCell>

      <TableCell sx={{ borderBottom: isLastRow ? theme => `1px solid ${theme.palette.divider}` : 'none' }}>
        <Controller
          name={`journalItems.${index}.taxId`}
          control={control}
          render={({ field: { onChange, value } }) => (
            <Autocomplete
              openOnFocus
              size='small'
              options={availableTaxes}
              disabled={isSubmitting || isSuccess}
              getOptionLabel={tax => `${tax.name} [${tax.rate}%]`}
              value={availableTaxes?.find(tax => tax.id === value) || null}
              isOptionEqualToValue={(tax, value) => tax.id === value.id}
              renderInput={params => <TextField {...params} placeholder='Select tax' />}
              onChange={(_, tax) => {
                if (tax?.id === 'add-new' && openTaxModal) return
                onChange(tax ? tax.id : '')
              }}
              filterOptions={options => {
                if (mode === 'edit') return [...options]
                else {
                  return [
                    ...options,
                    {
                      id: 'add-new',
                      name: '',
                      rate: 0
                    } as TaxColumn
                  ]
                }
              }}
              renderOption={(props, tax) => {
                if (tax.id === 'add-new') {
                  return (
                    <li
                      {...props}
                      key={tax.id}
                      onClick={e => {
                        e.stopPropagation()
                        if (openTaxModal) openTaxModal()
                      }}
                    >
                      <Typography color='primary.main'>+ Add Tax Rate</Typography>
                    </li>
                  )
                }

                return (
                  <li {...props} key={tax.id}>
                    {`${tax.name} [${tax.rate}%]`}
                  </li>
                )
              }}
            />
          )}
        />
      </TableCell>

      <TableCell
        align='right'
        sx={{ borderBottom: isLastRow ? theme => `1px solid ${theme.palette.divider}` : 'none' }}
      >
        <TextField
          fullWidth
          size='small'
          disabled={hasCredit || isSubmitting || isSuccess}
          {...register(`journalItems.${index}.debit`, {
            onChange: numberInputFormatter,
            onBlur: numberOutputFormatter
          })}
          slotProps={{
            htmlInput: {
              type: 'text',
              inputMode: 'decimal',
              style: { textAlign: 'right' }
            },
            input: { startAdornment: <InputAdornment position='start'>&#8369;</InputAdornment> }
          }}
          onFocus={e => e.target.select()}
          onKeyDown={keyboardEvent => {
            // Append new row when 'Tab' is pressed
            if (keyboardEvent.key === 'Tab' && !keyboardEvent.shiftKey && hasDebit && !hasCredit && isLastRow) {
              keyboardEvent.preventDefault()
              onAppend()
            }
          }}
        />
      </TableCell>

      <TableCell
        align='right'
        sx={{ borderBottom: isLastRow ? theme => `1px solid ${theme.palette.divider}` : 'none' }}
      >
        <TextField
          fullWidth
          size='small'
          disabled={hasDebit || isSubmitting || isSuccess}
          {...register(`journalItems.${index}.credit`, {
            onChange: numberInputFormatter,
            onBlur: numberOutputFormatter
          })}
          slotProps={{
            htmlInput: {
              type: 'text',
              inputMode: 'decimal',
              style: { textAlign: 'right' }
            },
            input: { startAdornment: <InputAdornment position='start'>&#8369;</InputAdornment> }
          }}
          onFocus={e => e.target.select()}
          onKeyDown={keyboardEvent => {
            // Append new row when 'Tab' is pressed
            if (keyboardEvent.key === 'Tab' && !keyboardEvent.shiftKey && isLastRow) {
              keyboardEvent.preventDefault()
              onAppend()
            }
          }}
        />
      </TableCell>

      <TableCell
        align='center'
        sx={{ borderBottom: isLastRow ? theme => `1px solid ${theme.palette.divider}` : 'none' }}
      >
        <IconButton
          size='small'
          color='error'
          tabIndex={-1}
          onClick={handleRemoveRow}
          disabled={isDeleteDisabled || isSubmitting || isSuccess}
        >
          <i className='bx bx-trash' />
        </IconButton>
      </TableCell>
    </TableRow>
  )
}

export default JournalItemRow
