import type { Dispatch, SetStateAction } from 'react'

import { Autocomplete, Checkbox, Chip, darken, lighten, styled, TextField } from '@mui/material'

import type { AccountGroup } from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'

// Props for this functional component
interface SelectProps {
  withLabel?: boolean
  disabled: boolean
  accounts: AccountGroup[]
  selectedAccounts: string[]
  setSelectedAccounts: Dispatch<SetStateAction<string[]>>
}

function MultipleAccountSelect({
  withLabel = true,
  disabled,
  accounts,
  selectedAccounts,
  setSelectedAccounts
}: SelectProps) {
  // Header style for the grouped account options
  const GroupHeader = styled('div')(({ theme }) => ({
    position: 'sticky',
    top: '-8px',
    padding: '5px 10px',
    color: 'white',
    zIndex: 1,
    backgroundColor: lighten(theme.palette.primary.light, 0.85),
    ...theme.applyStyles('dark', {
      backgroundColor: darken(theme.palette.primary.dark, 0)
    })
  }))

  // Menu item style for the grouped account options
  const GroupItems = styled('ul')({
    padding: 0
  })

  return (
    <Autocomplete
      multiple
      openOnFocus
      limitTags={2}
      sx={{ flex: 1 }}
      options={accounts}
      disabled={disabled}
      disableCloseOnSelect
      groupBy={account => account.coreType}
      value={accounts?.filter(account => selectedAccounts?.includes(account.id))}
      onChange={(_, account) => {
        setSelectedAccounts(account.map(account => account.id))
      }}
      getOptionLabel={account => `(${account.code}) ${account.name}`}
      renderInput={params => (
        <TextField
          {...params}
          label={withLabel ? 'Selected Accounts' : null}
          placeholder={selectedAccounts.length === 0 ? 'Select account' : ''}
        />
      )}
      renderGroup={params => (
        <li key={params.key}>
          <GroupHeader>{params.group}</GroupHeader>
          <GroupItems>{params.children}</GroupItems>
        </li>
      )}
      renderOption={(props, option, { selected }) => {
        const { key, ...optionProps } = props

        return (
          <li key={key} {...optionProps}>
            <Checkbox checked={selected} style={{ marginRight: 8 }} />
            {`(${option.code}) ${option.name}`}
          </li>
        )
      }}
      renderValue={(value, getTagProps) => {
        const visible = value.slice(0, 2)
        const hiddenCount = value.length - visible.length

        return (
          <>
            {visible.map((account, index) => {
              const { key, ...tagProps } = getTagProps({ index })

              return <Chip key={key} {...tagProps} label={account.name} />
            })}

            {hiddenCount > 0 && <Chip label={`+${hiddenCount}`} />}
          </>
        )
      }}
    />
  )
}

export default MultipleAccountSelect
