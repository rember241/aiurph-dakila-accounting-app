'use client'

import dynamic from 'next/dynamic'

import Box from '@mui/material/Box'
import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'

import type { ApexOptions } from 'apexcharts'

import type { KpiCardData } from '@views/(protected)/dashboard/types'

const AppReactApexCharts = dynamic(() => import('@/libs/styles/AppReactApexCharts'))

export function DashboardKpiCard({ title, value, tone, caption, trend }: KpiCardData) {
  const options: ApexOptions = {
    chart: { sparkline: { enabled: true }, toolbar: { show: false } },
    stroke: { width: 3, curve: 'smooth' },
    fill: {
      type: 'gradient',
      gradient: { shadeIntensity: 0.18, opacityFrom: 0.26, opacityTo: 0.04, stops: [0, 100] }
    },
    colors: [`var(--mui-palette-${tone}-main)`],
    tooltip: { enabled: false },
    grid: { show: false },
    xaxis: { labels: { show: false }, axisTicks: { show: false }, axisBorder: { show: false } },
    yaxis: { labels: { show: false } },
    states: { hover: { filter: { type: 'none' } }, active: { filter: { type: 'none' } } }
  }

  return (
    <Card variant='outlined' sx={{ height: '100%', borderColor: 'divider' }}>
      <CardContent sx={{ p: 4 }}>
        <Stack spacing={2}>
          <Stack direction='row' spacing={2} alignItems='center' justifyContent='space-between'>
            <Stack spacing={1.25} sx={{ minWidth: 0 }}>
              <Typography variant='subtitle2' color='text.secondary'>
                {title}
              </Typography>
              <Typography variant='h4' component='p' sx={{ overflowWrap: 'anywhere' }}>
                {value}
              </Typography>
            </Stack>

            {trend.length > 1 && (
              <Box sx={{ flexShrink: 0, width: { xs: 70, sm: 92 } }}>
                <AppReactApexCharts
                  type='area'
                  height={56}
                  width='100%'
                  series={[{ name: title, data: trend }]}
                  options={options}
                />
              </Box>
            )}
          </Stack>

          <Typography variant='body2' color='text.secondary'>
            {caption}
          </Typography>
        </Stack>
      </CardContent>
    </Card>
  )
}
