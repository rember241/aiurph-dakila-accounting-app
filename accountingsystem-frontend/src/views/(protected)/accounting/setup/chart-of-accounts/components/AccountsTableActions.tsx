import React from 'react'

import Paper from '@mui/material/Paper'

import Button from '@mui/material/Button'

import { FormControl, InputAdornment, InputLabel, Select, Stack } from '@mui/material'

import MenuItem from '@mui/material/MenuItem'

import CustomTextField from '@core/components/mui/TextField'
import FilterTabs from '@components/app/FilterTabs'
import { ACCOUNT_CATEGORY_FILTERS, ACCOUNT_CORE_TYPE_LABELS } from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'

import type {
  AccountStatus,
  AccountFilter,
  AccountPosting
} from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'

const accountFilters = [
  { value: '', label: 'All Accounts', icon: 'bx-list-ul' },
  { value: 'revenue', label: ACCOUNT_CORE_TYPE_LABELS.revenue, icon: 'bx-briefcase' },
  { value: 'expense', label: ACCOUNT_CORE_TYPE_LABELS.expense, icon: 'bx-receipt' },
  { value: 'asset', label: ACCOUNT_CORE_TYPE_LABELS.asset, icon: 'bx-buildings' },
  { value: 'liability', label: ACCOUNT_CORE_TYPE_LABELS.liability, icon: 'bx-money' },
  { value: 'equity', label: ACCOUNT_CORE_TYPE_LABELS.equity, icon: 'bx-line-chart' },
  ...ACCOUNT_CATEGORY_FILTERS
] as const

type AccountsFilterProps = {
  searchValue: string
  onSearchChange: (value: string) => void

  accountFilter: AccountFilter
  onAccountFilterChange: (value: AccountFilter) => void

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
  accountFilter,
  onAccountFilterChange,
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
    <Paper sx={{ mb: 3, pt: 2 }}>
      <FilterTabs label='Filter accounts' value={accountFilter} onChange={onAccountFilterChange} options={accountFilters} />
      <Stack
        direction={{ xs: 'column', md: 'row' }}
        justifyContent='space-between'
        alignItems={{ xs: 'stretch', md: 'center' }}
        gap={3}
        sx={{ p: { xs: 3, sm: 5 } }}
      >
        <CustomTextField
          size='small'
          value={searchValue}
          onChange={e => onSearchChange(e.target.value)}
          placeholder='Search accounts'
          slotProps={{
            htmlInput: { 'aria-label': 'Search accounts' },
            input: {
              startAdornment: (
                <InputAdornment position='start'>
                  <i className='bx-search' aria-hidden='true' />
                </InputAdornment>
              )
            }
          }}
          sx={{ width: { xs: '100%', md: 300 } }}
        />

        <Stack direction='row' alignItems='center' flexWrap='wrap' gap={2}>
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
