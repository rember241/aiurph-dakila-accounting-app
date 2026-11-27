'use client'

import type { ReactNode } from 'react'

import { usePathname } from 'next/navigation'

import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'

import { getPageNavigation } from '@/data/navigation/pageNavigation'

import PageBreadcrumbs from './PageBreadcrumbs'
import PageTitle from './PageTitle'

interface PageHeaderProps {
  title: string
  description?: string
  children?: ReactNode
}

/**
 * Reusable page header for feature screens.
 * The optional `children` slot holds the primary action or action group.
 */
function CustomPageHeader({ title, description, children }: PageHeaderProps) {
  const { icon, breadcrumbs } = getPageNavigation(usePathname(), title)

  return (
    <Stack
      direction={{ xs: 'column', md: 'row' }}
      justifyContent='space-between'
      alignItems={{ xs: 'flex-start', md: 'center' }}
      className='mb-5'
      spacing={2}
    >
      <Stack spacing={1.5} sx={{ minWidth: 0 }}>
        <PageTitle title={title} icon={icon} />
        <PageBreadcrumbs items={breadcrumbs} />
        {description && (
          <Typography variant='body2' color='text.secondary'>
            {description}
          </Typography>
        )}
      </Stack>
      {children}
    </Stack>
  )
}

export default CustomPageHeader
