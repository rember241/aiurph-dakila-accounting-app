import Breadcrumbs from '@mui/material/Breadcrumbs'
import Link from '@mui/material/Link'
import Typography from '@mui/material/Typography'

import AppLink from '@components/Link'
import type { BreadcrumbItem } from '@/data/navigation/pageNavigation'

const crumbStyles = {
  display: 'inline-flex',
  alignItems: 'center',
  gap: 1,
  '& i': { fontSize: '1rem', flexShrink: 0 }
} as const

export default function PageBreadcrumbs({ items }: { items: BreadcrumbItem[] }) {
  return (
    <Breadcrumbs
      aria-label='Breadcrumb'
      separator={<i className='bx-chevron-right' aria-hidden='true' />}
      sx={{
        '& .MuiBreadcrumbs-ol': { rowGap: 0.5 },
        '& .MuiBreadcrumbs-separator': { mx: 1, color: 'text.secondary' },
        '& .MuiBreadcrumbs-separator i': { fontSize: '0.875rem' }
      }}
    >
      {items.map(({ label, href, icon }, index) => {
        const current = index === items.length - 1

        const content = (
          <>
            <i className={icon} aria-hidden='true' />
            <span>{label}</span>
          </>
        )

        return href ? (
          <Link key={label} component={AppLink} href={href} variant='body2' underline='hover' sx={crumbStyles}>
            {content}
          </Link>
        ) : (
          <Typography
            key={label}
            component='span'
            variant='body2'
            color={current ? 'text.primary' : 'text.secondary'}
            aria-current={current ? 'page' : undefined}
            sx={current ? { ...crumbStyles, fontWeight: 600 } : crumbStyles}
          >
            {content}
          </Typography>
        )
      })}
    </Breadcrumbs>
  )
}
