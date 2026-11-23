import Box from '@mui/material/Box'

type FullLogoProps = {
  src?: string | null
  alt?: string
}

export default function FullLogo({ src, alt = 'Dakila' }: FullLogoProps) {
  return (
    <Box
      sx={{
        display: 'inline-flex',
        alignItems: 'center',
        width: { xs: 180, sm: 220 },
        maxWidth: '100%'
      }}
    >
      <Box
        component='img'
        src={src || '/images/logos/dakila-logo.png'}
        alt={alt}
        sx={{
          width: '100%',
          height: 'auto',
          display: 'block'
        }}
      />
    </Box>
  )
}
