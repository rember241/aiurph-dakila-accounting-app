'use client'

import { useEffect, useMemo, useState } from 'react'

import { useRouter } from 'next/navigation'

import { Alert, Button, Checkbox, Paper, Stack, Tooltip, Typography } from '@mui/material'

import type { Dayjs } from 'dayjs'
import dayjs from 'dayjs'

import { toast, ToastContainer } from 'react-toastify'

import type { GridPaginationModel, GridSortModel } from '@mui/x-data-grid'

import CustomPageHeader from '@components/app/CustomPageHeader'
import MultipleAccountSelect from '@components/app/Accounting/MultipleAccountSelect'
import PeriodRangePicker from '@components/app/Accounting/PeriodRangePicker'
import getAccountColumns from '@views/(protected)/accounting/books/general-ledger/componenets/GeneralLedgerAccountColumns'
import { useAccountsTreeQuery } from '@/features/(protected)/accounting/setup/chart-of-accounts/api/accounts.queries'
import { groupAccounts } from '@/utils/journal-entries/groupAccounts'
import { useGeneralLedgerAccountsQuery } from '@/features/(protected)/accounting/books/general-ledger/api/general-ledger.queries'
import CustomTable from '@components/app/CustomTable'
import CustomDrawer from '@components/app/CustomDrawer'
import AccountTransactionsModal from '@components/app/Accounting/AccountTransactionsModal'
import type { GeneralLedgerAccount } from '@/features/(protected)/accounting/books/general-ledger/model/general-ledger.types'
import PromptUserCard from '@components/app/Accounting/PromptUserCard'
import { useMeQuery } from '@/features/auth/api/auth.queries'
import SpinnerCard from '@components/app/Accounting/SpinnerCard'
import ReportSummaryCards from '@components/app/Accounting/ReportSummaryCards'
import { currencyFormatter } from '@/utils/currencyFormatter'

