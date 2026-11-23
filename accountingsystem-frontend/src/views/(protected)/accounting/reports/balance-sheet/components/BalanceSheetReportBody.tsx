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
  TableHead,
  TableRow,
  Typography
} from '@mui/material'

import type {
  BalanceSheetAccount,
  GenerateBalanceSheetResponse
} from '@/features/(protected)/accounting/reports/balance-sheet/model/balance-sheet.types'
import { currencyFormatter } from '@/utils/journal-entries/currencyFormatter'
import { AccountJournalEntriesCollapse } from '@components/app/Accounting/AccountJournalEntriesCollapse'

// Props to this functional component
interface ReportProps {
  data: GenerateBalanceSheetResponse | undefined
  isLoading: boolean
  handleViewingTransaction: (transaction: BalanceSheetAccount) => void
}

function BalanceSheetReportBody({ data, isLoading, handleViewingTransaction }: ReportProps) {
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

  const renderAccountRows = (predicate: (account: BalanceSheetAccount) => boolean) =>
    data?.accounts.filter(predicate).map(account => {
      const isExpanded = expandedAccountIds.has(account.id)

      return (
        <Fragment key={account.id}>
          <TableRow hover>
            <TableCell
              align='left'
              sx={{
                pl: 6,
                borderBottom: isExpanded ? 'none' : 'none',
                position: 'sticky',
                left: 0,
                zIndex: 1,
                backgroundColor: theme => theme.palette.background.paper,
                borderRight: theme => `1px solid ${theme.palette.divider}`
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
                <Typography variant='subtitle2' sx={{ fontWeight: 'bold', display: 'inline' }}>
                  <Link component='button' color='textPrimary' onClick={() => handleViewingTransaction(account)}>
                    {account.name}
                  </Link>
                </Typography>
                {account.isContra && <Chip size='small' variant='tonal' color='warning' label='Contra' />}
              </Stack>
            </TableCell>

            <TableCell align='right' sx={{ borderBottom: 'none' }}>
              <Typography variant='subtitle2'>{currencyFormatter.format(account.balance)}</Typography>
            </TableCell>

            {account?.previousBalances?.map((balance, index) => (
              <TableCell key={`${balance}-${index}`} align='right' sx={{ borderBottom: 'none' }}>
                <Typography variant='subtitle2'>{currencyFormatter.format(balance)}</Typography>
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
          {/* Assets Table */}
          <Table stickyHeader size='small' sx={{ mb: 5 }}>
            <TableHead>
              <TableRow sx={{ borderBottom: theme => `4px solid ${theme.palette.divider}` }}>
                <TableCell
                  sx={{
                    minWidth: '280px',
                    position: 'sticky',
                    left: 0,
                    zIndex: 3
                  }}
                >
                  <Typography sx={{ fontWeight: 'bold' }} variant='h6'>
                    ASSETS
                  </Typography>
                </TableCell>

                {data?.labels.map(dateLabel => (
                  <TableCell key={`${dateLabel}-assets`} sx={{ fontWeight: 'bold', minWidth: '170px' }} align='right'>
                    {dateLabel}
                  </TableCell>
                ))}
              </TableRow>
            </TableHead>

            <TableBody>
              {/* Current Assets */}
              <TableRow>
                <TableCell
                  sx={{
                    position: 'sticky',
                    left: 0,
                    zIndex: 1,
                    backgroundColor: theme => theme.palette.background.paper
                  }}
                >
                  <Typography sx={{ fontWeight: 'bold' }} variant='h6'>
                    Current Assets
                  </Typography>
                </TableCell>
                <TableCell colSpan={data?.labels.length ?? 0} />
              </TableRow>

              {renderAccountRows(account => account.accountType === 'currentAsset')}

              <TableRow>
                <TableCell
                  align='right'
                  sx={{
                    borderBottom: 'none',
                    borderTop: theme => `1px solid ${theme.palette.divider}`,
                    position: 'sticky',
                    left: 0,
                    zIndex: 1,
                    backgroundColor: theme => theme.palette.background.paper
                  }}
                >
                  <Typography variant='subtitle2' sx={{ fontWeight: 'bold' }}>
                    TOTAL CURRENT ASSETS
                  </Typography>
                </TableCell>
                {data?.totalCurrentAssets.map((currentAsset, index) => (
                  <TableCell
                    key={`${currentAsset}-${index}`}
                    align='right'
                    sx={{ borderBottom: 'none', borderTop: theme => `1px solid ${theme.palette.divider}` }}
                  >
                    <Typography variant='subtitle2'>{currencyFormatter.format(currentAsset)}</Typography>
                  </TableCell>
                ))}
              </TableRow>
              <TableRow>
                <TableCell sx={{ py: 2, borderBottom: 'none' }} />
              </TableRow>

              {/* Non-current Assets */}
              <TableRow>
                <TableCell
                  sx={{
                    position: 'sticky',
                    left: 0,
                    zIndex: 1,
                    backgroundColor: theme => theme.palette.background.paper
                  }}
                >
                  <Typography sx={{ fontWeight: 'bold' }} variant='h6'>
                    Non-current Assets
                  </Typography>
                </TableCell>
                <TableCell colSpan={data?.labels.length ?? 0} />
              </TableRow>

              {renderAccountRows(account => account.accountType === 'nonCurrentAsset')}

              <TableRow>
                <TableCell
                  width='75%'
                  align='right'
                  sx={{
                    borderBottom: 'none',
                    borderTop: theme => `1px solid ${theme.palette.divider}`,
                    position: 'sticky',
                    left: 0,
                    zIndex: 1,
                    backgroundColor: theme => theme.palette.background.paper
                  }}
                >
                  <Typography variant='subtitle2' sx={{ fontWeight: 'bold' }}>
                    TOTAL NON-CURRENT ASSETS
                  </Typography>
                </TableCell>

                {data?.totalNonCurrentAssets.map((nonCurrentAsset, index) => (
                  <TableCell
                    key={`${nonCurrentAsset}-${index}`}
                    align='right'
                    sx={{ borderBottom: 'none', borderTop: theme => `1px solid ${theme.palette.divider}` }}
                  >
                    <Typography variant='subtitle2'>{currencyFormatter.format(nonCurrentAsset)}</Typography>
                  </TableCell>
                ))}
              </TableRow>

              {/* Total Assets */}
              <TableRow>
                <TableCell
                  width='75%'
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
                    TOTAL ASSETS
                  </Typography>
                </TableCell>
                {data?.totalAssets.map((asset, index) => (
                  <TableCell key={`${asset}-${index}`} align='right' sx={{ borderBottom: 'none' }}>
                    <Typography variant='subtitle2'>{currencyFormatter.format(asset)}</Typography>
                  </TableCell>
                ))}
              </TableRow>
            </TableBody>
          </Table>

          {/* Liabilities Table */}
          <Table stickyHeader size='small' sx={{ mb: 5 }}>
            <TableHead>
              <TableRow sx={{ borderBottom: theme => `4px solid ${theme.palette.divider}` }}>
                <TableCell
                  sx={{
                    minWidth: '280px',
                    position: 'sticky',
                    left: 0,
                    zIndex: 3
                  }}
                >
                  <Typography sx={{ fontWeight: 'bold' }} variant='h6'>
                    LIABILITIES
                  </Typography>
                </TableCell>
                {data?.labels.map(dateLabel => (
                  <TableCell
                    key={`${dateLabel}-liabilities`}
                    sx={{ fontWeight: 'bold', minWidth: '170px' }}
                    align='right'
                  >
                    {dateLabel}
                  </TableCell>
                ))}
              </TableRow>
            </TableHead>

            <TableBody>
              {/* Current Liabilities */}
              <TableRow>
                <TableCell
                  sx={{
                    position: 'sticky',
                    left: 0,
                    zIndex: 1,
                    backgroundColor: theme => theme.palette.background.paper
                  }}
                >
                  <Typography sx={{ fontWeight: 'bold' }} variant='h6'>
                    Current Liabilities
                  </Typography>
                </TableCell>
                <TableCell colSpan={data?.labels.length ?? 0} />
              </TableRow>

              {renderAccountRows(account => account.accountType === 'currentLiability')}

              <TableRow>
                <TableCell
                  align='right'
                  sx={{
                    borderBottom: 'none',
                    borderTop: theme => `1px solid ${theme.palette.divider}`,
                    position: 'sticky',
                    left: 0,
                    zIndex: 1,
                    backgroundColor: theme => theme.palette.background.paper
                  }}
                >
                  <Typography variant='subtitle2' sx={{ fontWeight: 'bold' }}>
                    TOTAL CURRENT LIABILITIES
                  </Typography>
                </TableCell>
                {data?.totalCurrentLiabilities.map((currentLiability, index) => (
                  <TableCell
                    key={`${currentLiability}-${index}`}
                    align='right'
                    sx={{ borderBottom: 'none', borderTop: theme => `1px solid ${theme.palette.divider}` }}
                  >
                    <Typography variant='subtitle2'>{currencyFormatter.format(currentLiability)}</Typography>
                  </TableCell>
                ))}
              </TableRow>

              <TableRow>
                <TableCell sx={{ py: 2, borderBottom: 'none' }} />
              </TableRow>

              {/* Non-current Liabilities */}
              <TableRow>
                <TableCell
                  sx={{
                    position: 'sticky',
                    left: 0,
                    zIndex: 1,
                    backgroundColor: theme => theme.palette.background.paper
                  }}
                >
                  <Typography sx={{ fontWeight: 'bold' }} variant='h6'>
                    Non-current Liabilities
                  </Typography>
                </TableCell>
                <TableCell colSpan={data?.labels.length ?? 2} />
              </TableRow>

              {renderAccountRows(account => account.accountType === 'nonCurrentLiability')}

              <TableRow>
                <TableCell
                  width='75%'
                  align='right'
                  sx={{
                    borderBottom: 'none',
                    borderTop: theme => `1px solid ${theme.palette.divider}`,
                    position: 'sticky',
                    left: 0,
                    zIndex: 1,
                    backgroundColor: theme => theme.palette.background.paper
                  }}
                >
                  <Typography variant='subtitle2' sx={{ fontWeight: 'bold' }}>
                    TOTAL NON-CURRENT LIABILITIES
                  </Typography>
                </TableCell>
                {data?.totalNonCurrentLiabilities.map((nonCurrentLiability, index) => (
                  <TableCell
                    key={`${nonCurrentLiability}-${index}`}
                    align='right'
                    sx={{ borderBottom: 'none', borderTop: theme => `1px solid ${theme.palette.divider}` }}
                  >
                    <Typography variant='subtitle2'>{currencyFormatter.format(nonCurrentLiability)}</Typography>
                  </TableCell>
                ))}
              </TableRow>

              {/* Total Liabilities */}
              <TableRow>
                <TableCell
                  width='75%'
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
                    TOTAL LIABILITIES
                  </Typography>
                </TableCell>
                {data?.totalLiabilities.map((liability, index) => (
                  <TableCell key={`${liability}-${index}`} align='right' sx={{ borderBottom: 'none' }}>
                    <Typography variant='subtitle2'>{currencyFormatter.format(liability)}</Typography>
                  </TableCell>
                ))}
              </TableRow>
            </TableBody>
          </Table>

          {/* Equities Table */}
          <Table stickyHeader size='small'>
            <TableHead>
              <TableRow sx={{ borderBottom: theme => `4px solid ${theme.palette.divider}` }}>
                <TableCell
                  sx={{
                    minWidth: '280px',
                    position: 'sticky',
                    left: 0,
                    zIndex: 3
                  }}
                >
                  <Typography sx={{ fontWeight: 'bold' }} variant='h6'>
                    EQUITIES
                  </Typography>
                </TableCell>

                {data?.labels.map(dateLabel => (
                  <TableCell key={`${dateLabel}-equities`} sx={{ fontWeight: 'bold', minWidth: '170px' }} align='right'>
                    {dateLabel}
                  </TableCell>
                ))}
              </TableRow>
            </TableHead>

            <TableBody>
              {renderAccountRows(account => account.coreType === 'equity')}

              <TableRow>
                <TableCell
                  align='right'
                  sx={{
                    borderBottom: 'none',
                    borderTop: theme => `1px solid ${theme.palette.divider}`,
                    position: 'sticky',
                    left: 0,
                    zIndex: 1,
                    backgroundColor: theme => theme.palette.background.paper
                  }}
                >
                  <Typography variant='subtitle2' sx={{ fontWeight: 'bold' }}>
                    TOTAL EQUITIES
                  </Typography>
                </TableCell>
                {data?.totalEquities.map((equity, index) => (
                  <TableCell
                    key={`${equity}-${index}`}
                    align='right'
                    sx={{
                      borderBottom: 'none',
                      borderTop: theme => `1px solid ${theme.palette.divider}`
                    }}
                  >
                    <Typography variant='subtitle2'>{currencyFormatter.format(equity)}</Typography>
                  </TableCell>
                ))}
              </TableRow>

              {/* Total Liabilities + Equities */}
              <TableRow>
                <TableCell
                  width='75%'
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
                    TOTAL LIABILITIES + EQUITY
                  </Typography>
                </TableCell>
                {data?.totalLiabilitiesEquities.map((liabilityEquity, index) => (
                  <TableCell key={`${liabilityEquity}-${index}`} align='right' sx={{ borderBottom: 'none' }}>
                    <Typography variant='subtitle2'>{currencyFormatter.format(liabilityEquity)}</Typography>
                  </TableCell>
                ))}
              </TableRow>
            </TableBody>
          </Table>
        </TableContainer>
      )}
    </Paper>
  )
}

export default BalanceSheetReportBody
