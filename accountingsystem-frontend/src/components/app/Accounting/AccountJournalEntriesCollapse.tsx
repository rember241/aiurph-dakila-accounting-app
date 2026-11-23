import { Box, Collapse, Table, TableBody, TableCell, TableHead, TableRow, Typography } from '@mui/material'

import type { AccountTransaction } from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'
import type { CashFlowTransaction } from '@/features/(protected)/accounting/reports/cash-flow/model/cash-flow.types'
import { currencyFormatter } from '@/utils/currencyFormatter'

// Shared wrapper used by both variants below to render the expandable panel underneath a report row
function CollapsibleRow({
  open,
  colSpan,
  isEmpty,
  children
}: {
  open: boolean
  colSpan: number
  isEmpty: boolean
  children: React.ReactNode
}) {
  return (
    <TableRow>
      <TableCell colSpan={colSpan} sx={{ p: 0, borderBottom: open ? undefined : 'none' }}>
        <Collapse in={open} timeout='auto' unmountOnExit>
          <Box
            sx={{
              my: 2,
              ml: 10,
              mr: 2,
              border: theme => `1px solid ${theme.palette.divider}`,
              borderRadius: 1,
              overflow: 'hidden'
            }}
          >
            {isEmpty ? (
              <Typography variant='body2' color='text.secondary' sx={{ p: 3 }}>
                No journal entries found for this account within the selected period.
              </Typography>
            ) : (
              children
            )}
          </Box>
        </Collapse>
      </TableCell>
    </TableRow>
  )
}

// Props for the account-level journal entries collapse (Trial Balance, Income Statement, Balance Sheet)
interface AccountJournalEntriesCollapseProps {
  open: boolean
  colSpan: number
  transactions: AccountTransaction[]
}

/**
 * Expandable panel showing the individual journal entries behind an account's
 * balance in a report. Rendered as a full-width row directly beneath the account.
 */
export function AccountJournalEntriesCollapse({ open, colSpan, transactions }: AccountJournalEntriesCollapseProps) {
  return (
    <CollapsibleRow open={open} colSpan={colSpan} isEmpty={transactions.length === 0}>
      <Table size='small'>
        <TableHead>
          <TableRow sx={{ '& .MuiTableCell-root': { backgroundColor: theme => theme.palette.action.hover, fontWeight: 'bold' } }}>
            <TableCell>Date</TableCell>
            <TableCell>Journal #</TableCell>
            <TableCell>Description</TableCell>
            <TableCell align='right'>Debit</TableCell>
            <TableCell align='right'>Credit</TableCell>
            <TableCell align='right'>Balance</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {transactions.map(transaction => (
            <TableRow key={transaction.id} hover>
              <TableCell>{transaction.date}</TableCell>
              <TableCell>{transaction.journalNumber}</TableCell>
              <TableCell>{transaction.description.length ? transaction.description : '-'}</TableCell>
              <TableCell align='right'>
                {transaction.debit === 0 ? '-' : currencyFormatter.format(transaction.debit)}
              </TableCell>
              <TableCell align='right'>
                {transaction.credit === 0 ? '-' : currencyFormatter.format(transaction.credit)}
              </TableCell>
              <TableCell align='right'>{currencyFormatter.format(transaction.balance)}</TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </CollapsibleRow>
  )
}

// Props for the cash flow line-item journal entries collapse
interface CashFlowJournalEntriesCollapseProps {
  open: boolean
  colSpan: number
  transactions: CashFlowTransaction[]
}

/**
 * Expandable panel showing the individual journal entries behind a Cash Flow line item.
 * Cash flow lines net a single signed amount per journal entry rather than separate
 * debit/credit columns, so this uses a lighter table shape than the account variant above.
 */
export function CashFlowJournalEntriesCollapse({ open, colSpan, transactions }: CashFlowJournalEntriesCollapseProps) {
  return (
    <CollapsibleRow open={open} colSpan={colSpan} isEmpty={transactions.length === 0}>
      <Table size='small'>
        <TableHead>
          <TableRow sx={{ '& .MuiTableCell-root': { backgroundColor: theme => theme.palette.action.hover, fontWeight: 'bold' } }}>
            <TableCell>Date</TableCell>
            <TableCell>Journal #</TableCell>
            <TableCell>Description</TableCell>
            <TableCell align='right'>Amount</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {transactions.map(transaction => (
            <TableRow key={transaction.id} hover>
              <TableCell>{transaction.date}</TableCell>
              <TableCell>{transaction.journalNumber}</TableCell>
              <TableCell>{transaction.description.length ? transaction.description : '-'}</TableCell>
              <TableCell align='right'>{currencyFormatter.format(transaction.amount)}</TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </CollapsibleRow>
  )
}