function GeneralLedgerPage() {
  // Used to redirect to the COA page
  const router = useRouter()

  // Filter drawer state
  const [isDrawerOpen, setIsDrawerOpen] = useState<boolean>(false)

  // Transaction view modal state
  const [isModalOpen, setIsModalOpen] = useState<boolean>(false)

  // Account whose transactions will be viewed
  const [accountToView, setAccountToView] = useState<GeneralLedgerAccount | null>(null)

  // Date range and selected accounts
  const [startDate, setStartDate] = useState<Dayjs>(dayjs().startOf('month'))
  const [endDate, setEndDate] = useState<Dayjs>(dayjs().endOf('month'))
  const [selectedAccounts, setSelectedAccounts] = useState<string[]>([])

  const [appliedAccounts, setAppliedAccounts] = useState<string[]>([])

  // For table pagination and sorting
  const [paginationModel, setPaginationModel] = useState<GridPaginationModel>({ page: 0, pageSize: 10 })
  const [sortModel, setSortModel] = useState<GridSortModel>([{ field: 'name', sort: 'asc' }])

  /*
   * Separate start/end dates used for the sub-header.
   * These are decoupled from the filter state to avoid updating
   * the displayed date range before a new report is generated.
   */
  const [subHeaderStartDate, setSubHeaderStartDate] = useState<Dayjs>(dayjs().startOf('month'))
  const [subHeaderEndDate, setSubHeaderEndDate] = useState<Dayjs>(dayjs().endOf('month'))

  // TanStack Query to fetch user data
  const { data: userData } = useMeQuery()

  // TanStack Query hook to fetch accounts data
  const { data: accountsData, isPending: areAccountsFetching } = useAccountsTreeQuery()

  // Group posting accounts by their core types to be used in the account dropdown
  const accountOptions = useMemo(() => {
    if (!accountsData?.data.length) return []

    return groupAccounts(accountsData.data)
  }, [accountsData])

  // Pre-select all accounts when the options have loaded
  useEffect(() => {
    setSelectedAccounts(accountOptions.map(account => account.id))
    setAppliedAccounts(accountOptions.map(account => account.id))
  }, [accountOptions])

  // TanStack Query hook to fetch general ledger data
  const {
    data: ledgerData,
    refetch: fetchLedgerData,
    error: ledgerError,
    isFetching: isLedgerDataFetching
  } = useGeneralLedgerAccountsQuery({
    startDate: subHeaderStartDate.format('YYYY-MM-DD'),
    endDate: subHeaderEndDate.format('YYYY-MM-DD'),
    accountIds: appliedAccounts
  })

  // Return the user to the first page in the table when the ledger display has been updated
  useEffect(() => {
    setPaginationModel(prev => ({ ...prev, page: 0 }))
  }, [ledgerData])

  const ledgerSummary = useMemo(
    () =>
      (ledgerData ?? []).reduce(
        (summary, account) => ({
          debit: summary.debit + account.debit,
          credit: summary.credit + account.credit,
          transactions: summary.transactions + account.transactions.length
        }),
        { debit: 0, credit: 0, transactions: 0 }
      ),
    [ledgerData]
  )

  // Get columns
  const columns = useMemo(
    () =>
      getAccountColumns({
        onView: row => {
          setAccountToView(row)
          setIsModalOpen(true)
        }
      }),
    []
  )

  // Function to handle updating the display
  const handleUpdateDisplay = async () => {
    toast.dismiss()

    if (startDate.isAfter(endDate)) toast.error('Start date cannot be set to a time after the end date')
    else {
      if (startDate.isSame(subHeaderStartDate) && endDate.isSame(subHeaderEndDate) &&
          JSON.stringify(selectedAccounts) === JSON.stringify(appliedAccounts)) {
        await fetchLedgerData()
      }

      setAppliedAccounts([...selectedAccounts])
      // Update the date ranges in the header
      setSubHeaderStartDate(startDate)
      setSubHeaderEndDate(endDate)
    }
  }

  return (
    <>
      {/* Toast Notification */}
      <ToastContainer stacked autoClose={2500} position='top-center' />

      {/* General Ledger Header */}
      <CustomPageHeader title='General Ledger' description='View financial transactions across all accounts'>
        {/* Hide buttons if there are no accounts to choose from */}
        {!areAccountsFetching && accountOptions.length !== 0 && (
          <Stack gap={2} direction='row' alignItems='center' justifyContent='flex-end'>
            {/* Filter Button */}
            <Button
              disabled={areAccountsFetching || isLedgerDataFetching}
              color='secondary'
              onClick={() => setIsDrawerOpen(true)}
              startIcon={<i className='bx-slider' />}
            >
              Filters
            </Button>

            {/* Update Display Button */}
            <Tooltip
              placement='bottom-end'
              title='No accounts have been selected'
              disableFocusListener={selectedAccounts.length > 0}
              disableHoverListener={selectedAccounts.length > 0}
            >
              <span>
                <Button
                  disabled={areAccountsFetching || isLedgerDataFetching || selectedAccounts.length === 0}
                  variant='contained'
                  onClick={handleUpdateDisplay}
                >
                  Update
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
              <Typography variant='h6'>Data Range</Typography>
              <PeriodRangePicker
                withLabels={false}
                disabled={areAccountsFetching || isLedgerDataFetching}
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
                  disabled={areAccountsFetching || isLedgerDataFetching}
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
      ) : accountOptions.length === 0 ? (
        <>
          {/* Prompt user to make an account if no accounts can be selected */}
          <PromptUserCard
            icon='bx-book'
            mainText='No accounts yet'
            subText='Before we can set up your general ledger, please make sure to create at least one account first.'
            buttonText='Create Account'
            buttonAction={() => router.push('/accounting/setup/accounts')}
          />
        </>
      ) : ledgerError ? (
        <Alert severity='error' action={<Button onClick={() => fetchLedgerData()}>Retry</Button>}>
          Unable to retrieve the general ledger. Please try again.
        </Alert>
      ) : !ledgerData ? (
        <SpinnerCard />
      ) : (
        <>
          <ReportSummaryCards
            loading={isLedgerDataFetching}
            items={[
              {
                label: 'Posting Accounts',
                value: ledgerData.length,
                icon: 'bx-list-ul',
                tone: 'primary',
                caption: 'Accounts included in this ledger'
              },
              {
                label: 'Total Debit',
                value: currencyFormatter.format(ledgerSummary.debit),
                icon: 'bx-down-arrow-circle',
                tone: 'info'
              },
              {
                label: 'Total Credit',
                value: currencyFormatter.format(ledgerSummary.credit),
                icon: 'bx-up-arrow-circle',
                tone: 'success'
              },
              {
                label: 'Transactions',
                value: ledgerSummary.transactions,
                icon: 'bx-transfer',
                tone: 'warning'
              }
            ]}
          />

          {/* Sub-header */}
          <Paper variant='outlined' sx={{ mt: 5, mb: 5, p: 5, borderRadius: 3 }}>
            <Stack
              direction={{ xs: 'column', sm: 'row' }}
              gap={2}
              alignItems={{ sm: 'center' }}
              justifyContent='space-between'
            >
              <Stack alignItems='flex-start' justifyContent='center'>
                <Typography variant='h6'>Account Transactions</Typography>
                <Typography variant='subtitle2' color='text.secondary'>
                  {userData?.user?.company?.name}
                </Typography>
              </Stack>
              <Typography variant='subtitle2' color='text.secondary'>
                {subHeaderStartDate.isSame(subHeaderEndDate)
                  ? 'For today'
                  : `From ${subHeaderStartDate.format('MMMM DD, YYYY')} to ${subHeaderEndDate.format('MMMM DD, YYYY')}`}
              </Typography>
            </Stack>
          </Paper>

          {/* General Ledger Data */}
          <Paper>
            <CustomTable
              loading={isLedgerDataFetching}
              columns={columns}
              rows={ledgerData ?? []}
              paginationModel={paginationModel}
              onPaginationModelChange={setPaginationModel}
              sortModel={sortModel}
              onSortModelChange={setSortModel}
            />
          </Paper>
        </>
      )}

      {/* Transaction Vew Modal */}
      <AccountTransactionsModal
        open={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        account={accountToView}
        startDate={subHeaderStartDate}
        endDate={subHeaderEndDate}
      />
    </>
  )
}

export default GeneralLedgerPage
