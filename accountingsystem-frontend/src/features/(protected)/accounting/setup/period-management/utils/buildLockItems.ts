import type { VerticalOption } from '@components/app/CustomInputVertical'

export function buildLockItems(periodData: {
  lock_ar?: boolean
  lock_ap?: boolean
  lock_inventory?: boolean
}): VerticalOption[] {
  return [
    {
      value: 'lock_ar',
      title: 'Accounts Receivable',
      content: periodData.lock_ar ? 'Locked' : 'Unlocked',
      asset: periodData.lock_ar ? 'bx bx-lock-alt' : 'bx bx-lock-open-alt',
      color: periodData.lock_ar ? 'error' : 'success'
    },
    {
      value: 'lock_ap',
      title: 'Accounts Payable',
      content: periodData.lock_ap ? 'Locked' : 'Unlocked',
      asset: periodData.lock_ap ? 'bx bx-lock-alt' : 'bx bx-lock-open-alt',
      color: periodData.lock_ap ? 'error' : 'success'
    },
    {
      value: 'lock_inventory',
      title: 'Inventory',
      content: periodData.lock_inventory ? 'Locked' : 'Unlocked',
      asset: periodData.lock_inventory ? 'bx bx-lock-alt' : 'bx bx-lock-open-alt',
      color: periodData.lock_inventory ? 'error' : 'success'
    }
  ]
}
