'use client'

import Box from '@mui/material/Box'
import CircularProgress from '@mui/material/CircularProgress'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'

import { useMeQuery } from '@/features/auth/api/auth.queries'

function CompanySwitcher() {
  const meQuery = useMeQuery()
  const currentCompany = meQuery.data?.user?.company ?? null
  const isLoading = meQuery.isLoading || meQuery.isFetching
  const title = currentCompany?.name ?? (isLoading ? 'Loading company' : 'Company setup required')

  const helperText = currentCompany?.slug
    ? `/${currentCompany.slug}`
    : isLoading
      ? 'Checking company profile'
      : 'Complete company setup to continue'

  return (
    <Box
      sx={{
        minWidth: { xs: 180, md: 240 },
        maxWidth: 320,
        border: 1,
        borderColor: 'divider',
        borderRadius: 2,
        px: 2,
        py: 1.25,
        bgcolor: 'background.paper'
      }}
    >
      <Stack direction='row' spacing={1.5} alignItems='center' justifyContent='space-between'>
        <Stack direction='row' spacing={1.5} alignItems='center' sx={{ minWidth: 0 }}>
          <Box
            component='i'
            className='bx-buildings'
            sx={{ color: 'text.secondary', fontSize: 20, flexShrink: 0 }}
          />

          <Stack spacing={0.25} sx={{ minWidth: 0 }}>
            <Typography noWrap variant='body2' fontWeight={600}>
              {title}
            </Typography>
            <Typography noWrap color='text.secondary' variant='caption'>
              {helperText}
            </Typography>
          </Stack>
        </Stack>

        {isLoading ? <CircularProgress size={16} /> : null}
      </Stack>
    </Box>
  )
}

export default CompanySwitcher
