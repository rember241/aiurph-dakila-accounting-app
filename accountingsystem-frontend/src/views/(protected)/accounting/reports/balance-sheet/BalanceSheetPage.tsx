'use client'

import { useEffect, useMemo, useRef, useState } from 'react'

import { useRouter } from 'next/navigation'

import type { Dayjs } from 'dayjs'
import dayjs from 'dayjs'

import {
  Button,
  Checkbox,
  Divider,
  IconButton,
  MenuItem,
  Select,
  Stack,
  TextField,
  Tooltip,
  Typography
} from '@mui/material'

import { toast, ToastContainer } from 'react-toastify'

import { DatePicker, LocalizationProvider } from '@mui/x-date-pickers'

import { AdapterDayjs } from '@mui/x-date-pickers/AdapterDayjs'

import CustomPageHeader from '@components/app/CustomPageHeader'
import type { BalanceSheetAccount } from '@/features/(protected)/accounting/reports/balance-sheet/model/balance-sheet.types'

import { useAccountsTreeQuery } from '@/features/(protected)/accounting/setup/chart-of-accounts/api/accounts.queries'
import { groupAccounts } from '@/utils/journal-entries/groupAccounts'
import {
  useBalanceSheetQuery,
  useDownloadBalanceSheet
} from '@/features/(protected)/accounting/reports/balance-sheet/api/balance-sheet.queries'
import CustomDrawer from '@components/app/CustomDrawer'
import MultipleAccountSelect from '@components/app/Accounting/MultipleAccountSelect'
import BalanceSheetReportBody from '@views/(protected)/accounting/reports/balance-sheet/components/BalanceSheetReportBody'
import AccountTransactionsModal from '@components/app/Accounting/AccountTransactionsModal'
import PromptUserCard from '@components/app/Accounting/PromptUserCard'
import { useMeQuery } from '@/features/auth/api/auth.queries'
import SpinnerCard from '@components/app/Accounting/SpinnerCard'
import FilterPromptCard from '@components/app/Accounting/FilterPromptCard'
import ReportHeader from '@components/app/Accounting/ReportHeader'
import PrintableReportFrame from '@components/app/Accounting/PrintableReportFrame'
import ReportSummaryCards from '@components/app/Accounting/ReportSummaryCards'
import { useCompanySettingsQuery } from '@/features/(protected)/settings/api/settings.queries'
import { currencyFormatter } from '@/utils/currencyFormatter'

