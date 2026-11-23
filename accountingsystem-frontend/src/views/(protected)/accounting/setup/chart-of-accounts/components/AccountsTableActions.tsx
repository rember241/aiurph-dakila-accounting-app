import React from 'react'

import Paper from '@mui/material/Paper'

import Button from '@mui/material/Button'

import { FormControl, InputLabel, Select, Stack } from '@mui/material'

import MenuItem from '@mui/material/MenuItem'

import CustomTextField from '@core/components/mui/TextField'

import type {
  AccountStatus,
  CoreType,
  AccountPosting
} from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'

type AccountsFilterProps = {
  searchValue: string
  onSearchChange: (value: string) => void

  typeValue: CoreType | ''
  onTypeChange: (value: CoreType | '') => void

  statusValue: AccountStatus | ''
  onStatusChange: (value: AccountStatus | '') => void

  postingValue: AccountPosting
  onPostingChange: (value: AccountPosting) => void

  onClick: () => void
  canCreateAccount?: boolean
}

function AccountsTableActions({
  searchValue,
  onSearchChange,
  typeValue,
  onTypeChange,
  statusValue,
  onStatusChange,
  postingValue,
  onPostingChange,
  onClick,
  canCreateAccount = true
}: AccountsFilterProps) {
  type PostingSelectValue = '' | 'true' | 'false'

  const toPostingSelectValue = (v: AccountPosting | undefined): PostingSelectValue => {
    if (v === true) return 'true'
    if (v === false) return 'false'

    return ''
  }

  return (
    <Paper sx={{ mb: 3, p: 5 }}>
      <Stack
        direction={{ xs: 'column', sm: 'column', md: 'row' }}
        justifyContent={'space-between'}
        alignItems={'center'}
        spacing={2}
      >
        <Stack direction={'row'} alignItems={'center'} spacing={2}>
          <i className={'bx-search'} style={{ fontSize: 24 }} />
          <CustomTextField
            size={'medium'}
            value={searchValue}
            onChange={e => onSearchChange(e.target.value)}
            placeholder='Search an Account'
            style={{ width: 300 }}
          />
        </Stack>

        <Stack direction='row' alignItems='center' spacing={2}>
          {/* Account Type */}
          <FormControl sx={{ minWidth: 120 }} size='small'>
            <InputLabel id='type-label'>Type</InputLabel>
            <Select labelId='type-label' value={typeValue} label='Type' onChange={e => onTypeChange(e.target.value)}>
              <MenuItem value=''>All Types</MenuItem>
              <MenuItem value='asset'>Asset</MenuItem>
              <MenuItem value='liability'>Liability</MenuItem>
              <MenuItem value='equity'>Equity</MenuItem>
              <MenuItem value='revenue'>Revenue</MenuItem>
              <MenuItem value='expense'>Expense</MenuItem>
            </Select>
          </FormControl>

          {/* Status */}
          <FormControl sx={{ minWidth: 120 }} size='small'>
            <InputLabel id='status-label'>Status</InputLabel>
            <Select
              labelId='status-label'
              value={statusValue}
              label='Status'
              onChange={e => onStatusChange(e.target.value as AccountStatus | '')}
            >
              <MenuItem value=''>All</MenuItem>
              <MenuItem value='active'>Active</MenuItem>
              <MenuItem value='inactive'>Inactive</MenuItem>
            </Select>
          </FormControl>

          {/* Posting */}
          <FormControl sx={{ minWidth: 140 }} size='small'>
            <InputLabel id='posting-label'>Posting</InputLabel>
            <Select
              labelId='posting-label'
              label='Posting'
              value={toPostingSelectValue(postingValue)}
              sx={{ mr: 4 }}
              onChange={e => {
                const v = e.target.value as PostingSelectValue

                onPostingChange(v === '' ? undefined : v === 'true')
              }}
            >
              <MenuItem value=''>All</MenuItem>
              <MenuItem value='true'>Posting</MenuItem>
              <MenuItem value='false'>Non-posting</MenuItem>
            </Select>
          </FormControl>

          {/* Create Button */}
          {canCreateAccount ? (
            <Button variant={'contained'} size={'medium'} onClick={onClick} startIcon={<i className={'bx-plus'} />}>
              New Account
            </Button>
          ) : null}
        </Stack>
      </Stack>
    </Paper>
  )
}

export default AccountsTableActions
