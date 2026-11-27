import Tab from '@mui/material/Tab'
import Tabs from '@mui/material/Tabs'

type FilterOption<Value extends string> = { value: Value; label: string; icon: string }

type FilterTabsProps<Value extends string> = {
  label: string
  value: Value
  onChange: (value: Value) => void
  options: readonly FilterOption<Value>[]
  disabled?: boolean
}

export const ACTIVE_STATUS_FILTERS = [
  { value: '', label: 'All Statuses', icon: 'bx-list-ul' },
  { value: 'active', label: 'Active', icon: 'bx-check-circle' },
  { value: 'inactive', label: 'Inactive', icon: 'bx-minus-circle' }
] as const

export default function FilterTabs<Value extends string>({
  label,
  value,
  onChange,
  options,
  disabled
}: FilterTabsProps<Value>) {
  return (
    <Tabs
      aria-label={label}
      value={value}
      onChange={(_, nextValue: Value) => onChange(nextValue)}
      variant='scrollable'
      scrollButtons='auto'
      allowScrollButtonsMobile
      sx={{ px: { xs: 1, sm: 3 }, '& .MuiTab-root': { minWidth: 'auto' } }}
    >
      {options.map(option => (
        <Tab
          key={option.value}
          value={option.value}
          label={option.label}
          icon={<i className={option.icon} aria-hidden='true' />}
          iconPosition='start'
          disabled={disabled}
        />
      ))}
    </Tabs>
  )
}
