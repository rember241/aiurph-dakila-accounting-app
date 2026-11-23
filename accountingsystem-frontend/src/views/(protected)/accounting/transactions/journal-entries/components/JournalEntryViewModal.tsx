import { useState } from 'react'

import {
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  Paper,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableFooter,
  TableHead,
  TableRow,
  Typography
} from '@mui/material'

import { toast } from 'react-toastify'

import { useCurrentTenantAccess } from '@/features/auth/access/components/CurrentTenantAccessProvider'
import { APP_ABILITIES } from '@/features/auth/access/model/access.schema'
import type { JournalEntryColumn } from '@/features/(protected)/accounting/transactions/journal-entries/model/journal-entries.types'
import { currencyFormatter } from '@/utils/journal-entries/currencyFormatter'

import { usePostJournalEntryMutation } from '@/features/(protected)/accounting/transactions/journal-entries/api/journal-entries.queries'

// Props passed into JournalEntryViewModal component
interface ViewModalProps {
  isOpen: boolean
  handleClose: () => void
  journalEntry: JournalEntryColumn | null
}

function JournalEntryViewModal({ isOpen, journalEntry, handleClose }: ViewModalProps) {
  // Format entry number
  const formattedEntryNumber = `${journalEntry?.prefix}-${String(journalEntry?.journalNumber).padStart(4, '0')}`

  // Post confirmation modal state
  const [isConfirmationModalOpen, setIsConfirmationModalOpen] = useState<boolean>(false)

  // Custom TanStack Query hook to post an entry that was saved as draft
  const { mutateAsync } = usePostJournalEntryMutation()
  const access = useCurrentTenantAccess()
  const canManageJournalEntries = access.canAny([APP_ABILITIES.journalWrite, APP_ABILITIES.accountingWrite])

  let debitSubTotal = 0
  let creditSubTotal = 0

  // Compute the total debit before taxes
  journalEntry?.journalItems.forEach(item => {
    if (item.type === 'debit') debitSubTotal += item.amount
  })

  // Compute the total credit before taxes
  journalEntry?.journalItems.forEach(item => {
    if (item.type === 'credit') creditSubTotal += item.amount
  })

  // Group tax totals by tax name, split into debit and credit
  const taxTotals =
    journalEntry?.journalItems.reduce(
      (acc, item) => {
        // Ignore journal items without tax
        if (!item.taxName || !item.taxRate) return acc

        // If the tax name has not been encountered yet, initialize with default values
        acc[item.taxName] ??= { taxName: item.taxName, taxRate: item.taxRate, debit: 0, credit: 0 }

        // Calculate and add amount to either the debit or credit side
        acc[item.taxName][item.type] += Math.round(item.amount * item.taxRate) / 100

        return acc
      },
      {} as Record<string, { taxName: string; taxRate: number; debit: number; credit: number }>
    ) ?? {}

  // Array of taxes applied in this journal entry with their corresponding total debit and credit
  const taxesApplied = Object.values(taxTotals)

  // Compute the total of the debit and credit side with taxes
  const debitTotalWithTax = Math.round(taxesApplied.reduce((sum, t) => sum + t.debit, debitSubTotal) * 100) / 100
  const creditTotalWithTax = Math.round(taxesApplied.reduce((sum, t) => sum + t.credit, creditSubTotal) * 100) / 100

  // Function to post a journal that was saved as a draft
  const handleEntryPosting = async () => {
    if (journalEntry) {
      await toast.promise(mutateAsync(journalEntry.id), {
        pending: 'Posting journal entry...',
        success: 'Journal entry posted successfully!',
        error: {
          render: ({ data }: { data: any }) => {
            const backendMessage = data?.response?.data?.message

            return backendMessage ?? 'Error posting journal entry'
          }
        }
      })
    }

    setIsConfirmationModalOpen(false)
    handleClose()
  }

  return (
    <>
      <Dialog fullWidth maxWidth='lg' open={isOpen} onClose={handleClose}>
        <DialogTitle>{`Journal Entry #${formattedEntryNumber}`}</DialogTitle>
        <DialogContent>
          <Stack
            spacing={6}
            direction='column'
            sx={{
              justifyContent: 'flex-start',
              alignItems: 'stretch'
            }}
          >
            {/* Entry Details */}
            <Stack
              spacing={2}
              direction='column'
              sx={{
                justifyContent: 'flex-start',
                alignItems: 'stretch'
              }}
            >
              <Typography variant='h6'>Entry Details</Typography>
              <Paper elevation={3} sx={{ display: 'flex', justifyContent: 'space-between', p: 4, gap: 8 }}>
                {/* Left Column */}
                <Stack spacing={2} sx={{ flex: 1 }}>
                  <Stack
                    direction='row'
                    sx={{
                      justifyContent: 'space-between',
                      alignItems: 'center'
                    }}
                  >
                    <Typography variant='subtitle1'>Date:</Typography>
                    <Typography variant='caption'>{journalEntry?.date}</Typography>
                  </Stack>

                  <Stack
                    direction='row'
                    sx={{
                      justifyContent: 'space-between',
                      alignItems: 'center'
                    }}
                  >
                    <Typography variant='subtitle1'>Type:</Typography>
                    <Typography variant='caption'>{journalEntry?.type.toUpperCase()}</Typography>
                  </Stack>

                  <Stack
                    direction='row'
                    sx={{
                      justifyContent: 'space-between',
                      alignItems: 'center'
                    }}
                  >
                    <Typography variant='subtitle1'>Fund Source:</Typography>
                    <Typography variant='caption'>{journalEntry?.fundSource}</Typography>
                  </Stack>

                  <Stack
                    direction='row'
                    sx={{
                      justifyContent: 'space-between',
                      alignItems: 'center'
                    }}
                  >
                    <Typography variant='subtitle1'>Amount:</Typography>
                    <Typography variant='caption'>
                      {currencyFormatter.format(journalEntry?.amount as number)}
                    </Typography>
                  </Stack>

                  <Stack
                    direction='row'
                    sx={{
                      justifyContent: 'space-between',
                      alignItems: 'center'
                    }}
                  >
                    <Typography variant='subtitle1'>Reference Number:</Typography>
                    <Typography variant='caption'>
                      {journalEntry?.referenceNumber ? journalEntry?.referenceNumber : '-'}
                    </Typography>
                  </Stack>

                  <Stack
                    direction='row'
                    sx={{
                      justifyContent: 'space-between',
                      alignItems: 'center'
                    }}
                  >
                    <Typography variant='subtitle1'>Status:</Typography>
                    <Typography variant='caption'>{journalEntry?.status.toUpperCase()}</Typography>
                  </Stack>
                </Stack>

                {/* Right Column */}
                <Stack spacing={2} sx={{ flex: 1 }}>
                  <Typography variant='subtitle1'>Note:</Typography>
                  <Typography variant='caption'>{journalEntry?.note ? journalEntry?.note : '-'}</Typography>
                </Stack>
              </Paper>
            </Stack>

            {/* Ledger */}
            <Stack
              spacing={2}
              direction='column'
              sx={{
                justifyContent: 'flex-start',
                alignItems: 'stretch'
              }}
            >
              <Typography variant='h6'>Ledger</Typography>
              <Paper elevation={3} sx={{ p: 4 }}>
                <Table>
                  <TableHead sx={{ backgroundColor: theme => theme.palette.background.default }}>
                    <TableRow>
                      <TableCell>
                        <strong>ACCOUNT CODE</strong>
                      </TableCell>
                      <TableCell>
                        <strong>ACCOUNT NAME</strong>
                      </TableCell>
                      <TableCell>
                        <strong>DESCRIPTION</strong>
                      </TableCell>
                      <TableCell align='right'>
                        <strong>TAX</strong>
                      </TableCell>
                      <TableCell align='right'>
                        <strong>DEBIT</strong>
                      </TableCell>
                      <TableCell align='right'>
                        <strong>CREDIT</strong>
                      </TableCell>
                    </TableRow>
                  </TableHead>

                  <TableBody>
                    {journalEntry?.journalItems.map((item, index) => (
                      <TableRow key={`${item.accountCode} ${item.accountName}`}>
                        <TableCell
                          sx={{
                            borderBottom:
                              index + 1 === journalEntry?.journalItems.length
                                ? theme => `1px solid ${theme.palette.divider}`
                                : 'none'
                          }}
                        >
                          {item.accountCode}
                        </TableCell>
                        <TableCell
                          sx={{
                            borderBottom:
                              index + 1 === journalEntry?.journalItems.length
                                ? theme => `1px solid ${theme.palette.divider}`
                                : 'none'
                          }}
                        >
                          {item.accountName}
                        </TableCell>
                        <TableCell
                          sx={{
                            borderBottom:
                              index + 1 === journalEntry?.journalItems.length
                                ? theme => `1px solid ${theme.palette.divider}`
                                : 'none'
                          }}
                        >
                          {item.description ? item.description : ''}
                        </TableCell>
                        <TableCell
                          align='right'
                          sx={{
                            borderBottom:
                              index + 1 === journalEntry?.journalItems.length
                                ? theme => `1px solid ${theme.palette.divider}`
                                : 'none'
                          }}
                        >
                          {item.taxRate ? currencyFormatter.format(item.amount * (item.taxRate * 0.01)) : ''}
                          <br />
                          {item.taxRate ? (
                            <Typography variant='subtitle2'>{`${item.taxName} [${item.taxRate}%]`}</Typography>
                          ) : (
                            ''
                          )}
                        </TableCell>
                        <TableCell
                          align='right'
                          sx={{
                            borderBottom:
                              index + 1 === journalEntry?.journalItems.length
                                ? theme => `1px solid ${theme.palette.divider}`
                                : 'none'
                          }}
                        >
                          {item.type === 'debit' ? currencyFormatter.format(item.amount) : ''}
                        </TableCell>
                        <TableCell
                          align='right'
                          sx={{
                            borderBottom:
                              index + 1 === journalEntry?.journalItems.length
                                ? theme => `1px solid ${theme.palette.divider}`
                                : 'none'
                          }}
                        >
                          {item.type === 'credit' ? currencyFormatter.format(item.amount) : ''}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>

                  <TableFooter>
                    {/* Sub Total Row */}
                    <TableRow>
                      <TableCell
                        colSpan={4}
                        align='right'
                        sx={{
                          fontWeight: 'bold',
                          borderBottom:
                            taxesApplied.length !== 0 ? 'none' : theme => `1px solid ${theme.palette.divider}`
                        }}
                      >
                        <Typography variant='subtitle2'>Sub Total</Typography>
                      </TableCell>
                      <TableCell
                        align='right'
                        sx={{
                          fontWeight: 'bold',
                          borderBottom:
                            taxesApplied.length !== 0 ? 'none' : theme => `1px solid ${theme.palette.divider}`
                        }}
                      >
                        <Typography variant='subtitle2'>{currencyFormatter.format(debitSubTotal)}</Typography>
                      </TableCell>
                      <TableCell
                        align='right'
                        sx={{
                          fontWeight: 'bold',
                          borderBottom:
                            taxesApplied.length !== 0 ? 'none' : theme => `1px solid ${theme.palette.divider}`
                        }}
                      >
                        <Typography variant='subtitle2'>{currencyFormatter.format(creditSubTotal)}</Typography>
                      </TableCell>
                    </TableRow>

                    {/* Tax Rows */}
                    {taxesApplied.length !== 0 &&
                      taxesApplied.map((currentTax, index) => (
                        <TableRow key={currentTax.taxName}>
                          <TableCell
                            colSpan={4}
                            align='right'
                            sx={{
                              fontWeight: 'bold',
                              borderBottom:
                                index + 1 === taxesApplied.length
                                  ? theme => `1px solid ${theme.palette.divider}`
                                  : 'none'
                            }}
                          >
                            <Typography variant='subtitle2'>
                              {`${currentTax.taxName} ( ${currentTax.taxRate}% )`}
                            </Typography>
                          </TableCell>
                          <TableCell
                            align='right'
                            sx={{
                              fontWeight: 'bold',
                              borderBottom:
                                index + 1 === taxesApplied.length
                                  ? theme => `1px solid ${theme.palette.divider}`
                                  : 'none'
                            }}
                          >
                            <Typography variant='subtitle2'>{currencyFormatter.format(currentTax.debit)}</Typography>
                          </TableCell>
                          <TableCell
                            align='right'
                            sx={{
                              fontWeight: 'bold',
                              borderBottom:
                                index + 1 === taxesApplied.length
                                  ? theme => `1px solid ${theme.palette.divider}`
                                  : 'none'
                            }}
                          >
                            <Typography variant='subtitle2'>{currencyFormatter.format(currentTax.credit)}</Typography>
                          </TableCell>
                        </TableRow>
                      ))}

                    {/* Total Row */}
                    <TableRow>
                      <TableCell colSpan={4} align='right' sx={{ fontWeight: 'bold' }}>
                        <Typography variant='subtitle2'>TOTAL</Typography>
                      </TableCell>
                      <TableCell align='right' sx={{ fontWeight: 'bold' }}>
                        <Typography variant='subtitle2'>{currencyFormatter.format(debitTotalWithTax)}</Typography>
                      </TableCell>
                      <TableCell align='right' sx={{ fontWeight: 'bold' }}>
                        <Typography variant='subtitle2'>{currencyFormatter.format(creditTotalWithTax)}</Typography>
                      </TableCell>
                    </TableRow>
                  </TableFooter>
                </Table>
              </Paper>
            </Stack>
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button
            onClick={handleClose}
            color={journalEntry?.status === 'draft' ? 'secondary' : 'primary'}
            variant={journalEntry?.status === 'draft' ? 'outlined' : 'contained'}
          >
            Close
          </Button>

          {journalEntry?.status === 'draft' && canManageJournalEntries && (
            <Button variant='contained' onClick={() => setIsConfirmationModalOpen(true)}>
              Post
            </Button>
          )}
        </DialogActions>
      </Dialog>

      {/* Post Confirmation Modal */}
      {journalEntry?.status === 'draft' && canManageJournalEntries && (
        <Dialog open={isConfirmationModalOpen} disableEscapeKeyDown>
          <DialogTitle>Post Confirmation</DialogTitle>

          <DialogContent>
            <DialogContentText>
              {`You are about to post Journal Entry #${formattedEntryNumber}.
              Posting will finalize the transaction and update your ledger balances
              and financial reports. Once posted, the entry can no longer be edited. `}
              <span style={{ fontWeight: 'bold', textDecoration: 'underline' }}>This cannot be undone</span>.
            </DialogContentText>
          </DialogContent>

          <DialogActions>
            <Button onClick={() => setIsConfirmationModalOpen(false)} color='secondary' variant='outlined'>
              Cancel
            </Button>

            <Button variant='contained' onClick={handleEntryPosting}>
              Post
            </Button>
          </DialogActions>
        </Dialog>
      )}
    </>
  )
}

export default JournalEntryViewModal
