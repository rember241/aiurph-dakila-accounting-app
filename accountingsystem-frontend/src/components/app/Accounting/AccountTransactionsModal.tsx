import { useMemo, useState } from 'react'

import {
  Button,
  ButtonGroup,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Paper,
  Stack,
  Typography
} from '@mui/material'

import type { Dayjs } from 'dayjs'

import Decimal from 'decimal.js'

import type { GeneralLedgerAccount } from '@/features/(protected)/accounting/books/general-ledger/model/general-ledger.types'
import TableView from '@views/(protected)/accounting/books/general-ledger/componenets/TableView'

import TAccountsView from '@views/(protected)/accounting/books/general-ledger/componenets/TAccountsView'
import type { IncomeStatementAccount } from '@/features/(protected)/accounting/reports/income-statement/model/income-statement.types'
import type { BalanceSheetAccount } from '@/features/(protected)/accounting/reports/balance-sheet/model/balance-sheet.types'
import type { TrialBalanceAccount } from '@/features/(protected)/accounting/reports/trial-balance/model/trial-balance.types'
import { currencyFormatter } from '@/utils/journal-entries/currencyFormatter'

// Props for this functional component
interface ModalProps {
  open: boolean
  onClose: () => void
  account: GeneralLedgerAccount | IncomeStatementAccount | BalanceSheetAccount | TrialBalanceAccount | null
  date?: Dayjs // Used in the Balance Sheet page
  startDate?: Dayjs
  endDate?: Dayjs
}

function AccountTransactionsModal({ open, onClose, account, date, startDate, endDate }: ModalProps) {
  // View mode
  const [viewMode, setViewMode] = useState<'table' | 't-accounts'>('table')

  // Computes the total debits and credits
  const totals = useMemo(() => {
    return account?.transactions.reduce(
      (acc, item) => {
        acc.debit = acc.debit.plus(item.debit)
        acc.credit = acc.credit.plus(item.credit)

        return acc
      },
      {
        debit: new Decimal(0),
        credit: new Decimal(0)
      }
    )
  }, [account])

  const debitTotal = (totals ?? { debit: new Decimal(0), credit: new Decimal(0) }).debit.toNumber()
  const creditTotal = (totals ?? { debit: new Decimal(0), credit: new Decimal(0) }).credit.toNumber()
  const openingBalance = account && 'openingBalance' in account ? (account.openingBalance ?? 0) : 0
  const hasTransactions = (account?.transactions.length ?? 0) > 0
  const endingBalance = account && 'balance' in account ? account.balance : openingBalance

  return (
    <Dialog fullWidth maxWidth='xl' open={open} onClose={onClose}>
      <DialogTitle>Account Transactions</DialogTitle>

      <DialogContent>
        <Stack width='100%' alignItems='center' justifyContent='flex-start'>
          {/* Sub-header Text and View Mode Buttons */}
          <Paper elevation={3} sx={{ width: '100%', mb: 5, p: 5 }}>
            <Stack direction='row' alignItems='center' justifyContent='space-between'>
              {/* Sub-header Text */}
              <Stack alignItems='flex-start' justifyContent='center'>
                <Typography variant='h6'>{`(${account?.code}) ${account?.name}`}</Typography>
                <Typography variant='subtitle1'>
                  {date
                    ? `As of ${date.format('MMMM DD, YYYY')}`
                    : startDate?.isSame(endDate)
                      ? `Transactions from today (${startDate.format('MMMM DD, YYYY')})`
                      : `Transactions from ${startDate?.format('MMMM DD, YYYY')} to ${endDate?.format('MMMM DD, YYYY')}`}
                </Typography>
                {account && 'openingBalance' in account && (
                  <Typography variant='body2' color='text.secondary'>
                    {`Opening Balance: ${currencyFormatter.format(openingBalance)} | Period Debits: ${currencyFormatter.format(debitTotal)} | Period Credits: ${currencyFormatter.format(creditTotal)} | Ending Balance: ${currencyFormatter.format(endingBalance)}`}
                  </Typography>
                )}
              </Stack>

              {/* View Mode Buttons */}
              <ButtonGroup>
                <Button
                  disabled={!hasTransactions}
                  variant={viewMode === 'table' ? 'contained' : 'outlined'}
                  onClick={() => setViewMode('table')}
                  startIcon={<i className={'bx-columns'} />}
                >
                  Table View
                </Button>
                <Button
                  disabled={!hasTransactions}
                  variant={viewMode === 't-accounts' ? 'contained' : 'outlined'}
                  onClick={() => setViewMode('t-accounts')}
                  startIcon={<i className={'bx-sidebar'} />}
                >
                  T-Accounts View
                </Button>
              </ButtonGroup>
            </Stack>
          </Paper>

          {/* Table or T-account View */}
          {!hasTransactions && openingBalance === 0 ? (
            <Paper elevation={3} sx={{ width: '100%' }}>
              <Stack sx={{ p: 25 }} alignItems='center'>
                <Typography>
                  No transactions have been made with this account within the specified date range
                </Typography>
              </Stack>
            </Paper>
          ) : (
            <Paper elevation={3} sx={{ width: '100%', p: 5 }}>
              {viewMode === 'table' || !hasTransactions ? (
                <TableView
                  totals={totals ?? { debit: new Decimal(0), credit: new Decimal(0) }}
                  openingBalance={openingBalance}
                  transactionData={account?.transactions ?? []}
                  normalBalance={account?.normalBalance ?? ''}
                />
              ) : (
                <TAccountsView
                  totals={totals ?? { debit: new Decimal(0), credit: new Decimal(0) }}
                  transactionData={account?.transactions ?? []}
                />
              )}
            </Paper>
          )}
        </Stack>
      </DialogContent>

      <DialogActions>
        <Button variant='contained' color='secondary' onClick={onClose}>
          Close
        </Button>
      </DialogActions>
    </Dialog>
  )
}

export default AccountTransactionsModal
