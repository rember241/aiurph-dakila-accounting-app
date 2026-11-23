// Props for this functional component
import { Button, Dialog, DialogActions, DialogContent, DialogContentText, DialogTitle } from '@mui/material'

import type { JournalNumberColumn } from '@/features/(protected)/accounting/setup/transaction-series/model/transaction-series.types'

interface ModalProps {
  open: boolean
  onClose: () => void
  journalNumber: JournalNumberColumn | null
  isDeleting: boolean
  onCancel: () => void
  onDelete: () => void
}

function DeleteConfirmationModal({ open, onClose, journalNumber, isDeleting, onCancel, onDelete }: ModalProps) {
  return (
    <Dialog disableEscapeKeyDown open={open} onClose={onClose}>
      <DialogTitle>Confirm Delete</DialogTitle>
      <DialogContent>
        <DialogContentText>
          {journalNumber?.isUsed ? (
            <>
              <span style={{ fontWeight: 'bold', textDecoration: 'underline' }}>
                Deleting this journal number will not remove it from existing transactions.
              </span>{' '}
              It will only be removed from the active list and can no longer be selected for new transactions. This
              cannot be undone.
            </>
          ) : (
            `Are you sure you want to delete "${journalNumber?.name}"`
          )}
        </DialogContentText>
      </DialogContent>
      <DialogActions>
        <Button color='secondary' key='cancel-delete' disabled={isDeleting} onClick={onCancel}>
          Cancel
        </Button>
        <Button color='error' variant='contained' key='confirm-delete' onClick={onDelete} disabled={isDeleting}>
          Delete
        </Button>
      </DialogActions>
    </Dialog>
  )
}

export default DeleteConfirmationModal