function BalanceSheetPage() {
  // Used to redirect to the COA page
  const router = useRouter()

  // Filter drawer states
  const [isDrawerOpen, setIsDrawerOpen] = useState(false)
  const [date, setDate] = useState<Dayjs>(dayjs())
  const [selectedAccounts, setSelectedAccounts] = useState<string[]>([])
  const [compareWithOption, setCompareWithOption] = useState<'period' | 'fiscalYear' | 'specific' | ''>('')
  const [numberOfPeriods, setNumberOfPeriods] = useState<number>(1)
  const [compareDate, setCompareDate] = useState<Dayjs>(dayjs().subtract(1, 'days'))

  /*
   * Separate copy of the filter options to avoid updating the report display when changing filters.
   * Some of these are also used as parameters for the download feature of the Balance Sheet report
   */
  const [headerDate, setHeaderDate] = useState<Dayjs>(dayjs())
  const [headerAccounts, setHeaderAccounts] = useState<string[]>([])
  const [dateText, setDateText] = useState<string>('')
  const [compareWithCopy, setCompareWithCopy] = useState<'period' | 'fiscalYear' | 'specific' | ''>('')
  const [numberOfPeriodsCopy, setNumberOfPeriodsCopy] = useState<number>(1)
  const [compareDateCopy, setCompareDateCopy] = useState<Dayjs>(dayjs())

  // Account transaction modal state and content
  const [isModalOpen, setIsModalOpen] = useState<boolean>(false)
  const [accountToView, setAccountToView] = useState<BalanceSheetAccount | null>(null)

  // TanStack Query to fetch user data
  const { data: userData } = useMeQuery()
  const { data: companySettings } = useCompanySettingsQuery()

  // TanStack Query hook to fetch accounts
  const { data: accountsData } = useAccountsTreeQuery()

  // TanStack Query hook to download the balance sheet
  const { mutateAsync: downloadReport } = useDownloadBalanceSheet()

  // TanStack Query hook to fetch Balance sheet data
  const {
    data: reportData,
    refetch: fetchReportData,
    isFetching: isReportFetching
  } = useBalanceSheetQuery({
    date: date.format('YYYY-MM-DD'),
    accountIds: selectedAccounts,
    compareWith: compareWithOption,
    numberOfPeriods,
    compareDate: compareDate.format('YYYY-MM-DD')
  })

  // Group posting accounts by their core types and extract only income and expense accounts for the dropdown component
  const accountOptions = useMemo(() => {
    if (!accountsData?.data.length) return []

    const groupedAccounts = groupAccounts(accountsData.data)

    return groupedAccounts.filter(
      account => account.type === 'asset' || account.type === 'liability' || account.type === 'equity'
    )
  }, [accountsData])

  // Pre-select all accounts when the options have loaded
  useEffect(() => {
    setSelectedAccounts(accountOptions.map(account => account.id))
  }, [accountOptions])

  // Handle report generation. Runs silently (no toast) for the automatic first-load preview
  const handleReportGeneration = async (options?: { silent?: boolean }) => {
    if (options?.silent) {
      await fetchReportData()
    } else {
      toast.dismiss()

      await toast.promise(fetchReportData, {
        pending: 'Generating report...',
        success: 'Report generated successfully!',
        error: {
          render: ({ data }: { data: any }) => {
            const backendMessage = data?.response?.data?.message

            return backendMessage ?? `Error generating report`
          }
        }
      })
    }

    // Update the copies of the filter options and change the date text for the ReportHeader
    setHeaderDate(date)
    setHeaderAccounts(selectedAccounts)
    setCompareWithCopy(compareWithOption)
    setNumberOfPeriodsCopy(numberOfPeriods)
    setCompareDateCopy(compareDate)
    const tempDateText = date.format('MMMM DD, YYYY')

    date.isSame(dayjs(), 'day') ? setDateText(`As of Today (${tempDateText})`) : setDateText(`As of ${tempDateText}`)
  }

  // Automatically generate a preview report on first load once accounts are pre-selected
  const hasAutoGeneratedRef = useRef(false)

  useEffect(() => {
    if (!hasAutoGeneratedRef.current && accountOptions.length > 0 && selectedAccounts.length > 0) {
      hasAutoGeneratedRef.current = true
      handleReportGeneration({ silent: true })
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [accountOptions, selectedAccounts])

  // Handle report download
  const handleReportDownload = async (fileType: 'pdf' | 'csv' | 'xlsx') => {
    toast.dismiss()

    await toast.promise(
      downloadReport({
        date: headerDate.format('YYYY-MM-DD'),
        accountIds: headerAccounts,
        compareWith: compareWithCopy,
        numberOfPeriods: numberOfPeriodsCopy,
        compareDate: compareDateCopy.format('YYYY-MM-DD'),
        fileType: fileType
      }),
      {
        pending: 'Downloading report...',
        error: {
          render: ({ data }: { data: any }) => {
            const backendMessage = data?.response?.data?.message

            return backendMessage ?? `Error downloading report`
          }
        }
      }
    )
  }

  const handlePrint = () => {
    window.print()
  }

  return (
    <>
      {/* Toast Notification */}
      <ToastContainer stacked autoClose={2500} position='top-center' />

      {/* Balance Sheet Header */}
      <CustomPageHeader
        title='Balance Sheet'
        description='A snapshot of assets, liabilities, and equity at a specific point in time'
      >
        {/* Hide buttons if there are no accounts made */}
        {(accountsData || accountOptions.length !== 0) && (
          <Stack gap={2} direction='row' alignItems='center' justifyContent='flex-end'>
            {/* Filter Button */}
            <Button
              color='secondary'
              startIcon={<i className='bx-slider' />}
              onClick={() => setIsDrawerOpen(true)}
              disabled={!accountOptions.length || isReportFetching}
            >
              Filters
            </Button>

            {/* Generate Report Button */}
            <Tooltip
              placement='bottom-end'
              title='No accounts have been selected'
              disableFocusListener={selectedAccounts.length > 0}
              disableHoverListener={selectedAccounts.length > 0}
            >
              <span>
                <Button
                  color='primary'
                  variant='contained'
                  onClick={() => handleReportGeneration()}
                  disabled={selectedAccounts.length == 0 || !accountOptions.length || isReportFetching}
                >
                  Generate
                </Button>
              </span>
            </Tooltip>
          </Stack>
        )}
      </CustomPageHeader>

      {/* Filter Drawer */}
      <CustomDrawer
        width={560}
        title='Filter Options'
        subtitle='Configure selected date and accounts'
        open={isDrawerOpen}
        onClose={() => setIsDrawerOpen(false)}
      >
        <Stack height='100%' width='100%' sx={{ p: 5 }} alignItems='flex-start' justifyContent='space-between'>
          <Stack gap={5} width='100%' alignItems='flex-start' justifyContent='flex-start'>
            {/* Date Picker */}
            <Stack gap={1} width='100%' alignItems='flex-start'>
              <Typography variant='h6'>Date</Typography>
              <LocalizationProvider dateAdapter={AdapterDayjs}>
                <DatePicker
                  sx={{ width: '100%' }}
                  value={date}
                  onChange={(value: Dayjs | null) => setDate(value ?? dayjs())}
                  disabled={!accountOptions.length || isReportFetching}
                />
              </LocalizationProvider>
            </Stack>

            {/* Account Selection */}
            <Stack sx={{ width: '100%' }} gap={1} alignItems='flex-start'>
              <Typography variant='h6'>Accounts</Typography>
              <Stack sx={{ width: '100%' }} gap={2} direction='row' alignItems='center'>
                <MultipleAccountSelect
                  withLabel={false}
                  disabled={!accountOptions.length || isReportFetching}
                  accounts={accountOptions}
                  selectedAccounts={selectedAccounts}
                  setSelectedAccounts={setSelectedAccounts}
                />

                <Checkbox
                  checked={selectedAccounts.length === accountOptions.length}
                  onClick={() =>
                    selectedAccounts.length === accountOptions.length
                      ? setSelectedAccounts([])
                      : setSelectedAccounts(accountOptions.map(account => account.id))
                  }
                />
              </Stack>
            </Stack>

            <Divider flexItem sx={{ width: '98%' }} />

            {/* "Compare With" Selection */}
            <Stack width='98%' gap={1}>
              <Typography variant='h6'>Compare With</Typography>
              <Select
                fullWidth
                displayEmpty
                value={compareWithOption}
                onChange={event => setCompareWithOption(event.target.value)}
              >
                <MenuItem value=''>None</MenuItem>
                <MenuItem value='specific'>Specific Date</MenuItem>
                <MenuItem value='period'>Previous Period(s)</MenuItem>
                <MenuItem value='fiscalYear'>Previous Fiscal Year(s)</MenuItem>
              </Select>
            </Stack>

            {/* Number of Periods */}
            {compareWithOption.length > 0 && compareWithOption !== 'specific' && (
              <Stack width='98%' gap={1}>
                <Typography variant='h6'>
                  {compareWithOption === 'period' ? 'Number of Period(s)' : 'Number of Year(s)'}
                </Typography>
                <TextField
                  fullWidth
                  value={numberOfPeriods}
                  onFocus={e => e.target.select()}
                  onChange={e => {
                    // Only allow numbers
                    const sanitizedValue = e.target.value.replace(/\D/g, '')

                    if (sanitizedValue === '') return

                    // Limit period/fiscal year range from 1-36
                    const num = Math.min(Math.max(Number(sanitizedValue), 1), 36)

                    setNumberOfPeriods(num)
                  }}
                  slotProps={{
                    htmlInput: {
                      style: { textAlign: 'center' }
                    },
                    input: {
                      startAdornment: (
                        <IconButton
                          onClick={() => {
                            if (numberOfPeriods != 1) setNumberOfPeriods(numberOfPeriods - 1)
                          }}
                        >
                          <i className='bx-minus' />
                        </IconButton>
                      ),
                      endAdornment: (
                        <IconButton
                          onClick={() => {
                            if (numberOfPeriods != 36) setNumberOfPeriods(numberOfPeriods + 1)
                          }}
                        >
                          <i className='bx-plus' />
                        </IconButton>
                      )
                    }
                  }}
                />
                <Typography variant='caption'>
                  {`Max ${compareWithOption === 'period' ? 'periods' : 'years'}: 36 (Larger comparisons may break print/PDF layouts)`}
                </Typography>
              </Stack>
            )}

            {/* Specific Date Picker */}
            {compareWithOption.length > 0 && compareWithOption === 'specific' && (
              <Stack gap={1} width='100%' alignItems='flex-start'>
                <Typography variant='h6'>Date to Compare With</Typography>
                <LocalizationProvider dateAdapter={AdapterDayjs}>
                  <DatePicker
                    sx={{ width: '100%' }}
                    value={compareDate}
                    onChange={(value: Dayjs | null) => setCompareDate(value ?? dayjs())}
                    disabled={!accountOptions.length || isReportFetching}
                  />
                </LocalizationProvider>
              </Stack>
            )}
          </Stack>

          {/* Filter Buttons */}
          <Stack direction='row' gap={2} justifyContent='flex-start' alignItems='center'>
            <Button
              color='primary'
              variant='contained'
              onClick={() => {
                setIsDrawerOpen(false)
                handleReportGeneration()
              }}
            >
              Generate
            </Button>

            <Button variant='outlined' color='secondary' onClick={() => setIsDrawerOpen(false)}>
              Close
            </Button>
          </Stack>
        </Stack>
      </CustomDrawer>

      {!accountsData ? (
        <>
          {/* Show spinner if accounts are still being fetched */}
          <SpinnerCard />
        </>
      ) : accountOptions.length === 0 ? (
        <>
          {/* Prompt user to make an account if no accounts can be selected */}
          <PromptUserCard
            icon='bx-book'
            mainText='No accounts yet'
            subText='To get your Balance Sheet ready, simply set up at least one account for each category: Assets, Liabilities, and Equity.'
            buttonText='Create Account'
            buttonAction={() => router.push('/accounting/setup/accounts')}
          />
        </>
      ) : !reportData ? (
        <FilterPromptCard
          mainText='Configure report filters or generate the report with pre-selected filters'
          subText='(By default, all accounts have been selected, and the date has been set to today)'
        />
      ) : (
        <Stack gap={5} direction='column' alignItems='stretch' justifyContent='flex-start'>
          <ReportHeader
            companyName={userData?.user?.company?.name ?? ''}
            reportType={'Balance Sheet'}
            dateText={dateText}
            isLoading={isReportFetching}
            handleDownload={handleReportDownload}
            handlePrint={handlePrint}
            status={{
              label:
                Math.abs((reportData?.totalAssets[0] ?? 0) - (reportData?.totalLiabilitiesEquities[0] ?? 0)) < 0.01
                  ? 'Balanced'
                  : 'Out of balance',
              color:
                Math.abs((reportData?.totalAssets[0] ?? 0) - (reportData?.totalLiabilitiesEquities[0] ?? 0)) < 0.01
                  ? 'success'
                  : 'error'
            }}
          />

          <ReportSummaryCards
            loading={isReportFetching}
            items={[
              {
                label: 'Total Assets',
                value: currencyFormatter.format(reportData?.totalAssets[0] ?? 0),
                icon: 'bx-buildings',
                tone: 'primary'
              },
              {
                label: 'Total Liabilities',
                value: currencyFormatter.format(reportData?.totalLiabilities[0] ?? 0),
                icon: 'bx-credit-card',
                tone: 'warning'
              },
              {
                label: 'Total Equity',
                value: currencyFormatter.format(reportData?.totalEquities[0] ?? 0),
                icon: 'bx-pie-chart-alt-2',
                tone: 'info'
              }
            ]}
          />

          <PrintableReportFrame
            company={companySettings}
            fallbackCompanyName={userData?.user?.company?.name ?? ''}
            documentTitle='Balance Sheet'
            subtitle={dateText}
          >
            <BalanceSheetReportBody
              data={reportData}
              isLoading={isReportFetching}
              handleViewingTransaction={(account: BalanceSheetAccount) => {
                setAccountToView(account)
                setIsModalOpen(true)
              }}
            />
          </PrintableReportFrame>
        </Stack>
      )}

      {/* Account Transaction Modal */}
      <AccountTransactionsModal
        open={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        account={accountToView}
        date={headerDate}
      />
    </>
  )
}

export default BalanceSheetPage
