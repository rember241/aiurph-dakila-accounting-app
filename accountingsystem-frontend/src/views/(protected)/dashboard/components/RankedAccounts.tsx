import { useState } from 'react'

import { Box, Button, Card, CardContent, Skeleton, Stack, Typography } from '@mui/material'

import type { RankedAccount } from '../hooks/useDashboardData'

type Props = { title: string; period: string; icon: string; rows?: RankedAccount[]; loading: boolean }

const amount = new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })

export function RankedAccounts({ title, period, icon, rows, loading }: Props) {
  const [expanded, setExpanded] = useState(false)

  return (
    <Card variant='outlined' component='section' aria-label={title}>
      <CardContent>
        <Stack direction='row' gap={2.5} alignItems='center'>
          <Box component='i' aria-hidden='true' className={icon} sx={{ color: 'text.secondary', fontSize: 22 }} />
          <div>
            <Typography component='h2' variant='h6'>
              {title}
            </Typography>
            <Typography variant='caption' color='text.secondary'>
              By posted amount · {period}
            </Typography>
          </div>
        </Stack>
        {loading ? (
          <Skeleton sx={{ mt: 2 }} />
        ) : rows?.length ? (
          <Box component='ol' sx={{ m: 0, mt: 2, p: 0, listStyle: 'none' }}>
            {rows.slice(0, expanded ? 10 : 3).map((row, index) => (
              <Stack
                component='li'
                key={row.id}
                direction='row'
                alignItems='baseline'
                gap={2}
                sx={{ py: 1.5, borderTop: 1, borderColor: 'divider' }}
              >
                <Typography variant='caption' color='text.secondary'>
                  {index + 1}
                </Typography>
                <Typography variant='body2' sx={{ flex: 1, overflowWrap: 'anywhere' }}>
                  {row.name}
                </Typography>
                <Typography variant='body2' sx={{ fontVariantNumeric: 'tabular-nums' }}>
                  {amount.format(Number(row.amount))}
                </Typography>
              </Stack>
            ))}
          </Box>
        ) : (
          <Typography variant='body2' color='text.secondary' sx={{ mt: 2 }}>
            {rows ? 'No positive balances this month.' : 'Ranking unavailable.'}
          </Typography>
        )}
        {rows && rows.length > 3 && (
          <Button size='small' color='secondary' aria-expanded={expanded} onClick={() => setExpanded(!expanded)}>
            {expanded ? 'Show fewer' : `Show all ${rows.length}`}
          </Button>
        )}
      </CardContent>
    </Card>
  )
}
