import Box from '@mui/material/Box'
import Stack from '@mui/material/Stack'
import Typography from '@mui/material/Typography'

type PageTitleProps = {
  title: string
  icon: string
  variant?: 'h2' | 'h3' | 'h4'
  component?: 'h1' | 'h2'
}

export default function PageTitle({ title, icon, variant = 'h3', component = 'h1' }: PageTitleProps) {
  return (
    <Stack direction='row' spacing={2.5} alignItems='center'>
      <Box
        component='span'
        aria-hidden='true'
        sx={{
          display: 'inline-flex',
          alignItems: 'center',
          justifyContent: 'center',
          flexShrink: 0,
          width: { xs: 38, sm: 46 },
          height: { xs: 38, sm: 46 },
          borderRadius: 2,
          color: 'primary.main',
          bgcolor: 'primary.lightOpacity',
          '& i': { fontSize: { xs: '1.25rem', sm: '1.5rem' } }
        }}
      >
        <i className={icon} />
      </Box>
      <Typography variant={variant} component={component} fontWeight={700} sx={{ minWidth: 0, overflowWrap: 'anywhere' }}>
        {title}
      </Typography>
    </Stack>
  )
}
