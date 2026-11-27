import React from 'react'

import Paper from '@mui/material/Paper'

import Button from '@mui/material/Button'

import { FormControl, InputLabel, Select, Stack } from '@mui/material'

import MenuItem from '@mui/material/MenuItem'

import FilterTabs from '@components/app/FilterTabs'
import CustomTextField from '@core/components/mui/TextField'

import type {
  FiscalYear,
  FiscalYearStatus,
  PeriodStatus
} from '@/features/(protected)/accounting/setup/period-management/models/period-management.types'

const allStatuses = { value: '', label: 'All Statuses', icon: 'bx-list-ul' } as const
const openStatus = { value: 'open', label: 'Open', icon: 'bx-lock-open-alt' } as const
const closedStatus = { value: 'closed', label: 'Closed', icon: 'bx-lock-alt' } as const
const fiscalYearStatuses = [allStatuses, openStatus, closedStatus]

const periodStatuses = [
  allStatuses,
  openStatus,
  { value: 'soft_closed', label: 'Soft Closed', icon: 'bx-time-five' },
  closedStatus
] as const

type FiscalYearsFilterProps = {
  viewMode: 'period' | 'fiscal_year'

  searchValue: string
  onSearchChange: (value: string) => void

  fiscalStatusTypeValue: FiscalYearStatus | ''
  onFiscalStatusTypeChange: (value: FiscalYearStatus | '') => void

  periodStatusTypeValue: PeriodStatus | ''
  onPeriodStatusTypeChange: (value: PeriodStatus | '') => void

  selectedFiscalYearId: string
  onFiscalYearChange: (value: string) => void
  fiscalYearOptions: FiscalYear[]

  onClick?: () => void
  canCreateFiscalYear?: boolean
}

function AccountingPeriodTableActions({
  viewMode,
  searchValue,
  onSearchChange,
  fiscalStatusTypeValue,
  onFiscalStatusTypeChange,
  periodStatusTypeValue,
  onPeriodStatusTypeChange,
  selectedFiscalYearId,
  onFiscalYearChange,
  fiscalYearOptions,
  onClick = () => {},
  canCreateFiscalYear = true
}: FiscalYearsFilterProps) {
  return (
    <Paper sx={{ mb: 3, pt: 2 }}>
      {viewMode === 'period' ? (
        <FilterTabs
          label='Period status'
          value={periodStatusTypeValue}
          onChange={onPeriodStatusTypeChange}
          options={periodStatuses}
        />
      ) : (
        <FilterTabs
          label='Fiscal year status'
          value={fiscalStatusTypeValue}
          onChange={onFiscalStatusTypeChange}
          options={fiscalYearStatuses}
        />
      )}
      <Stack
        direction={{ xs: 'column', md: 'row' }}
        justifyContent='space-between'
        alignItems={{ xs: 'stretch', md: 'center' }}
        gap={3}
        sx={{ p: 5 }}
      >
        <Stack direction='row' alignItems='center' spacing={2} sx={{ width: { xs: '100%', md: 400 } }}>
          <i className='bx-search' aria-hidden='true' style={{ fontSize: 24 }} />
          <CustomTextField
            fullWidth
            value={searchValue}
            onChange={e => onSearchChange(e.target.value)}
            placeholder={
              viewMode === 'period' ? 'Search period, number, or fiscal year' : 'Search fiscal year code or name'
            }
            slotProps={{ htmlInput: { 'aria-label': viewMode === 'period' ? 'Search periods' : 'Search fiscal years' } }}
          />
        </Stack>

        {viewMode === 'period' ? (
          <FormControl sx={{ minWidth: { md: 260 } }} size='small'>
            <InputLabel id='period-year-label'>Fiscal Year</InputLabel>
            <Select
              label='Fiscal Year'
              labelId='period-year-label'
              value={selectedFiscalYearId}
              disabled={fiscalYearOptions.length === 0}
              onChange={e => onFiscalYearChange(e.target.value)}
            >
              {fiscalYearOptions.map(fiscalYear => (
                <MenuItem key={fiscalYear.id} value={fiscalYear.id}>
                  {fiscalYear.code} — {fiscalYear.name}
                  {fiscalYear.is_current ? ' (Current)' : ''}
                </MenuItem>
              ))}
            </Select>
          </FormControl>
        ) : (
          canCreateFiscalYear && (
            <Button variant='contained' onClick={onClick} startIcon={<i className='bx-plus' />}>
              New Fiscal Year
            </Button>
          )
        )}
      </Stack>
    </Paper>
  )
}

export default AccountingPeriodTableActions
