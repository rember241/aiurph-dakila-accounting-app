import { Fragment, useMemo, useState } from 'react'

import Decimal from 'decimal.js'

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
  TableFooter,
  TableHead,
  TableRow,
  Typography
} from '@mui/material'

import type { TrialBalanceAccount } from '@/features/(protected)/accounting/reports/trial-balance/model/trial-balance.types'
import { currencyFormatter } from '@/utils/currencyFormatter'
import { AccountJournalEntriesCollapse } from '@components/app/Accounting/AccountJournalEntriesCollapse'

// Props to this functional component
interface ReportProps {
  data: TrialBalanceAccount[] | undefined
  isLoading: boolean
  handleViewingTransaction: (transaction: TrialBalanceAccount) => void
}

const SECTIONS: { key: TrialBalanceAccount['type']; label: string }[] = [
  { key: 'asset', label: 'Assets' },
  { key: 'liability', label: 'Liabilities' },
  { key: 'equity', label: 'Equities' },
  { key: 'revenue', label: 'Revenue' },
  { key: 'expense', label: 'Expenses' }
]

function TrialBalanceReportBody({ data, isLoading, handleViewingTransaction }: ReportProps) {
  // States for showing/hiding sections
  const [hiddenSections, setHiddenSections] = useState<Set<string>>(new Set())

  // IDs of accounts whose journal entries are currently expanded
  const [expandedAccountIds, setExpandedAccountIds] = useState<Set<string>>(new Set())

  const toggleSection = (key: string) => {
    setHiddenSections(prev => {
      const next = new Set(prev)

      next.has(key) ? next.delete(key) : next.add(key)

      return next
    })
  }

  const toggleAccount = (id: string) => {
    setExpandedAccountIds(prev => {
      const next = new Set(prev)

      next.has(id) ? next.delete(id) : next.add(id)

      return next
    })
  }

  // Utility function to compute the totals of the debit and credit side
  const computeTotalsByNormalBalance = (accounts: TrialBalanceAccount[]) => {
    return accounts.reduce(
      (totals, account) => {
        totals.debit = totals.debit.plus(account.debit)
        totals.credit = totals.credit.plus(account.credit)

        return totals
      },
      {
        debit: new Decimal(0),
        credit: new Decimal(0)
      }
    )
  }

  // Compute the debit/credit sides per section, plus grand totals
  const { grandTotal, sectionTotals } = useMemo(() => {
    const zero = { debit: new Decimal(0), credit: new Decimal(0) }

    if (!data) {
      return {
        grandTotal: zero,
        sectionTotals: Object.fromEntries(SECTIONS.map(section => [section.key, zero]))
      }
    }

    return {
      grandTotal: computeTotalsByNormalBalance(data),
      sectionTotals: Object.fromEntries(
        SECTIONS.map(section => [section.key, computeTotalsByNormalBalance(data.filter(a => a.type === section.key))])
      )
    }
  }, [data])

  return (
    <Paper sx={{ p: 5 }}>
      <Stack alignItems='center' justifyContent='center'>
        {isLoading ? (
          <>
            <Skeleton width='75%' height='100px' />
            <Skeleton width='75%' height='60px' />
            <Skeleton width='75%' height='60px' />
            <Skeleton width='75%' height='60px' />
          </>
        ) : (
          <>
            <Table sx={{ width: '75%' }}>
              {/* Table Header */}
              <TableHead sx={{ backgroundColor: theme => theme.palette.background.default }}>
                <TableRow sx={{ borderBottom: theme => `4px solid ${theme.palette.divider}` }}>
                  <TableCell sx={{ fontWeight: 'bold' }}>ACCOUNT</TableCell>
                  <TableCell align='right' sx={{ fontWeight: 'bold' }}>
                    NET DEBIT (Dr)
                  </TableCell>
                  <TableCell align='right' sx={{ fontWeight: 'bold' }}>
                    NET CREDIT (Cr)
                  </TableCell>
                </TableRow>
              </TableHead>

              {/* Table Body */}
              <TableBody>
                {SECTIONS.map(section => {
                  const isHidden = hiddenSections.has(section.key)
                  const totals = sectionTotals[section.key]
                  const accounts = data?.filter(account => account.type === section.key) ?? []

                  return (
                    <Fragment key={section.key}>
                      <TableRow
                        sx={{ cursor: 'pointer', '&:hover': { backgroundColor: theme => theme.palette.action.hover } }}
                        onClick={() => toggleSection(section.key)}
                      >
                        <TableCell>
                          <Stack direction='row' alignItems='center' gap={1}>
                            <i className={isHidden ? 'bx bx-caret-right' : 'bx bx-caret-down'} />
                            <Typography sx={{ fontWeight: 'bold' }}>{section.label}</Typography>
                            <Chip size='small' variant='tonal' label={accounts.length} sx={{ ml: 1 }} />
                          </Stack>
                        </TableCell>
                        <TableCell align='right'>
                          {isHidden && (
                            <Typography variant='body2' sx={{ fontWeight: 'bold' }}>
                              {currencyFormatter.format(totals.debit.toNumber())}
                            </Typography>
                          )}
                        </TableCell>
                        <TableCell align='right'>
                          {isHidden && (
                            <Typography variant='body2' sx={{ fontWeight: 'bold' }}>
                              {currencyFormatter.format(totals.credit.toNumber())}
                            </Typography>
                          )}
                        </TableCell>
                      </TableRow>

                      {!isHidden &&
                        accounts.map(account => {
                          const isExpanded = expandedAccountIds.has(account.id)

                          return (
                            <Fragment key={account.id}>
                              <TableRow
                                hover
                                sx={{ '& .MuiTableCell-root': { borderBottom: isExpanded ? 'none' : undefined } }}
                              >
                                <TableCell sx={{ pl: 6 }}>
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
                                    <Link component='button' color='textPrimary' onClick={() => handleViewingTransaction(account)}>
                                      {account.name}
                                    </Link>
                                    {account.isContra && (
                                      <Chip variant='tonal' color='warning' size='small' label='Contra' />
                                    )}
                                  </Stack>
                                </TableCell>
                                <TableCell align='right'>
                                  {account.debit !== 0 ? currencyFormatter.format(account.debit) : ''}
                                </TableCell>
                                <TableCell align='right'>
                                  {account.credit !== 0 ? currencyFormatter.format(account.credit) : ''}
                                </TableCell>
                              </TableRow>

                              <AccountJournalEntriesCollapse
                                open={isExpanded}
                                colSpan={3}
                                transactions={account.transactions}
                              />
                            </Fragment>
                          )
                        })}
                    </Fragment>
                  )
                })}
              </TableBody>

              {/* Table Footer */}
              <TableFooter>
                <TableRow>
                  <TableCell>
                    <Typography variant='body2' sx={{ fontWeight: 'bold', display: 'inline' }}>
                      TOTALS
                    </Typography>
                    {!grandTotal.debit.equals(grandTotal.credit) && (
                      <Chip
                        sx={{ ml: 2 }}
                        variant='tonal'
                        color='error'
                        size='small'
                        label={`Out of Balance (${currencyFormatter.format(Math.abs(grandTotal.debit.minus(grandTotal.credit).toNumber()))})`}
                      />
                    )}
                  </TableCell>
                  <TableCell align='right'>
                    <Typography variant='body2' sx={{ fontWeight: 'bold' }}>
                      {currencyFormatter.format(grandTotal.debit.toNumber())}
                    </Typography>
                  </TableCell>
                  <TableCell align='right'>
                    <Typography variant='body2' sx={{ fontWeight: 'bold' }}>
                      {currencyFormatter.format(grandTotal.credit.toNumber())}
                    </Typography>
                  </TableCell>
                </TableRow>
              </TableFooter>
            </Table>
          </>
        )}
      </Stack>
    </Paper>
  )
}

export default TrialBalanceReportBody
