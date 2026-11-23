import type { AvatarProps } from '@mui/material/Avatar'

import type { CustomAvatarProps } from '@core/components/mui/Avatar'
import type { ThemeColor } from '@core/types'

type TrendDirection = 'positive' | 'negative'

type AvatarStatProps = {
  avatarIcon: string
  avatarColor?: ThemeColor
  avatarVariant?: AvatarProps['variant']
  avatarSkin?: CustomAvatarProps['skin']
  avatarSize?: number
}

export type CardStatsVerticalWithHeaderProps = AvatarStatProps & {
  title: string
  subtitle: string
  stats: string | number
  trendNumber: number
  trend: TrendDirection
}

export type CardStatsHorizontalWithAvatarProps = AvatarStatProps & {
  title: string
  stats: string | number
}

export type CardStatsVerticalProps = {
  title: string
  imageSrc: string
  stats: string | number
  trendNumber: number
  trend: TrendDirection
}

export type CardStatsHorizontalWithBorderProps = {
  title: string
  stats: string | number
  trendNumber: number
  avatarIcon: string
  color?: ThemeColor
}
