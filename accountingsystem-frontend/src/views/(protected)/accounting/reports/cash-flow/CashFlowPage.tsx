'use client'

import { useEffect, useRef, useState } from 'react'

import { useRouter } from 'next/navigation'

import type { Dayjs } from 'dayjs'
import dayjs from 'dayjs'

import { toast, ToastContainer } from 'react-toastify'

import { Button, Paper, Stack } from '@mui/material'

import { useCashFlowQuery } from '@/features/(protected)/accounting/reports/cash-flow/api/cash-flow.queries'
import type { CashFlowData } from '@/features/(protected)/accounting/reports/cash-flow/model/cash-flow.types'
import { useMeQuery } from '@/features/auth/api/auth.queries'
import { useAccountsTreeQuery } from '@/features/(protected)/accounting/setup/chart-of-accounts/api/accounts.queries'
import type { AccountTreeNode } from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'
import CustomPageHeader from '@components/app/CustomPageHeader'

import PromptUserCard from '@components/app/Accounting/PromptUserCard'
import CashFlowReportView from '@views/(protected)/accounting/reports/cash-flow/components/CashFlowReportView'
import SpinnerCard from '@components/app/Accounting/SpinnerCard'
import FilterPromptCard from '@components/app/Accounting/FilterPromptCard'
import PrintableReportFrame from '@components/app/Accounting/PrintableReportFrame'
import ReportHeader from '@components/app/Accounting/ReportHeader'
import ReportSummaryCards from '@components/app/Accounting/ReportSummaryCards'
import { useCompanySettingsQuery } from '@/features/(protected)/settings/api/settings.queries'
import CashFlowFiltersDrawer from '@views/(protected)/accounting/reports/cash-flow/components/CashFlowFiltersDrawer'
import { currencyFormatter } from '@/utils/currencyFormatter'

type CashFlowComparisonMode = 'previous_period' | 'previous_year' | 'custom'

