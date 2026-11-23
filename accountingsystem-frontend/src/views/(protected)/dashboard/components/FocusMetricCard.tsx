'use client'

import Link from 'next/link'

import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Chip from '@mui/material/Chip'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'

import { toneToColor, type FocusMetricData } from '@views/(protected)/dashboard/types'

export function FocusMetricCard({ title, value, tone, helper, href, actionLabel }: FocusMetricData) {
  return (
    <Card variant='outlined' sx={{ height: '100%', borderRadius: 4, borderColor: 'divider' }}>
      <CardContent sx={{ p: 4.5 }}>
        <Stack spacing={3} height='100%'>
          <Stack direction='row' justifyContent='space-between' alignItems='flex-start' spacing={2}>
            <Stack spacing={1}>
              <Typography variant='overline' color='text.secondary'>
                {title}
              </Typography>
              <Typography variant='h3' fontWeight={800} sx={{ color: toneToColor(tone) }}>
                {value}
              </Typography>
            </Stack>
            <Chip size='small' variant='tonal' color={tone} label='Priority Control' />
          </Stack>

          <Typography variant='body2' color='text.secondary'>
            {helper}
          </Typography>

          <Box sx={{ mt: 'auto' }}>
            <Link href={href}>
              <Button component='span' size='small' color={tone} endIcon={<i className='bx bx-right-arrow-alt' />}>
                {actionLabel}
              </Button>
            </Link>
          </Box>
        </Stack>
      </CardContent>
    </Card>
  )
}
