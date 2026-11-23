'use client'

import { useEffect, useMemo, useRef, useState } from 'react'

import { useRouter } from 'next/navigation'

import type { Dayjs } from 'dayjs'
import dayjs from 'dayjs'

import { toast, ToastContainer } from 'react-toastify'

import { Button, Stack, Typography } from '@mui/material'

import { DatePicker, LocalizationProvider } from '@mui/x-date-pickers'

import { AdapterDayjs } from '@mui/x-date-pickers/AdapterDayjs'

import type { TrialBalanceAccount } from '@/features/(protected)/accounting/reports/trial-balance/model/trial-balance.types'
import { useMeQuery } from '@/features/auth/api/auth.queries'
import {
  useDownloadTrialBalance,
  useTrialBalanceQuery
} from '@/features/(protected)/accounting/reports/trial-balance/api/trial-balance.queries'
import { useAccountsTreeQuery } from '@/features/(protected)/accounting/setup/chart-of-accounts/api/accounts.queries'
import CustomPageHeader from '@components/app/CustomPageHeader'
import type { AccountTreeNode } from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'

import CustomDrawer from '@components/app/CustomDrawer'
import AccountTransactionsModal from '@components/app/Accounting/AccountTransactionsModal'
import PromptUserCard from '@components/app/Accounting/PromptUserCard'
import TrialBalanceReportBody from '@views/(protected)/accounting/reports/trial-balance/components/TrialBalanceReportBody'
import SpinnerCard from '@components/app/Accounting/SpinnerCard'
import FilterPromptCard from '@components/app/Accounting/FilterPromptCard'
import ReportHeader from '@components/app/Accounting/ReportHeader'
import PrintableReportFrame from '@components/app/Accounting/PrintableReportFrame'
import ReportSummaryCards from '@components/app/Accounting/ReportSummaryCards'
import { useCompanySettingsQuery } from '@/features/(protected)/settings/api/settings.queries'
import { currencyFormatter } from '@/utils/currencyFormatter'

