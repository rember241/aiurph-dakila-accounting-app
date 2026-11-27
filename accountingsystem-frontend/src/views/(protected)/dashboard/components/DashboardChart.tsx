'use client'

import type { ReactNode } from 'react'

import Card from '@mui/material/Card'
import CardContent from '@mui/material/CardContent'
import CardHeader from '@mui/material/CardHeader'
import Skeleton from '@mui/material/Skeleton'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'
import { useColorScheme, useTheme } from '@mui/material/styles'

import type { ApexOptions } from 'apexcharts'

import AppReactApexCharts from '@/libs/styles/AppReactApexCharts'

import type { DashboardTone } from '../types'

type Series = { name: string; data: (number | null)[]; tone: DashboardTone }

type Props = {
  title: string
  subtitle: string
  labels: string[]
  series: Series[]
  type: 'area' | 'bar'
  loading: boolean
  emptyMessage: string
  children?: ReactNode
  featured?: boolean
}

const axisAmount = new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 })
const tooltipAmount = new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })

export function DashboardChart({
  title,
  subtitle,
  labels,
  series,
  type,
  loading,
  emptyMessage,
  children,
  featured
}: Props) {
  const theme = useTheme()
  const { mode, systemMode } = useColorScheme()
  const colorMode = mode === 'system' ? systemMode : mode
  const palette = (colorMode && theme.colorSchemes?.[colorMode]?.palette) || theme.palette
  const visibleSeries = series.filter(item => item.data.some(value => value !== null))
  const colors = visibleSeries.map(item => palette[item.tone].main)

  const options: ApexOptions = {
    chart: { toolbar: { show: false }, zoom: { enabled: false }, foreColor: palette.text.secondary },
    colors,
    dataLabels: { enabled: false },
    grid: { borderColor: palette.divider, strokeDashArray: 4 },
    legend: { show: visibleSeries.length > 1, position: 'top', horizontalAlign: 'left' },
    stroke: { curve: 'straight', width: type === 'area' ? 3 : 0 },
    fill: type === 'area' ? { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } } : { opacity: 1 },
    plotOptions: { bar: { columnWidth: '54%', borderRadius: 3 } },
    tooltip: { y: { formatter: value => tooltipAmount.format(value) } },
    xaxis: { categories: labels, axisBorder: { show: false }, axisTicks: { show: false } },
    yaxis: { forceNiceScale: true, labels: { formatter: value => axisAmount.format(value) } }
  }

  return (
    <Card
      component='section'
      aria-label={title}
      sx={{
        height: '100%',
        ...(featured && { border: 1, borderColor: 'primary.main', bgcolor: 'primary.lightOpacity' })
      }}
    >
      <CardHeader
        title={
          <Typography variant='h5' component='h2'>
            {title}
          </Typography>
        }
        subheader={subtitle}
      />
      <CardContent sx={{ pt: 0 }}>
        {children}
        {loading ? (
          <Skeleton variant='rounded' height={210} />
        ) : visibleSeries.length ? (
          <AppReactApexCharts
            type={type}
            height={210}
            series={visibleSeries.map(({ name, data }) => ({ name, data }))}
            options={options}
          />
        ) : (
          <Stack alignItems='center' justifyContent='center' sx={{ height: 210 }}>
            <Typography variant='body2' color='text.secondary'>
              {emptyMessage}
            </Typography>
          </Stack>
        )}
      </CardContent>
    </Card>
  )
}
