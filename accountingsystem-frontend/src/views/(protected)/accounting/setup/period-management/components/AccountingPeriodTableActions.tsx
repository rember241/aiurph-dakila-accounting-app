import React from 'react'

import Paper from '@mui/material/Paper'

import Button from '@mui/material/Button'

import { FormControl, InputLabel, Select, Stack } from '@mui/material'

import MenuItem from '@mui/material/MenuItem'

import CustomTextField from '@core/components/mui/TextField'

import type {
  FiscalYear,
  FiscalYearStatus,
  PeriodStatus
} from '@/features/(protected)/accounting/setup/period-management/models/period-management.types'

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
            placeholder={
              viewMode === 'period' ? 'Search period, number, or fiscal year' : 'Search fiscal year code or name'
            }
            style={{ width: 400 }}
          />
        </Stack>

        <Stack direction='row' alignItems='center' spacing={3}>
          {viewMode === 'period' && (
            <>
              <FormControl sx={{ minWidth: 260 }} size='small'>
                <Select
                  labelId='period-year-label'
                  value={selectedFiscalYearId}
                  displayEmpty
                  disabled={fiscalYearOptions.length === 0}
                  onChange={e => onFiscalYearChange(e.target.value as string)}
                >
                  <MenuItem value='' disabled>
                    Select Period Year
                  </MenuItem>
                  {fiscalYearOptions.map(fiscalYear => (
                    <MenuItem key={fiscalYear.id} value={fiscalYear.id}>
                      {fiscalYear.code} — {fiscalYear.name}
                      {fiscalYear.is_current ? ' (Current)' : ''}
                    </MenuItem>
                  ))}
                </Select>
              </FormControl>

              <FormControl sx={{ minWidth: 140 }} size='small'>
                <InputLabel id='period-status-label'>Status</InputLabel>
                <Select
                  labelId='period-status-label'
                  value={periodStatusTypeValue}
                  label='Status'
                  onChange={e => onPeriodStatusTypeChange(e.target.value as PeriodStatus | '')}
                >
                  <MenuItem value=''>All Status</MenuItem>
                  <MenuItem value='open'>Open</MenuItem>
                  <MenuItem value='soft_closed'>Soft Closed</MenuItem>
                  <MenuItem value='closed'>Closed</MenuItem>
                </Select>
              </FormControl>
            </>
          )}

          {viewMode === 'fiscal_year' && (
            <>
              <FormControl sx={{ minWidth: 140 }} size='small'>
                <InputLabel id='fiscal-year-status-label'>Status</InputLabel>
                <Select
                  labelId='fiscal-year-status-label'
                  value={fiscalStatusTypeValue}
                  label='Status'
                  onChange={e => onFiscalStatusTypeChange(e.target.value as FiscalYearStatus | '')}
                >
                  <MenuItem value=''>All Status</MenuItem>
                  <MenuItem value='open'>Open</MenuItem>
                  <MenuItem value='closed'>Closed</MenuItem>
                </Select>
              </FormControl>

              {canCreateFiscalYear ? (
                <Button variant={'contained'} size={'medium'} onClick={onClick} startIcon={<i className={'bx-plus'} />}>
                  New Fiscal Year
                </Button>
              ) : null}
            </>
          )}
        </Stack>
      </Stack>
    </Paper>
  )
}

export default AccountingPeriodTableActions
