import { APP_ABILITIES } from '@/features/auth/access/model/access.schema'
import type { HorizontalMenuDataType } from '@/types/menuTypes'

const horizontalMenuData = (): HorizontalMenuDataType[] => [
  {
    label: 'Overview',
    href: '/home',
    icon: 'bx-home-circle',
    requiredAbilities: [APP_ABILITIES.accountingRead]
  },
  {
    label: 'General Ledger',
    href: '/accounting/books/ledger',
    icon: 'bx-book',
    requiredAbilities: [APP_ABILITIES.accountingRead]
  }
]

export default horizontalMenuData
