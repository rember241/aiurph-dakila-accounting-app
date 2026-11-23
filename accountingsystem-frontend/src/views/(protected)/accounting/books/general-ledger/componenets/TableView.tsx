import { Table, TableBody, TableCell, TableFooter, TableHead, TableRow, Typography } from '@mui/material'

import type { Decimal as DecimalType } from 'decimal.js'

import { default as DecimalClass } from 'decimal.js'

import { currencyFormatter } from '@/utils/journal-entries/currencyFormatter'

import type { AccountTransaction } from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'

// Props for this functional component
interface TableProps {
  totals: {
    debit: DecimalType
    credit: DecimalType
  } // An object that contains the computed debits and credits
  normalBalance: string
  openingBalance: number
  transactionData: AccountTransaction[]
}

function TableView({ totals, normalBalance, openingBalance, transactionData }: TableProps) {
  const endingBalance =
    normalBalance === 'debit'
      ? openingBalance + totals.debit.minus(totals.credit).toNumber()
      : openingBalance + totals.credit.minus(totals.debit).toNumber()

  return (
    <Table>
      {/* Table Header */}
      <TableHead>
        <TableRow>
          <TableCell>
            <strong>DATE</strong>
          </TableCell>
          <TableCell>
            <strong>JOURNAL #</strong>
          </TableCell>
          <TableCell>
            <strong>DESCRIPTION</strong>
          </TableCell>
          <TableCell align='right'>
            <strong>DEBIT</strong>
          </TableCell>
          <TableCell align='right'>
            <strong>CREDIT</strong>
          </TableCell>
          <TableCell align='right'>
            <strong>BALANCE</strong>
          </TableCell>
        </TableRow>
      </TableHead>

      {/* Table Body */}
      <TableBody>
        <TableRow>
          <TableCell>-</TableCell>
          <TableCell>-</TableCell>
          <TableCell>Opening Balance</TableCell>
          <TableCell align='right'>-</TableCell>
          <TableCell align='right'>-</TableCell>
          <TableCell align='right'>{currencyFormatter.format(openingBalance)}</TableCell>
        </TableRow>

        {transactionData.map(transaction => (
          <TableRow key={transaction.id}>
            <TableCell>{transaction.date}</TableCell>
            <TableCell>{transaction.journalNumber}</TableCell>
            <TableCell>{transaction.description.length ? transaction.description : '-'}</TableCell>
            <TableCell align='right'>
              {transaction.debit === 0 ? '-' : currencyFormatter.format(new DecimalClass(transaction.debit).toNumber())}
            </TableCell>
            <TableCell align='right'>
              {transaction.credit === 0
                ? '-'
                : currencyFormatter.format(new DecimalClass(transaction.credit).toNumber())}
            </TableCell>
            <TableCell align='right'>{currencyFormatter.format(transaction.balance)}</TableCell>
          </TableRow>
        ))}
      </TableBody>

      {/* Table Footer */}
      <TableFooter>
        <TableRow>
          <TableCell colSpan={3}>
            <Typography variant='subtitle2' sx={{ fontWeight: 'bold', color: 'white' }}>
              Ending Balance
            </Typography>
          </TableCell>
          <TableCell align='right'>
            <Typography variant='subtitle2' sx={{ fontWeight: 'bold', color: 'white' }}>
              {currencyFormatter.format(totals.debit.toNumber())}
            </Typography>
          </TableCell>
          <TableCell align='right'>
            <Typography variant='subtitle2' sx={{ fontWeight: 'bold', color: 'white' }}>
              {currencyFormatter.format(totals.credit.toNumber())}
            </Typography>
          </TableCell>
          <TableCell align='right'>
            <Typography variant='subtitle2' sx={{ fontWeight: 'bold', color: 'white' }}>
              {currencyFormatter.format(endingBalance)}
            </Typography>
          </TableCell>
        </TableRow>
      </TableFooter>
    </Table>
  )
}

export default TableView