function TrialBalancePage() {
  // Used to redirect to the COA page
  const router = useRouter()

  // Filter drawer state
  const [isDrawerOpen, setIsDrawerOpen] = useState(false)

  // States for selected accounts and the date for the report
  const [date, setDate] = useState<Dayjs>(dayjs())

  // Separate date used for the ReportHeader. Also used as
  // a parameter when downloading the trial balance report
  const [headerDate, setHeaderDate] = useState<Dayjs>(dayjs())
  const [dateText, setDateText] = useState<string>('')

  // Account transaction modal state and content
  const [isModalOpen, setIsModalOpen] = useState<boolean>(false)
  const [accountToView, setAccountToView] = useState<TrialBalanceAccount | null>(null)

  // TanStack Query to fetch user data
  const { data: userData } = useMeQuery()
  const { data: companySettings } = useCompanySettingsQuery()

  // TanStack Query hook to fetch accounts
  const { data: accountsData, isFetching: areAccountsFetching } = useAccountsTreeQuery()

  // TanStack Query hook to download the trial balance
  const { mutateAsync: downloadReport } = useDownloadTrialBalance()

  // TanStack Query hook to fetch Trial Balance data
  const {
    data: reportData,
    refetch: fetchReportData,
    isFetching: isReportFetching
  } = useTrialBalanceQuery({
    date: date.format('YYYY-MM-DD')
  })

  // Recursive utility function to check for posting accounts
  function hasPostingAccount(nodes: AccountTreeNode[]): boolean {
    for (const node of nodes) {
      if (node.is_posting) return true

      if (node.children?.length) {
        if (hasPostingAccount(node.children)) return true
      }
    }

    return false
  }

  // Flag to check if there are existing posting accounts to perform the trial balance
  const areAccountsFound = hasPostingAccount(accountsData?.data ?? [])

  const totals = useMemo(
    () =>
      (reportData ?? []).reduce(
        (summary, account) => ({
          debit: summary.debit + account.debit,
          credit: summary.credit + account.credit
        }),
        { debit: 0, credit: 0 }
      ),
    [reportData]
  )

  const isBalanced = Math.abs(totals.debit - totals.credit) < 0.01

  // Handle report generation. Runs silently (no toast) for the automatic first-load preview
  const handleReportGeneration = async (options?: { silent?: boolean }) => {
    if (options?.silent) {
      await fetchReportData()
    } else {
      toast.dismiss()

      await toast.promise(fetchReportData, {
        pending: 'Generating report...',
        success: 'Report generated!',
        error: {
          render: ({ data }: { data: any }) => {
            const backendMessage = data?.response?.data?.message

            return backendMessage ?? `Error generating report`
          }
        }
      })
    }

    // Update the date text in the ReportHeader component
    setHeaderDate(date)
    const tempDateText = date.format('MMMM DD, YYYY')

    date.isSame(dayjs(), 'day') ? setDateText(`As of Today (${tempDateText})`) : setDateText(`As of ${tempDateText}`)
  }

  // Automatically generate a preview report on first load using the default filters
  const hasAutoGeneratedRef = useRef(false)

  useEffect(() => {
    if (!hasAutoGeneratedRef.current && areAccountsFound && !areAccountsFetching) {
      hasAutoGeneratedRef.current = true
      handleReportGeneration({ silent: true })
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [areAccountsFound, areAccountsFetching])

  // Handle report download
  const handleReportDownload = async (fileType: 'pdf' | 'csv' | 'xlsx') => {
    toast.dismiss()

    await toast.promise(
      downloadReport({
        date: headerDate.format('YYYY-MM-DD'),
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

      {/* Trial Balance Header */}
      <CustomPageHeader title='Trial Balance' description='Ensure your books are balanced at a specific point in time'>
        {/* Hide buttons if there are no accounts made */}
        {areAccountsFound && !areAccountsFetching && (
          <Stack gap={2} direction='row' alignItems='center' justifyContent='flex-end'>
            {/* Filter Button */}
            <Button
              color='secondary'
              startIcon={<i className='bx-slider' />}
              onClick={() => setIsDrawerOpen(true)}
              disabled={areAccountsFetching || isReportFetching}
            >
              Filters
            </Button>

            {/* Generate Report Button */}
            <Button
              color='primary'
              variant='contained'
              onClick={() => handleReportGeneration()}
              disabled={areAccountsFetching || isReportFetching}
            >
              Generate
            </Button>
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
                  disabled={areAccountsFetching || isReportFetching}
                />
              </LocalizationProvider>
            </Stack>
          </Stack>

          {/* Close Button */}
          <Button variant='contained' onClick={() => setIsDrawerOpen(false)}>
            Close
          </Button>
        </Stack>
      </CustomDrawer>

      {areAccountsFetching ? (
        <>
          {/* Show spinner if accounts are still being fetched */}
          <SpinnerCard />
        </>
      ) : !areAccountsFound ? (
        <>
          {/* Prompt user to make an account if no accounts can be selected */}
          <PromptUserCard
            icon='bx-book'
            mainText='No accounts yet'
            subText='You’ll need at least one account before generating a trial balance. Create one to get started.'
            buttonText='Create Account'
            buttonAction={() => router.push('/accounting/setup/accounts')}
          />
        </>
      ) : !reportData ? (
        <FilterPromptCard
          mainText='Configure report filters or generate the report with pre-selected filters'
          subText='(By default, the date has been set to today)'
        />
      ) : (
        <Stack gap={5} direction='column' alignItems='stretch' justifyContent='flex-start'>
          <ReportHeader
            companyName={userData?.user?.company?.name ?? ''}
            reportType='Trial Balance'
            dateText={dateText}
            isLoading={isReportFetching}
            handleDownload={handleReportDownload}
            handlePrint={handlePrint}
            status={{ label: isBalanced ? 'Balanced' : 'Out of balance', color: isBalanced ? 'success' : 'error' }}
          />

          <ReportSummaryCards
            loading={isReportFetching}
            items={[
              {
                label: 'Posting Accounts',
                value: reportData?.length ?? 0,
                icon: 'bx-list-ul',
                tone: 'primary',
                caption: 'Accounts included in this trial balance'
              },
              {
                label: 'Total Debit',
                value: currencyFormatter.format(totals.debit),
                icon: 'bx-down-arrow-circle',
                tone: 'info'
              },
              {
                label: 'Total Credit',
                value: currencyFormatter.format(totals.credit),
                icon: 'bx-up-arrow-circle',
                tone: 'success'
              }
            ]}
          />

          <PrintableReportFrame
            company={companySettings}
            fallbackCompanyName={userData?.user?.company?.name ?? ''}
            documentTitle='Trial Balance'
            subtitle={dateText}
          >
            <TrialBalanceReportBody
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
        date={headerDate}
      />
    </>
  )
}

export default TrialBalancePage
