import type { Dispatch, SetStateAction, MouseEvent } from 'react'
import { useEffect, useState } from 'react'

import { DatePicker, LocalizationProvider } from '@mui/x-date-pickers'
import { AdapterDayjs } from '@mui/x-date-pickers/AdapterDayjs'
import { IconButton, ListSubheader, Menu, MenuItem, Stack, Typography } from '@mui/material'
import type { Dayjs } from 'dayjs'
import dayjs from 'dayjs'

import {
  useAccountingPeriodsQuery,
  useGetCurrentFiscalYearQuery
} from '@/features/(protected)/accounting/setup/period-management/api/period-management.queries'
import type { AccountingPeriod } from '@/features/(protected)/accounting/setup/period-management/models/period-management.types'

// Props for this functional component
interface DatePickerProps {
  withLabels?: boolean
  disabled: boolean
  startDate: Dayjs
  endDate: Dayjs
  setStartDate: Dispatch<SetStateAction<Dayjs>>
  setEndDate: Dispatch<SetStateAction<Dayjs>>
}

// Dropdown options for selecting date presets
const datePresets = ['Today', 'This Week', 'This Month', 'This Year', 'Custom']

function PeriodRangePicker({
  withLabels = true,
  disabled,
  startDate,
  setStartDate,
  endDate,
  setEndDate
}: DatePickerProps) {
  //

  // The selected time period
  const [selectedPeriod, setSelectedPeriod] = useState<string>('This Month')

  // State for the Menu component in the dropdown
  const [anchorEl, setAnchorEl] = useState<HTMLElement | null>(null)
  const isDropdownOpen = Boolean(anchorEl)

  // TanStack Query to fetch the current fiscal year
  const { data: currentFiscalYear } = useGetCurrentFiscalYearQuery()

  // TanStack Query to fetch the periods under the current fiscal year
  const { data: accountingPeriods, refetch: fetchPeriods } = useAccountingPeriodsQuery<AccountingPeriod[]>(
    {
      fiscal_year_id: currentFiscalYear?.id,
      isPaginated: false
    },
    {
      enabled: false
    }
  )

  // Fetch the accounting periods only if there is a current fiscal year
  useEffect(() => {
    if (currentFiscalYear === undefined || currentFiscalYear === null) return

    fetchPeriods()
  }, [currentFiscalYear])

  // Function to handle clicking the dropdown button
  const handleDropdownOpen = (event: MouseEvent<HTMLButtonElement>) => {
    setAnchorEl(event.currentTarget)
  }

  // Function to close the dropdown menu
  const handleDropdownClose = () => {
    setAnchorEl(null)
  }

  // Changes the start and end dates based on the selected time period
  useEffect(() => {
    switch (selectedPeriod) {
      case 'Today':
        setStartDate(dayjs())
        setEndDate(dayjs())
        break
      case 'This Week':
        setStartDate(dayjs().day(1))
        setEndDate(dayjs().day(7))
        break
      case 'This Month':
        setStartDate(dayjs().startOf('month'))
        setEndDate(dayjs().endOf('month'))
        break
      case 'This Year':
        setStartDate(dayjs().startOf('year'))
        setEndDate(dayjs().endOf('year'))
        break
    }
  }, [selectedPeriod, setStartDate, setEndDate])

  return (
    <LocalizationProvider dateAdapter={AdapterDayjs}>
      <Stack sx={{ width: '100%' }} gap={2} direction='row' alignItems='center'>
        <Stack sx={{ width: '100%' }} direction='row'>
          {/* Start Date */}
          <DatePicker
            sx={{ flex: 1 }}
            disabled={disabled}
            label={withLabels && 'Start Date'}
            value={startDate}
            onChange={(value: Dayjs | null) => {
              setSelectedPeriod('Custom')
              setStartDate(value ?? dayjs().startOf('month'))
            }}
          />

          {/* End Date */}
          <DatePicker
            sx={{ flex: 1 }}
            disabled={disabled}
            label={withLabels && 'End Date'}
            value={endDate}
            onChange={(value: Dayjs | null) => {
              setSelectedPeriod('Custom')
              setEndDate(value ?? dayjs().endOf('month'))
            }}
          />
        </Stack>

        {/* Period Dropdown */}
        <IconButton disabled={disabled} onClick={isDropdownOpen ? handleDropdownClose : handleDropdownOpen}>
          <i className={isDropdownOpen ? 'bx bx-caret-up' : 'bx bx-caret-down'} />
        </IconButton>

        <Menu
          anchorEl={anchorEl}
          open={isDropdownOpen}
          onClose={handleDropdownClose}
          anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
          transformOrigin={{ vertical: 'top', horizontal: 'right' }}
          slotProps={{
            paper: {
              sx: {
                maxHeight: 500
              }
            }
          }}
        >
          <ListSubheader>Date Presets</ListSubheader>
          {datePresets.map(preset => {
            return (
              <MenuItem
                key={preset}
                value={preset}
                disabled={preset === 'Custom'}
                selected={preset === selectedPeriod}
                onClick={() => {
                  handleDropdownClose()
                  setSelectedPeriod(preset)
                }}
              >
                <Stack
                  sx={{ pl: 3, width: '500px' }}
                  direction='row'
                  alignItems='center'
                  justifyContent='space-between'
                >
                  {preset}
                  <Typography variant='caption'>
                    {(() => {
                      switch (preset) {
                        case 'Today':
                          return dayjs().format('MMMM DD, YYYY')
                        case 'This Week':
                          return `${dayjs().day(1).format('MMMM DD, YYYY')} - ${dayjs().day(7).format('MMMM DD, YYYY')}`
                        case 'This Month':
                          return `${dayjs().startOf('month').format('MMMM DD, YYYY')} - ${dayjs().endOf('month').format('MMMM DD, YYYY')}`
                        case 'This Year':
                          return `${dayjs().startOf('year').format('MMMM DD, YYYY')} - ${dayjs().endOf('year').format('MMMM DD, YYYY')}`
                        case 'Custom':
                          return '-'
                      }
                    })()}
                  </Typography>
                </Stack>
              </MenuItem>
            )
          })}

          {accountingPeriods != null && [
            <ListSubheader key='subheader-accounting'>Accounting Periods</ListSubheader>,
            ...accountingPeriods.map(period => (
              <MenuItem
                key={period.id}
                selected={selectedPeriod === period.id}
                onClick={() => {
                  handleDropdownClose()
                  setSelectedPeriod(period.id)
                  setStartDate(dayjs(period.start_date))
                  setEndDate(dayjs(period.end_date))
                }}
              >
                <Stack
                  sx={{ pl: 3, width: '500px' }}
                  direction='row'
                  alignItems='center'
                  justifyContent='space-between'
                >
                  {period.name}
                  <Typography variant='caption'>
                    {dayjs(period.start_date).format('MMMM DD, YYYY')} -{' '}
                    {dayjs(period.end_date).format('MMMM DD, YYYY')}
                  </Typography>
                </Stack>
              </MenuItem>
            ))
          ]}
        </Menu>
      </Stack>
    </LocalizationProvider>
  )
}

export default PeriodRangePicker
