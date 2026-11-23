import { Fragment, useState } from 'react'

import {
  Chip,
  IconButton,
  Link,
  Paper,
  Skeleton,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableFooter,
  TableHead,
  TableRow,
  Typography
} from '@mui/material'

import type {
  GenerateIncomeStatementResponse,
  IncomeStatementAccount
} from '@/features/(protected)/accounting/reports/income-statement/model/income-statement.types'

import { currencyFormatter } from '@/utils/journal-entries/currencyFormatter'
import { AccountJournalEntriesCollapse } from '@components/app/Accounting/AccountJournalEntriesCollapse'

// Props for this functional component
interface ReportProps {
  data: GenerateIncomeStatementResponse | undefined
  isLoading: boolean
  handleViewingTransaction: (transaction: IncomeStatementAccount) => void
}

function IncomeStatementReportBody({ data, isLoading, handleViewingTransaction }: ReportProps) {
  // IDs of accounts whose journal entries are currently expanded
  const [expandedAccountIds, setExpandedAccountIds] = useState<Set<string>>(new Set())

  const toggleAccount = (id: string) => {
    setExpandedAccountIds(prev => {
      const next = new Set(prev)

      next.has(id) ? next.delete(id) : next.add(id)

      return next
    })
  }

  // Number of columns the account name cell plus one column per date label spans
  const colSpan = (data?.labels.length ?? 1) + 1

  const renderAccountRows = (type: 'revenue' | 'expense') =>
    data?.accounts
      ?.filter(account => account.type === type)
      .map(account => {
        const isExpanded = expandedAccountIds.has(account.id)

        return (
          <Fragment key={account.id}>
            <TableRow hover>
              <TableCell
                align='left'
                sx={{
                  position: 'sticky',
                  left: 0,
                  zIndex: 1,
                  backgroundColor: theme => theme.palette.background.paper,
                  borderRight: theme => `1px solid ${theme.palette.divider}`,
                  borderBottom: isExpanded ? 'none' : undefined
                }}
              >
                <Stack direction='row' alignItems='center' gap={1}>
                  <IconButton
                    size='small'
                    onClick={() => toggleAccount(account.id)}
                    aria-label={isExpanded ? 'Collapse journal entries' : 'Expand journal entries'}
                  >
                    <i
                      className='bx-chevron-right'
                      style={{
                        display: 'inline-block',
                        transition: 'transform 0.15s ease',
                        transform: isExpanded ? 'rotate(90deg)' : 'rotate(0deg)'
                      }}
                    />
                  </IconButton>
                  <Typography variant='subtitle2' sx={{ fontWeight: 'bold' }}>
                    <Link component='button' color='textPrimary' onClick={() => handleViewingTransaction(account)}>
                      {account.name}
                    </Link>
                    {account.isContra && (
                      <Chip sx={{ ml: 2 }} size='small' variant='tonal' color='warning' label='Contra' />
                    )}
                  </Typography>
                </Stack>
              </TableCell>

              <TableCell align='right' sx={{ borderBottom: isExpanded ? 'none' : undefined }}>
                <Typography variant='subtitle2'>{currencyFormatter.format(account.balance)}</Typography>
              </TableCell>
              {account?.previousBalances?.map((previousBalance, index) => (
                <TableCell
                  key={`${previousBalance}-previous-${type}-${index}`}
                  align='right'
                  sx={{ borderBottom: isExpanded ? 'none' : undefined }}
                >
                  <Typography variant='subtitle2'>{currencyFormatter.format(previousBalance)}</Typography>
                </TableCell>
              ))}
            </TableRow>

            <AccountJournalEntriesCollapse open={isExpanded} colSpan={colSpan} transactions={account.transactions} />
          </Fragment>
        )
      })

  return (
    <Paper sx={{ p: 5 }}>
      {isLoading ? (
        <Stack alignItems='center' justifyContent='center'>
          <Skeleton width='100%' height='100px' />
          <Skeleton width='100%' height='60px' />
          <Skeleton width='100%' height='60px' />
          <Skeleton width='100%' height='60px' />
        </Stack>
      ) : (
        <TableContainer sx={{ overflowX: 'auto' }}>
          {/* Revenue Table */}
          <Table stickyHeader sx={{ mb: 5 }}>
            <TableHead>
              <TableRow sx={{ borderBottom: theme => `4px solid ${theme.palette.divider}` }}>
                <TableCell
                  sx={{
                    minWidth: '270px',
                    position: 'sticky',
                    left: 0,
                    zIndex: 3
                  }}
                >
                  <Typography sx={{ fontWeight: 'bold' }} variant='h6'>
                    REVENUE
                  </Typography>
                </TableCell>

                {data?.labels.map(dateLabel => (
                  <TableCell key={`${dateLabel}-revenue`} sx={{ fontWeight: 'bold', minWidth: '170px' }} align='right'>
                    {dateLabel}
                  </TableCell>
                ))}
              </TableRow>
            </TableHead>

            <TableBody>{renderAccountRows('revenue')}</TableBody>

            <TableFooter>
              <TableRow>
                <TableCell
                  align='right'
                  sx={{
                    borderBottom: 'none',
                    position: 'sticky',
                    left: 0,
                    zIndex: 1,
                    backgroundColor: theme => theme.palette.background.paper
                  }}
                >
                  <Typography variant='subtitle2' sx={{ fontWeight: 'bold' }}>
                    TOTAL REVENUE
                  </Typography>
                </TableCell>
                {data?.totalRevenue.map((revenue, index) => (
                  <TableCell key={`${revenue}-totalRevenue-${index}`} align='right' sx={{ borderBottom: 'none' }}>
                    <Typography variant='subtitle2'>{currencyFormatter.format(revenue)}</Typography>
                  </TableCell>
                ))}
              </TableRow>
            </TableFooter>
          </Table>

          {/* Expenses Table */}
          <Table stickyHeader>
            <TableHead>
              <TableRow sx={{ borderBottom: theme => `4px solid ${theme.palette.divider}` }}>
                <TableCell
                  sx={{
                    minWidth: '270px',
                    position: 'sticky',
                    left: 0,
                    zIndex: 3
                  }}
                >
                  <Typography sx={{ fontWeight: 'bold' }} variant='h6'>
                    EXPENSES
                  </Typography>
                </TableCell>
                {data?.labels.map(dateLabel => (
                  <TableCell key={`${dateLabel}-expense`} sx={{ fontWeight: 'bold', minWidth: '170px' }} align='right'>
                    {dateLabel}
                  </TableCell>
                ))}
              </TableRow>
            </TableHead>

            <TableBody>{renderAccountRows('expense')}</TableBody>

            <TableFooter>
              <TableRow>
                <TableCell
                  align='right'
                  sx={{
                    borderBottom: 'none',
                    position: 'sticky',
                    left: 0,
                    zIndex: 1,
                    backgroundColor: theme => theme.palette.background.paper
                  }}
                >
                  <Typography variant='subtitle2' sx={{ fontWeight: 'bold' }}>
                    TOTAL EXPENSES
                  </Typography>
                </TableCell>
                {data?.totalExpense.map((expense, index) => (
                  <TableCell key={`${expense}-totalExpense-${index}`} align='right' sx={{ borderBottom: 'none' }}>
                    <Typography variant='subtitle2'>{currencyFormatter.format(expense)}</Typography>
                  </TableCell>
                ))}
              </TableRow>

              {/* Net Loss/Profit */}
              <TableRow>
                <TableCell
                  align='right'
                  sx={{
                    borderBottom: 'none',
                    position: 'sticky',
                    left: 0,
                    zIndex: 1,
                    backgroundColor: theme => theme.palette.background.paper
                  }}
                >
                  <Typography variant='subtitle2' sx={{ fontWeight: 'bold' }}>
                    NET LOSS/PROFIT
                  </Typography>
                </TableCell>
                {data?.netTotal.map((total, index) => (
                  <TableCell key={`${total}-total-${index}`} align='right' sx={{ borderBottom: 'none' }}>
                    <Typography variant='subtitle2'>{currencyFormatter.format(total)}</Typography>
                  </TableCell>
                ))}
              </TableRow>
            </TableFooter>
          </Table>
        </TableContainer>
      )}
    </Paper>
  )
}

export default IncomeStatementReportBody
