import { Fragment, useState } from 'react'

import type { Dayjs } from 'dayjs'
import dayjs from 'dayjs'

import {
  Alert,
  IconButton,
  Skeleton,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  Typography
} from '@mui/material'

import type {
  CashFlowData,
  CashFlowSection
} from '@/features/(protected)/accounting/reports/cash-flow/model/cash-flow.types'
import { currencyFormatter } from '@/utils/currencyFormatter'
import { CashFlowJournalEntriesCollapse } from '@components/app/Accounting/AccountJournalEntriesCollapse'

// Props for this functional component
interface ReportProps {
  data: CashFlowData | undefined
  companyName: string
  loading: boolean
  startDate: Dayjs
  endDate: Dayjs
}

function CashFlowReportView({ data, companyName, loading, startDate, endDate }: ReportProps) {
  // Keys of the cash flow lines whose journal entries are currently expanded
  const [expandedLineKeys, setExpandedLineKeys] = useState<Set<string>>(new Set())

  const toggleLine = (key: string) => {
    setExpandedLineKeys(prev => {
      const next = new Set(prev)

      next.has(key) ? next.delete(key) : next.add(key)

      return next
    })
  }

  // String formats of the dates
  const startDateText = startDate.format('MMMM DD, YYYY')
  const endDateText = endDate.format('MMMM DD, YYYY')

  // Display skeleton if report data is still loading
  if (loading) {
    return (
      <>
        <Skeleton width='100%' height='100px' />
        <Skeleton width='100%' height='60px' />
        <Skeleton width='100%' height='60px' />
        <Skeleton width='100%' height='60px' />
      </>
    )
  }

  const hasComparison = Boolean(data?.comparisonPeriod && data?.comparison && data?.sections?.length)

  const comparisonStartText = data?.comparisonPeriod?.startDate
    ? dayjs(data.comparisonPeriod.startDate).format('MMMM DD, YYYY')
    : ''

  const comparisonEndText = data?.comparisonPeriod?.endDate
    ? dayjs(data.comparisonPeriod.endDate).format('MMMM DD, YYYY')
    : ''

  const comparisonPeriodText =
    comparisonStartText && comparisonEndText
      ? comparisonStartText === comparisonEndText
        ? comparisonStartText
        : `${comparisonStartText} to ${comparisonEndText}`
      : ''

  const sections = data?.sections ?? buildLegacySections(data)
  const warnings = data?.warnings ?? []
  const unmappedTransactions = data?.unmappedTransactions ?? []
  const hasReviewItems = warnings.length > 0 || unmappedTransactions.length > 0

  return (
    <Stack alignItems='center' justifyContent='flex-start'>
      <Stack className='report-screen-heading' sx={{ mb: 5 }} alignItems='center' justifyContent='center'>
        <Typography variant='h5'>{companyName}</Typography>
        <Typography variant='subtitle1'>Cash Flow Statement</Typography>
        <Typography variant='subtitle2'>
          {startDate.isSame(endDate) ? `From today (${startDateText})` : `From  ${startDateText} to ${endDateText}`}
        </Typography>
        {hasComparison && (
          <Typography variant='caption' color='text.secondary'>
            Compared with {comparisonPeriodText}
          </Typography>
        )}
      </Stack>

      <Stack width={hasComparison ? '100%' : '75%'}>
        {hasReviewItems && (
          <Stack gap={2} sx={{ mb: 4 }}>
            {warnings.map(warning => (
              <Alert key={warning} severity='warning'>
                {warning}
              </Alert>
            ))}
          </Stack>
        )}

        <Table>
          {/* Table Header */}
          <TableHead sx={{ backgroundColor: 'primary.main' }}>
            <TableRow>
              <TableCell>ACCOUNT</TableCell>
              {hasComparison ? (
                <>
                  <TableCell align='right'>CURRENT PERIOD</TableCell>
                  <TableCell align='right'>PREVIOUS PERIOD</TableCell>
                  <TableCell align='right'>VARIANCE</TableCell>
                  <TableCell align='right'>VARIANCE %</TableCell>
                </>
              ) : (
                <TableCell align='right'>TOTAL</TableCell>
              )}
            </TableRow>
          </TableHead>

          {/* Table Body */}
          <TableBody>
            {/* Opening Balance */}
            <TableRow>
              <TableCell>Beginning Cash Balance</TableCell>
              {hasComparison ? (
                <>
                  <TableCell align='right'>{currencyFormatter.format(data?.openingBalance ?? 0)}</TableCell>
                  <TableCell align='right'>{currencyFormatter.format(data?.comparison?.openingBalance ?? 0)}</TableCell>
                  <TableCell align='right'>
                    {currencyFormatter.format((data?.openingBalance ?? 0) - (data?.comparison?.openingBalance ?? 0))}
                  </TableCell>
                  <TableCell align='right'>{formatVariancePercent(data?.openingBalance ?? 0, data?.comparison?.openingBalance ?? 0)}</TableCell>
                </>
              ) : (
                <TableCell align='right'>{currencyFormatter.format(data?.openingBalance ?? 0)}</TableCell>
              )}
            </TableRow>

            {sections.map(section => (
              <Fragment key={section.key}>
                <TableRow key={`${section.key}-heading`}>
                  <TableCell colSpan={hasComparison ? 5 : 2} sx={{ borderBottom: 'none' }}>
                    Cash Flow from {section.label}
                  </TableCell>
                </TableRow>
                {section.lines.map(item => {
                  const lineKey = `${section.key}-${item.accountId ?? item.accountName}`
                  const isExpanded = expandedLineKeys.has(lineKey)
                  const transactions = item.transactions ?? []

                  return (
                    <Fragment key={lineKey}>
                      <TableRow hover>
                        <TableCell sx={{ pl: 6, borderBottom: 'none' }}>
                          <Stack direction='row' alignItems='center' gap={1}>
                            <IconButton
                              size='small'
                              onClick={() => toggleLine(lineKey)}
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
                            {item.accountName}
                          </Stack>
                        </TableCell>
                        {hasComparison ? (
                          <>
                            <TableCell align='right' sx={{ borderBottom: 'none' }}>
                              {currencyFormatter.format(item.currentAmount)}
                            </TableCell>
                            <TableCell align='right' sx={{ borderBottom: 'none' }}>
                              {currencyFormatter.format(item.previousAmount ?? 0)}
                            </TableCell>
                            <TableCell align='right' sx={{ borderBottom: 'none' }}>
                              {currencyFormatter.format(item.varianceAmount ?? 0)}
                            </TableCell>
                            <TableCell align='right' sx={{ borderBottom: 'none' }}>
                              {formatPercent(item.variancePercent)}
                            </TableCell>
                          </>
                        ) : (
                          <TableCell align='right' sx={{ borderBottom: 'none' }}>
                            {currencyFormatter.format(item.currentAmount)}
                          </TableCell>
                        )}
                      </TableRow>

                      <CashFlowJournalEntriesCollapse
                        open={isExpanded}
                        colSpan={hasComparison ? 5 : 2}
                        transactions={transactions}
                      />
                    </Fragment>
                  )
                })}
                <TableRow key={`${section.key}-total`}>
                  <TableCell>Net Cash from {section.label}</TableCell>
                  {hasComparison ? (
                    <>
                      <TableCell align='right'>{currencyFormatter.format(section.currentTotal)}</TableCell>
                      <TableCell align='right'>{currencyFormatter.format(section.previousTotal ?? 0)}</TableCell>
                      <TableCell align='right'>{currencyFormatter.format(section.varianceAmount ?? 0)}</TableCell>
                      <TableCell align='right'>{formatPercent(section.variancePercent)}</TableCell>
                    </>
                  ) : (
                    <TableCell align='right'>{currencyFormatter.format(section.currentTotal)}</TableCell>
                  )}
                </TableRow>
              </Fragment>
            ))}

            {/* Net Change and Ending Balance */}
            <TableRow>
              <TableCell>Net Change in Cash</TableCell>
              {hasComparison ? (
                <>
                  <TableCell align='right'>{currencyFormatter.format(data?.netChange ?? 0)}</TableCell>
                  <TableCell align='right'>{currencyFormatter.format(data?.comparison?.netChange ?? 0)}</TableCell>
                  <TableCell align='right'>
                    {currencyFormatter.format(data?.summary?.netCashFlowVariance ?? 0)}
                  </TableCell>
                  <TableCell align='right'>{formatVariancePercent(data?.netChange ?? 0, data?.comparison?.netChange ?? 0)}</TableCell>
                </>
              ) : (
                <TableCell align='right'>{currencyFormatter.format(data?.netChange ?? 0)}</TableCell>
              )}
            </TableRow>
            <TableRow>
              <TableCell>Ending Cash Balance</TableCell>
              {hasComparison ? (
                <>
                  <TableCell align='right'>{currencyFormatter.format(data?.endingBalance ?? 0)}</TableCell>
                  <TableCell align='right'>{currencyFormatter.format(data?.comparison?.endingBalance ?? 0)}</TableCell>
                  <TableCell align='right'>{currencyFormatter.format(data?.summary?.endingCashVariance ?? 0)}</TableCell>
                  <TableCell align='right'>{formatVariancePercent(data?.endingBalance ?? 0, data?.comparison?.endingBalance ?? 0)}</TableCell>
                </>
              ) : (
                <TableCell align='right'>{currencyFormatter.format(data?.endingBalance ?? 0)}</TableCell>
              )}
            </TableRow>
          </TableBody>
        </Table>

        {unmappedTransactions.length > 0 && (
          <Stack sx={{ mt: 6 }}>
            <Typography variant='subtitle2' sx={{ mb: 2 }}>
              Unmapped Cash Movements
            </Typography>
            <Table size='small'>
              <TableHead>
                <TableRow>
                  <TableCell>Date</TableCell>
                  <TableCell>Journal Entry</TableCell>
                  <TableCell>Reason</TableCell>
                  <TableCell align='right'>Amount</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {unmappedTransactions.map(transaction => (
                  <TableRow key={`${transaction.journalEntryId}-${transaction.amount}`}>
                    <TableCell>{dayjs(transaction.entryDate).format('MMM DD, YYYY')}</TableCell>
                    <TableCell>{transaction.journalNumber}</TableCell>
                    <TableCell>{transaction.reason}</TableCell>
                    <TableCell align='right'>{currencyFormatter.format(transaction.amount)}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </Stack>
        )}
      </Stack>
    </Stack>
  )
}

function buildLegacySections(data: CashFlowData | undefined): CashFlowSection[] {
  const sectionDefinitions: Array<{ key: CashFlowSection['key']; label: string; total: number }> = [
    { key: 'operating', label: 'Operating Activities', total: data?.operatingTotal ?? 0 },
    { key: 'investing', label: 'Investing Activities', total: data?.investingTotal ?? 0 },
    { key: 'financing', label: 'Financing Activities', total: data?.financingTotal ?? 0 }
  ]

  return sectionDefinitions.map(section => ({
    key: section.key,
    label: section.label,
    lines:
      data?.items
        ?.filter(item => item.type === section.key)
        .map(item => ({
          accountId: item.accountId,
          accountName: item.accountName,
          currentAmount: item.total,
          previousAmount: null,
          varianceAmount: null,
          variancePercent: null,
          transactions: item.transactions ?? []
        })) ?? [],
    currentTotal: section.total,
    previousTotal: null,
    varianceAmount: null,
    variancePercent: null
  }))
}

function formatPercent(value: number | null | undefined): string {
  return typeof value === 'number' ? `${value.toFixed(2)}%` : '—'
}

function formatVariancePercent(current: number, previous: number): string {
  if (Math.abs(previous) < 0.01) return '—'

  return `${(((current - previous) / Math.abs(previous)) * 100).toFixed(2)}%`
}

export default CashFlowReportView
