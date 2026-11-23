'use client'

import { useEffect, useMemo, useRef, useState } from 'react'

import { useRouter } from 'next/navigation'

import { toast, ToastContainer } from 'react-toastify'

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

import type { Dayjs } from 'dayjs'
import dayjs from 'dayjs'

import CustomPageHeader from '@components/app/CustomPageHeader'
import PeriodRangePicker from '@components/app/Accounting/PeriodRangePicker'
import MultipleAccountSelect from '@components/app/Accounting/MultipleAccountSelect'
import { useAccountsTreeQuery } from '@/features/(protected)/accounting/setup/chart-of-accounts/api/accounts.queries'
import { groupAccounts } from '@/utils/journal-entries/groupAccounts'
import IncomeStatementReportBody from '@views/(protected)/accounting/reports/income-statement/components/IncomeStatementReportBody'
import {
  useDownloadIncomeStatement,
  useGenerateIncomeStatementQuery
} from '@/features/(protected)/accounting/reports/income-statement/api/income-statement.queries'
import CustomDrawer from '@components/app/CustomDrawer'
import AccountTransactionsModal from '@components/app/Accounting/AccountTransactionsModal'
import type { IncomeStatementAccount } from '@/features/(protected)/accounting/reports/income-statement/model/income-statement.types'
import PromptUserCard from '@components/app/Accounting/PromptUserCard'
import { useMeQuery } from '@/features/auth/api/auth.queries'
import SpinnerCard from '@components/app/Accounting/SpinnerCard'
import FilterPromptCard from '@components/app/Accounting/FilterPromptCard'
import ReportHeader from '@components/app/Accounting/ReportHeader'
import PrintableReportFrame from '@components/app/Accounting/PrintableReportFrame'
import ReportSummaryCards from '@components/app/Accounting/ReportSummaryCards'
import { useCompanySettingsQuery } from '@/features/(protected)/settings/api/settings.queries'
import { currencyFormatter } from '@/utils/currencyFormatter'

