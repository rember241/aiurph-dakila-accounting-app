import { Button, Dialog, DialogActions, DialogContent, DialogContentText, DialogTitle } from '@mui/material'

// Props for this functional component
interface ModalProps {
  open: boolean
  onClose: () => void
  onCancel: () => void
  onConfirm: () => void
}

function EditConfirmationModal({ open, onClose, onCancel, onConfirm }: ModalProps) {
  return (
    <Dialog disableEscapeKeyDown open={open} onClose={onClose}>
      <DialogTitle>Confirm Update</DialogTitle>
      <DialogContent>
        <DialogContentText>
          <span style={{ fontWeight: 'bold', textDecoration: 'underline' }}>
            This journal number has been used in previous transactions.
          </span>{' '}
          Editing it will archive the current record and create a new one with your changes. This ensures that existing
          transactions referencing this journal number remain consistent. This will also reset the starting number back
          to 1. This cannot be undone.
        </DialogContentText>
      </DialogContent>
      <DialogActions>
        <Button key='cancel-edit' color='secondary' onClick={onCancel}>
          Cancel
        </Button>
        <Button key='confirm-edit' variant='contained' onClick={onConfirm}>
          Confirm
        </Button>
      </DialogActions>
    </Dialog>
  )
}

export default EditConfirmationModal
