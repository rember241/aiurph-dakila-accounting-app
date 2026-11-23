// Next Imports
import localFont from 'next/font/local'

// MUI Imports
import type { Theme } from '@mui/material/styles'

// Type Imports
import type { Settings } from '@core/contexts/settingsContext'
import type { Skin, SystemMode } from '@core/types'

// Theme Options Imports
import overrides from './overrides'
import colorSchemes from './colorSchemes'
import spacing from './spacing'
import shadows from './shadows'
import customShadows from './customShadows'
import typography from './typography'

const satoshi = localFont({
  src: [
    { path: '../../assets/fonts/Satoshi-Light.woff2', weight: '300', style: 'normal' },
    { path: '../../assets/fonts/Satoshi-Regular.woff2', weight: '400', style: 'normal' },
    { path: '../../assets/fonts/Satoshi-Medium.woff2', weight: '500', style: 'normal' },
    { path: '../../assets/fonts/Satoshi-Bold.woff2', weight: '700', style: 'normal' },
    { path: '../../assets/fonts/Satoshi-Black.woff2', weight: '900', style: 'normal' }
  ],
  display: 'swap',
  fallback: ['Arial', 'sans-serif']
})

const theme = (settings: Settings, mode: SystemMode, direction: Theme['direction']): Theme => {
  return {
    direction,
    components: overrides(settings.skin as Skin),
    colorSchemes: colorSchemes(settings.skin as Skin),
    ...spacing,
    shape: {
      borderRadius: 6,
      customBorderRadius: {
        xs: 2,
        sm: 4,
        md: 6,
        lg: 8,
        xl: 10
      }
    },
    shadows: shadows(mode),
    typography: typography(satoshi.style.fontFamily),
    customShadows: customShadows(mode),
    mainColorChannels: {
      light: '8 28 45',
      dark: '245 247 250',
      lightShadow: '8 28 45',
      darkShadow: '3 12 20'
    }
  } as Theme
}

export default theme
