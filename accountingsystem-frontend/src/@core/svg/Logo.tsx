import Box from '@mui/material/Box'

type LogoProps = {
  src?: string | null
  alt?: string
}

export default function Logo({ src, alt = 'Dakila' }: LogoProps) {
  return (
    <Box
      component='img'
      src={src || '/images/logos/dakila-logo.png'}
      alt={alt}
      sx={{
        width: 36,
        height: 36,
        display: 'block',
        objectFit: 'contain',
        flexShrink: 0
      }}
    />
  )
}
