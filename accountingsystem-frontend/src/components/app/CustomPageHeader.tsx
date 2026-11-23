import React from 'react'

import { Stack, Typography } from '@mui/material'

interface PageHeaderProps {
  title: string
  description: string // Optional prop
  children?: React.ReactNode
}

/**
 * Reusable page header for feature screens.
 *
 * The optional `children` slot is where pages usually place the primary action
 * button or a small action group.
 */
function CustomPageHeader({ title, description, children }: PageHeaderProps) {
  return (
    <Stack
      direction={{ xs: 'column', sm: 'column', md: 'row' }}
      justifyContent={'space-between'}
      alignItems={'center'}
      className={'mb-5'}
      spacing={2}
    >
      <Stack direction={'column'}>
        <Typography variant={'h2'} fontWeight={'bold'}>
          {title}
        </Typography>
        <Typography variant={'h5'}>{description}</Typography>
      </Stack>
      {children}
    </Stack>
  )
}

export default CustomPageHeader
