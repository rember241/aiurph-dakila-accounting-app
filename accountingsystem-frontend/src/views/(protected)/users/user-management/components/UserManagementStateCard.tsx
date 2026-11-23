import Button from '@mui/material/Button'
import Paper from '@mui/material/Paper'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'

type UserManagementStateCardProps = {
  title: string
  description: string
  iconClassName: string
  actionLabel?: string
  onAction?: () => void
}

function UserManagementStateCard({
  title,
  description,
  iconClassName,
  actionLabel,
  onAction
}: UserManagementStateCardProps) {
  return (
    <Paper sx={{ p: 8 }}>
      <Stack spacing={2} alignItems='center' textAlign='center'>
        <i className={iconClassName} style={{ fontSize: 40 }} />
        <Typography variant='h5'>{title}</Typography>
        <Typography variant='body2' color='text.secondary' sx={{ maxWidth: 480 }}>
          {description}
        </Typography>
        {actionLabel && onAction ? (
          <Button variant='contained' onClick={onAction} startIcon={<i className='bx bx-refresh' />}>
            {actionLabel}
          </Button>
        ) : null}
      </Stack>
    </Paper>
  )
}

export default UserManagementStateCard
