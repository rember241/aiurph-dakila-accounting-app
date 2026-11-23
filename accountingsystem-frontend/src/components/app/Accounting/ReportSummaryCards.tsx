import Box from '@mui/material/Box'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'

import CustomKpiCard from '@components/app/CustomKPICard'

type ReportSummaryItem = {
  label: string
  value: string | number
  icon: string
  tone: 'primary' | 'secondary' | 'success' | 'warning' | 'error' | 'info'
  caption?: string
}

type ReportSummaryCardsProps = {
  items: ReportSummaryItem[]
  loading?: boolean
}

/** Compact, responsive KPI strip shared by accounting reports. */
export default function ReportSummaryCards({ items, loading = false }: ReportSummaryCardsProps) {
  return (
    <Stack direction={{ xs: 'column', md: 'row' }} spacing={3} sx={{ width: '100%' }}>
      {items.map(item => (
        <Box key={item.label} sx={{ flex: 1, minWidth: 0 }}>
          <CustomKpiCard
            icon={<i className={item.icon} style={{ fontSize: 24 }} />}
            iconColor={item.tone}
            label={item.label}
            value={item.value}
            loading={loading}
          >
            {item.caption && (
              <Typography variant='caption' color='text.secondary'>
                {item.caption}
              </Typography>
            )}
          </CustomKpiCard>
        </Box>
      ))}
    </Stack>
  )
}
