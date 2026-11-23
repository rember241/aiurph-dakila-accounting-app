'use client'

import type { Dispatch, SetStateAction } from 'react'

import type { Dayjs } from 'dayjs'

import {
  Button,
  Divider,
  FormControl,
  FormControlLabel,
  InputLabel,
  MenuItem,
  Select,
  Stack,
  Switch,
  Typography
} from '@mui/material'
import type { SelectChangeEvent } from '@mui/material/Select'

import CustomDrawer from '@components/app/CustomDrawer'
import PeriodRangePicker from '@components/app/Accounting/PeriodRangePicker'

type CashFlowComparisonMode = 'previous_period' | 'previous_year' | 'custom'

type CashFlowFiltersDrawerProps = {
  open: boolean
  disabled: boolean
  startDate: Dayjs
  endDate: Dayjs
  compareEnabled: boolean
  comparisonMode: CashFlowComparisonMode
  comparisonStartDate: Dayjs
  comparisonEndDate: Dayjs
  onClose: () => void
  onGenerate: () => void
  setStartDate: Dispatch<SetStateAction<Dayjs>>
  setEndDate: Dispatch<SetStateAction<Dayjs>>
  setCompareEnabled: Dispatch<SetStateAction<boolean>>
  setComparisonMode: Dispatch<SetStateAction<CashFlowComparisonMode>>
  setComparisonStartDate: Dispatch<SetStateAction<Dayjs>>
  setComparisonEndDate: Dispatch<SetStateAction<Dayjs>>
}

/**
 * Filter drawer for the cash flow report.
 *
 * The content intentionally mirrors the Balance Sheet filter structure so the
 * accounting report screens feel consistent, while still preserving the
 * period-range-specific workflow cash flow needs.
 */
function CashFlowFiltersDrawer({
  open,
  disabled,
  startDate,
  endDate,
  compareEnabled,
  comparisonMode,
  comparisonStartDate,
  comparisonEndDate,
  onClose,
  onGenerate,
  setStartDate,
  setEndDate,
  setCompareEnabled,
  setComparisonMode,
  setComparisonStartDate,
  setComparisonEndDate
}: CashFlowFiltersDrawerProps) {
  const handleComparisonModeChange = (event: SelectChangeEvent<CashFlowComparisonMode>) => {
    setComparisonMode(event.target.value as CashFlowComparisonMode)
  }

  return (
    <CustomDrawer
      width={560}
      title='Filter Options'
      subtitle='Configure the report period and comparison settings'
      open={open}
      onClose={onClose}
    >
      <Stack height='100%' width='100%' sx={{ p: 5 }} alignItems='flex-start' justifyContent='space-between'>
        <Stack gap={5} width='100%' alignItems='flex-start' justifyContent='flex-start'>
          <Stack gap={1} width='100%' alignItems='flex-start'>
            <Typography variant='h6'>Current Period</Typography>
            <PeriodRangePicker
              withLabels={false}
              disabled={disabled}
              startDate={startDate}
              endDate={endDate}
              setStartDate={setStartDate}
              setEndDate={setEndDate}
            />
          </Stack>

          <Divider flexItem sx={{ width: '98%' }} />

          <Stack gap={3} width='98%'>
            <Stack gap={1}>
              <Typography variant='h6'>Comparison</Typography>
              <Typography variant='body2' color='text.secondary'>
                Compare the current cash flow with a prior period or a custom range.
              </Typography>
            </Stack>

            <FormControlLabel
              control={
                <Switch
                  checked={compareEnabled}
                  disabled={disabled}
                  onChange={event => setCompareEnabled(event.target.checked)}
                />
              }
              label='Enable comparison'
            />

            {compareEnabled ? (
              <Stack gap={3} width='100%'>
                <FormControl fullWidth size='small'>
                  <InputLabel id='cash-flow-comparison-mode-label'>Compare With</InputLabel>
                  <Select
                    labelId='cash-flow-comparison-mode-label'
                    label='Compare With'
                    value={comparisonMode}
                    onChange={handleComparisonModeChange}
                    disabled={disabled}
                  >
                    <MenuItem value='previous_period'>Previous period</MenuItem>
                    <MenuItem value='previous_year'>Previous year</MenuItem>
                    <MenuItem value='custom'>Custom period</MenuItem>
                  </Select>
                </FormControl>

                {comparisonMode === 'custom' ? (
                  <Stack gap={1} width='100%' alignItems='flex-start'>
                    <Typography variant='h6'>Comparison Period</Typography>
                    <PeriodRangePicker
                      withLabels={false}
                      disabled={disabled}
                      startDate={comparisonStartDate}
                      endDate={comparisonEndDate}
                      setStartDate={setComparisonStartDate}
                      setEndDate={setComparisonEndDate}
                    />
                  </Stack>
                ) : (
                  <Typography variant='caption' color='text.secondary'>
                    {comparisonMode === 'previous_period'
                      ? 'Compares the selected range against the immediately preceding period of the same length.'
                      : 'Compares the selected range against the same dates in the previous year.'}
                  </Typography>
                )}
              </Stack>
            ) : null}
          </Stack>
        </Stack>

        <Stack direction='row' gap={2} justifyContent='flex-start' alignItems='center'>
          <Button
            color='primary'
            variant='contained'
            onClick={() => {
              onClose()
              onGenerate()
            }}
            disabled={disabled}
          >
            Generate
          </Button>

          <Button variant='outlined' color='secondary' onClick={onClose}>
            Close
          </Button>
        </Stack>
      </Stack>
    </CustomDrawer>
  )
}

export default CashFlowFiltersDrawer