function IncomeStatementPage() {
  // Redirect to the COA page
  const router = useRouter()

  // Filter drawer states
  const [isDrawerOpen, setIsDrawerOpen] = useState(false)
  const [startDate, setStartDate] = useState<Dayjs>(dayjs().startOf('month'))
  const [endDate, setEndDate] = useState<Dayjs>(dayjs().endOf('month'))
  const [selectedAccounts, setSelectedAccounts] = useState<string[]>([])
  const [compareWithOption, setCompareWithOption] = useState<'period' | 'fiscalYear' | 'specific' | ''>('')
  const [numberOfPeriods, setNumberOfPeriods] = useState<number>(1)
  const [compareStartDate, setCompareStartDate] = useState<Dayjs>(dayjs().subtract(1, 'month').startOf('month'))
  const [compareEndDate, setCompareEndDate] = useState<Dayjs>(dayjs().subtract(1, 'month').endOf('month'))

  /*
   * Separate copy of the filter options to avoid updating the report display when changing filters.
   * Some of these are also used as parameters for the download feature of the Income Statement report
   */
  const [headerStartDate, setHeaderStartDate] = useState<Dayjs>(dayjs().startOf('month'))
  const [headerEndDate, setHeaderEndDate] = useState<Dayjs>(dayjs().endOf('month'))
  const [dateText, setDateText] = useState<string>('')
  const [headerAccounts, setHeaderAccounts] = useState<string[]>([])
  const [compareWithCopy, setCompareWithCopy] = useState<'period' | 'fiscalYear' | 'specific' | ''>('')
  const [numberOfPeriodsCopy, setNumberOfPeriodsCopy] = useState<number>(1)
  const [compareStartDateCopy, setCompareStartDateCopy] = useState<Dayjs>(dayjs().startOf('month'))
  const [compareEndDateCopy, setCompareEndDateCopy] = useState<Dayjs>(dayjs().endOf('month'))

  // Account transaction modal state and content
  const [isModalOpen, setIsModalOpen] = useState<boolean>(false)
  const [accountToView, setAccountToView] = useState<IncomeStatementAccount | null>(null)

  // TanStack Query to fetch user data
  const { data: userData } = useMeQuery()
  const { data: companySettings } = useCompanySettingsQuery()

  // TanStack Query hook to fetch accounts
  const { data: accountsData } = useAccountsTreeQuery()

  // TanStack Query hook to download the income statement
  const { mutateAsync: downloadReport } = useDownloadIncomeStatement()

  // TanStack Query hook to fetch income statement data
  const {
    data: reportData,
    refetch: fetchReportData,
    isFetching: isReportFetching
  } = useGenerateIncomeStatementQuery({
    startDate: startDate.format('YYYY-MM-DD'),
    endDate: endDate.format('YYYY-MM-DD'),
    accountIds: selectedAccounts,
    compareWith: compareWithOption,
    numberOfPeriods,
    compareStartDate: compareStartDate.format('YYYY-MM-DD'),
    compareEndDate: compareEndDate.format('YYYY-MM-DD')
  })

  // Group posting accounts by their core types and extract only revenue and expense accounts for the dropdown component
  const accountOptions = useMemo(() => {
    if (!accountsData?.data.length) return []

    const groupedAccounts = groupAccounts(accountsData.data)

    return groupedAccounts.filter(account => account.type === 'revenue' || account.type === 'expense')
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

    // Update the copies of the filter options
    setHeaderStartDate(startDate)
    setHeaderEndDate(endDate)
    setHeaderAccounts(selectedAccounts)
    setCompareWithCopy(compareWithOption)
    setNumberOfPeriodsCopy(numberOfPeriods)
    setCompareStartDateCopy(compareStartDate)
    setCompareEndDateCopy(compareEndDate)

    // Change the date text for the ReportHeader
    const startDateText = startDate.format('MMMM DD, YYYY')
    const endDateText = endDate.format('MMMM DD, YYYY')

    startDate.isSame(endDate)
      ? setDateText('For Today')
      : setDateText(`For the period of ${startDateText} to ${endDateText}`)
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
        startDate: headerStartDate.format('YYYY-MM-DD'),
        endDate: headerEndDate.format('YYYY-MM-DD'),
        accountIds: headerAccounts,
        compareWith: compareWithCopy,
        numberOfPeriods: numberOfPeriodsCopy,
        compareStartDate: compareStartDateCopy.format('YYYY-MM-DD'),
        compareEndDate: compareEndDateCopy.format('YYYY-MM-DD'),
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

      {/* Income Statement Header */}
      <CustomPageHeader
        title='Income Statement'
        description='Summary of revenues, expenses, and net profit/loss over a specific period'
      >
        {/* Hide buttons if no accounts have been made */}
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
        subtitle='Configure date ranges and select accounts'
        open={isDrawerOpen}
        onClose={() => setIsDrawerOpen(false)}
      >
        <Stack height='100%' sx={{ p: 5 }} alignItems='flex-start' justifyContent='space-between'>
          <Stack gap={5} alignItems='flex-start' justifyContent='flex-start'>
            {/* Date Range Picker */}
            <Stack gap={1} width='100%' alignItems='flex-start'>
              <Typography variant='h6'>Date Range</Typography>
              <PeriodRangePicker
                withLabels={false}
                disabled={!accountOptions.length || isReportFetching}
                startDate={startDate}
                endDate={endDate}
                setStartDate={setStartDate}
                setEndDate={setEndDate}
              />
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
                <MenuItem value='specific'>Specific Period</MenuItem>
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

            {/* Specific Period Picker */}
            {compareWithOption.length > 0 && compareWithOption === 'specific' && (
              <Stack gap={1} width='100%' alignItems='flex-start'>
                <Typography variant='h6'>Period to Compare With</Typography>
                <PeriodRangePicker
                  withLabels={false}
                  disabled={!accountOptions.length || isReportFetching}
                  startDate={compareStartDate}
                  endDate={compareEndDate}
                  setStartDate={setCompareStartDate}
                  setEndDate={setCompareEndDate}
                />
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
            subText='To get your Income Statement ready, simply set up at least one Revenue and Expense accounts.'
            buttonText='Create Account'
            buttonAction={() => router.push('/accounting/setup/accounts')}
          />
        </>
      ) : !reportData ? (
        <FilterPromptCard
          mainText='Configure report filters or generate the report with pre-selected filters'
          subText='(By default, all accounts have been selected, and the date range has been set to this month)'
        />
      ) : (
        <Stack gap={5} direction='column' alignItems='stretch' justifyContent='flex-start'>
          <ReportHeader
            companyName={userData?.user?.company?.name ?? ''}
            reportType={'Income Statement'}
            dateText={dateText}
            isLoading={isReportFetching}
            handleDownload={handleReportDownload}
            handlePrint={handlePrint}
            status={{
              label: (reportData?.netTotal[0] ?? 0) >= 0 ? 'Net profit' : 'Net loss',
              color: (reportData?.netTotal[0] ?? 0) >= 0 ? 'success' : 'error'
            }}
          />

          <ReportSummaryCards
            loading={isReportFetching}
            items={[
              {
                label: 'Total Revenue',
                value: currencyFormatter.format(reportData?.totalRevenue[0] ?? 0),
                icon: 'bx-trending-up',
                tone: 'success'
              },
              {
                label: 'Total Expenses',
                value: currencyFormatter.format(reportData?.totalExpense[0] ?? 0),
                icon: 'bx-trending-down',
                tone: 'error'
              },
              {
                label: 'Net Profit / Loss',
                value: currencyFormatter.format(reportData?.netTotal[0] ?? 0),
                icon: 'bx-line-chart',
                tone: (reportData?.netTotal[0] ?? 0) >= 0 ? 'info' : 'warning',
                caption: `${reportData?.accounts.length ?? 0} accounts shown`
              }
            ]}
          />

          <PrintableReportFrame
            company={companySettings}
            fallbackCompanyName={userData?.user?.company?.name ?? ''}
            documentTitle='Income Statement'
            subtitle={dateText}
          >
            <IncomeStatementReportBody
              data={reportData}
              isLoading={isReportFetching}
              handleViewingTransaction={account => {
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
        startDate={headerStartDate}
        endDate={headerEndDate}
      />
    </>
  )
}

export default IncomeStatementPage
