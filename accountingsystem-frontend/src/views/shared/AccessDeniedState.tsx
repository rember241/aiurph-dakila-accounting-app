import Link from 'next/link'

import Button from '@mui/material/Button'
import Paper from '@mui/material/Paper'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'

import { DEFAULT_PROTECTED_REDIRECT } from '@/features/auth/model/auth-routes'

type AccessDeniedStateProps = {
  title: string
  description: string
  actionHref?: string
  actionLabel?: string
}

export function AccessDeniedState({
  title,
  description,
  actionHref = DEFAULT_PROTECTED_REDIRECT,
  actionLabel = 'Back to workspace'
}: AccessDeniedStateProps) {
  return (
    <Paper sx={{ p: { xs: 4, md: 6 }, border: 1, borderColor: 'divider' }}>
      <Stack spacing={2.5} alignItems='flex-start' sx={{ maxWidth: 560 }}>
        <Stack direction='row' spacing={1.5} alignItems='center'>
          <i className='bx bx-shield-x' style={{ fontSize: 24 }} />
          <Typography variant='h5'>{title}</Typography>
        </Stack>
        <Typography variant='body2' color='text.secondary'>
          {description}
        </Typography>
        <Link href={actionHref}>
          <Button component='span' variant='contained'>
            {actionLabel}
          </Button>
        </Link>
      </Stack>
    </Paper>
  )
}