function CashFlowPage() {
  // Used to redirect to the COA page
  const router = useRouter()

  // Filter drawer state
  const [isDrawerOpen, setIsDrawerOpen] = useState(false)

  // States for date ranges and selected account IDs
  const [startDate, setStartDate] = useState<Dayjs>(dayjs().startOf('month'))
  const [endDate, setEndDate] = useState<Dayjs>(dayjs().endOf('month'))

  // Separate start/end dates used for the CashFlowReportView header
  const [headerStartDate, setHeaderStartDate] = useState<Dayjs>(dayjs().startOf('month'))
  const [headerEndDate, setHeaderEndDate] = useState<Dayjs>(dayjs().endOf('month'))
  const [compareEnabled, setCompareEnabled] = useState(false)
  const [comparisonMode, setComparisonMode] = useState<CashFlowComparisonMode>('previous_period')
  const [comparisonStartDate, setComparisonStartDate] = useState<Dayjs>(dayjs().subtract(1, 'month').startOf('month'))
  const [comparisonEndDate, setComparisonEndDate] = useState<Dayjs>(dayjs().subtract(1, 'month').endOf('month'))
  const [generatedReportData, setGeneratedReportData] = useState<CashFlowData | null>(null)

  // TanStack Query to fetch user data
  const { data: userData } = useMeQuery()
  const { data: companySettings } = useCompanySettingsQuery()

  // TanStack Query to fetch Cash Flow report data
  const {
    data: reportData,
    refetch: fetchReportData,
    isFetching: isReportFetching
  } = useCashFlowQuery({
    startDate: startDate.format('YYYY-MM-DD'),
    endDate: endDate.format('YYYY-MM-DD'),
    compareEnabled,
    comparisonMode,
    comparisonStartDate: comparisonStartDate.format('YYYY-MM-DD'),
    comparisonEndDate: comparisonEndDate.format('YYYY-MM-DD')
  })

  // TanStack Query hook to fetch accounts
  const { data: accountsData, isFetching: areAccountsFetching } = useAccountsTreeQuery()

  // Recursive utility function to check for posting accounts under 'Cash In Hand' or 'Bank Accounts'
  function hasCashPostingAccounts(nodes: AccountTreeNode[], inTarget = false): boolean {
    const targetGroupNames = [
      'Cash In Hand',
      'Cash on Hand',
      'Petty Cash',
      'Bank Accounts',
      'Bank Account',
      'Cash in Bank',
      'Cash and Cash Equivalents'
    ]

    const targetDetailTypeNames = targetGroupNames

    return nodes.some(node => {
      const matchesCashGroup = targetGroupNames.includes(node.name)
      const matchesCashDetailType = targetDetailTypeNames.includes(node.detail_type_name ?? '')
      const isTarget = inTarget || matchesCashGroup || matchesCashDetailType

      // Found a posting account within a target branch
      if (isTarget && node.is_posting && node.status === 'active' && node.type === 'asset') return true

      // Recursively check children
      return !!node.children?.length && hasCashPostingAccounts(node.children, isTarget)
    })
  }

  // Flag to check if there are existing posting cash accounts to fetch the Cash Flow data
  const areAccountsFound = hasCashPostingAccounts(accountsData?.data ?? [])
  const visibleReportData = generatedReportData ?? reportData

  // Handle report generation. Runs silently (no toast) for the automatic first-load preview
  const handleReportGeneration = async (options?: { silent?: boolean }) => {
    let result

    if (options?.silent) {
      try {
        result = await fetchReportData({ throwOnError: true })
      } catch {
        return
      }
    } else {
      toast.dismiss()

      result = await toast.promise(fetchReportData({ throwOnError: true }), {
        pending: 'Generating report...',
        success: 'Report generated!',
        error: {
          render: ({ data }: { data: Error }) => data?.message ?? 'Error generating report'
        }
      })
    }

    if (result.data) {
      setGeneratedReportData(result.data)
    }

    // Update the displayed date range in the CashFlowReportView component
    setHeaderStartDate(startDate)
    setHeaderEndDate(endDate)
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

  const handlePrint = () => {
    window.print()
  }

  return (
    <>
      {/* Toast Notification */}
      <ToastContainer stacked autoClose={2500} position='top-center' />

      {/* Cash Flow Header */}
      <CustomPageHeader
        title='Cash Flow'
        description='Shows movement of cash across business activities over a selected period'
      >
        {/* Hide buttons if there are no cash posting accounts */}
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
      <CashFlowFiltersDrawer
        open={isDrawerOpen}
        disabled={areAccountsFetching || isReportFetching}
        startDate={startDate}
        endDate={endDate}
        compareEnabled={compareEnabled}
        comparisonMode={comparisonMode}
        comparisonStartDate={comparisonStartDate}
        comparisonEndDate={comparisonEndDate}
        onClose={() => setIsDrawerOpen(false)}
        onGenerate={handleReportGeneration}
        setStartDate={setStartDate}
        setEndDate={setEndDate}
        setCompareEnabled={setCompareEnabled}
        setComparisonMode={setComparisonMode}
        setComparisonStartDate={setComparisonStartDate}
        setComparisonEndDate={setComparisonEndDate}
      />

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
            subText='To view your Cash Flow Statement, please set up a cash posting account.'
            buttonText='Create Account'
            buttonAction={() => router.push('/accounting/setup/accounts')}
          />
        </>
      ) : !visibleReportData ? (
        <FilterPromptCard
          mainText='Configure report filters or generate the report with pre-selected filters'
          subText='(By default, the date range has been set to this month)'
        />
      ) : (
        <Stack gap={5} direction='column' alignItems='stretch' justifyContent='flex-start'>
          <ReportHeader
            companyName={userData?.user?.company?.name ?? ''}
            reportType='Cash Flow Statement'
            dateText={`${headerStartDate.format('MMMM DD, YYYY')} to ${headerEndDate.format('MMMM DD, YYYY')}`}
            isLoading={isReportFetching}
            handlePrint={handlePrint}
            status={{
              label:
                Math.abs(visibleReportData.reconciliationDifference ?? 0) < 0.01 ? 'Reconciled' : 'Review required',
              color: Math.abs(visibleReportData.reconciliationDifference ?? 0) < 0.01 ? 'success' : 'warning'
            }}
          />

          <ReportSummaryCards
            loading={isReportFetching}
            items={[
              {
                label: 'Opening Cash',
                value: currencyFormatter.format(visibleReportData.openingBalance),
                icon: 'bx-wallet-alt',
                tone: 'primary'
              },
              {
                label: 'Net Change',
                value: currencyFormatter.format(visibleReportData.netChange),
                icon: 'bx-transfer',
                tone: visibleReportData.netChange >= 0 ? 'success' : 'warning'
              },
              {
                label: 'Ending Cash',
                value: currencyFormatter.format(visibleReportData.endingBalance),
                icon: 'bx-money',
                tone: 'info',
                caption: visibleReportData.unmappedTransactions?.length
                  ? `${visibleReportData.unmappedTransactions.length} movements need review`
                  : 'All classified cash movements are included'
              }
            ]}
          />

          <PrintableReportFrame
            company={companySettings}
            fallbackCompanyName={userData?.user?.company?.name ?? ''}
            documentTitle='Cash Flow Statement'
            subtitle={`${headerStartDate.format('MMMM DD, YYYY')} to ${headerEndDate.format('MMMM DD, YYYY')}`}
          >
            <Paper sx={{ p: 5 }}>
              <CashFlowReportView
                data={visibleReportData}
                companyName={userData?.user?.company?.name ?? ''}
                loading={isReportFetching}
                startDate={headerStartDate}
                endDate={headerEndDate}
              />
            </Paper>
          </PrintableReportFrame>
        </Stack>
      )}
    </>
  )
}

export default CashFlowPage
