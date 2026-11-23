import * as React from 'react'

import Button from '@mui/material/Button'
import ButtonGroup from '@mui/material/ButtonGroup'
import ClickAwayListener from '@mui/material/ClickAwayListener'
import Grow from '@mui/material/Grow'
import Paper from '@mui/material/Paper'
import Popper from '@mui/material/Popper'
import MenuItem from '@mui/material/MenuItem'
import MenuList from '@mui/material/MenuList'

type Props = {
  title: string
  options: readonly string[]
  value?: string
  onSelect: (value: string) => void
  onCreate?: (value: string) => void
  disabled?: boolean
}

export default function CustomSplitButton({ title, options, value, onSelect, onCreate, disabled = false }: Props) {
  const [open, setOpen] = React.useState(false)
  const anchorRef = React.useRef<HTMLDivElement>(null)

  const handleCreate = () => {
    if (!value || disabled) return
    onCreate?.(value)
  }

  const handleToggle = () => {
    if (disabled) return
    setOpen(prev => !prev)
  }

  const handleClose = (event: Event) => {
    if (anchorRef.current?.contains(event.target as HTMLElement)) return
    setOpen(false)
  }

  return (
    <>
      <ButtonGroup variant='contained' ref={anchorRef} disabled={disabled}>
        <Button onClick={handleCreate}>{title}</Button>

        <Button
          size='small'
          aria-controls={open ? 'split-button-menu' : undefined}
          aria-expanded={open ? 'true' : undefined}
          aria-haspopup='menu'
          onClick={handleToggle}
        >
          <i className='bx bx-chevron-down' />
        </Button>
      </ButtonGroup>

      <Popper
        open={open}
        anchorEl={anchorRef.current}
        placement='bottom-start'
        transition
        disablePortal
        sx={{ zIndex: 1300, width: anchorRef.current?.clientWidth }}
      >
        {({ TransitionProps }) => (
          <Grow {...TransitionProps}>
            <Paper sx={{ width: '100%' }}>
              <ClickAwayListener onClickAway={handleClose}>
                <MenuList id='split-button-menu' autoFocusItem>
                  {options.map(opt => (
                    <MenuItem
                      key={opt}
                      selected={opt === value}
                      onClick={() => {
                        onSelect(opt)
                        setOpen(false)
                      }}
                    >
                      {opt}
                    </MenuItem>
                  ))}
                </MenuList>
              </ClickAwayListener>
            </Paper>
          </Grow>
        )}
      </Popper>
    </>
  )
}
