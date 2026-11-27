import Link from 'next/link'

import Button from '@mui/material/Button'
import Paper from '@mui/material/Paper'
import Stack from '@mui/material/Stack'

import { APP_ABILITIES } from '@/features/auth/access/model/access.schema'

const actions = [
  {
    label: 'New Journal Entry',
    href: '/accounting/transactions/journal-entries/new',
    icon: 'bx-edit',
    abilities: [APP_ABILITIES.journalCreate]
  },
  {
    label: 'Chart of Accounts',
    href: '/accounting/setup/accounts',
    icon: 'bx-list-ul',
    abilities: [APP_ABILITIES.accountingRead]
  },
  {
    label: 'Trial Balance',
    href: '/accounting/reports/trial-balance',
    icon: 'bx-spreadsheet',
    abilities: [APP_ABILITIES.accountingRead]
  },
  {
    label: 'Cash Flow',
    href: '/accounting/reports/cash-flow',
    icon: 'bx-trending-up',
    abilities: [APP_ABILITIES.accountingRead]
  },
  {
    label: 'Fund Sources',
    href: '/accounting/setup/fund-sources',
    icon: 'bx-wallet-alt',
    abilities: [APP_ABILITIES.accountingRead, APP_ABILITIES.journalRead]
  },
  {
    label: 'Accounting Periods',
    href: '/accounting/setup/periods',
    icon: 'bx-calendar',
    abilities: [APP_ABILITIES.periodRead]
  }
]

export function DashboardQuickActions({ canAny }: { canAny: (abilities: readonly string[]) => boolean }) {
  const visibleActions = actions.filter(action => canAny(action.abilities))

  if (!visibleActions.length) return null

  return (
    <Paper component='nav' aria-label='Quick actions' variant='outlined' sx={{ p: 1.5 }}>
      <Stack direction='row' flexWrap='wrap' gap={1}>
        {visibleActions.map(({ label, href, icon }) => (
          <Button
            key={href}
            component={Link}
            href={href}
            size='small'
            color={href.endsWith('/new') ? 'primary' : 'secondary'}
            variant={href.endsWith('/new') ? 'tonal' : 'text'}
            startIcon={<i className={icon} aria-hidden='true' />}
          >
            {label}
          </Button>
        ))}
      </Stack>
    </Paper>
  )
}
