'use client'

// React Imports
import { useRef, useState } from 'react'
import type { MouseEvent } from 'react'

// Next Imports
import { useRouter } from 'next/navigation'

// MUI Imports
import { styled } from '@mui/material/styles'
import Badge from '@mui/material/Badge'
import Popper from '@mui/material/Popper'
import Fade from '@mui/material/Fade'
import Paper from '@mui/material/Paper'
import ClickAwayListener from '@mui/material/ClickAwayListener'
import MenuList from '@mui/material/MenuList'
import Typography from '@mui/material/Typography'
import Divider from '@mui/material/Divider'
import MenuItem from '@mui/material/MenuItem'

// Component Imports
import CustomAvatar from '@core/components/mui/Avatar'

// Hook Imports
import { useSettings } from '@core/hooks/useSettings'
import { useMeQuery } from '@/features/auth/api/auth.queries'
import { useCurrentTenantAccess } from '@/features/auth/access/components/CurrentTenantAccessProvider'
import { APP_ABILITIES } from '@/features/auth/access/model/access.schema'

// Styled component for badge content
const BadgeContentSpan = styled('span')({
  width: 8,
  height: 8,
  borderRadius: '50%',
  cursor: 'pointer',
  backgroundColor: 'var(--mui-palette-success-main)',
  boxShadow: '0 0 0 2px var(--mui-palette-background-paper)'
})

const UserDropdown = () => {
  // States
  const [open, setOpen] = useState(false)

  // User
  const { data } = useMeQuery()
  const access = useCurrentTenantAccess()
  const name = data?.user?.name ?? 'User'
  const email = data?.user?.email ?? ''
  const avatarUrl = data?.user?.avatar_url ?? undefined
  const canManageRoles = access.can(APP_ABILITIES.rolesRead)

  // Refs
  const anchorRef = useRef<HTMLDivElement>(null)

  // Hooks
  const router = useRouter()

  const { settings } = useSettings()

  const handleDropdownOpen = () => {
    !open ? setOpen(true) : setOpen(false)
  }

  const handleDropdownClose = (event?: MouseEvent<HTMLLIElement> | (MouseEvent | TouchEvent), url?: string) => {
    if (url) {
      router.push(url)
    }

    if (anchorRef.current && anchorRef.current.contains(event?.target as HTMLElement)) {
      return
    }

    setOpen(false)
  }

  const handleUserLogout = () => {
    // Route through a server-side logout endpoint so the redirect and
    // cookie expiration happen in the same navigation response.
    window.location.replace('/logout')
  }

  return (
    <>
      <Badge
        ref={anchorRef}
        overlap='circular'
        badgeContent={<BadgeContentSpan onClick={handleDropdownOpen} />}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
        className='mis-2.5'
      >
        <CustomAvatar
          ref={anchorRef}
          alt={name}
          src={avatarUrl}
          onClick={handleDropdownOpen}
          className='cursor-pointer'
        >
          {name.charAt(0).toUpperCase() || 'U'}
        </CustomAvatar>
      </Badge>
      <Popper
        open={open}
        transition
        disablePortal
        placement='bottom-end'
        anchorEl={anchorRef.current}
        className='min-is-[240px] !mbs-4 z-[1]'
      >
        {({ TransitionProps, placement }) => (
          <Fade
            {...TransitionProps}
            style={{
              transformOrigin: placement === 'bottom-end' ? 'right top' : 'left top'
            }}
          >
            <Paper className={settings.skin === 'bordered' ? 'border shadow-none' : 'shadow-lg'}>
              <ClickAwayListener onClickAway={e => handleDropdownClose(e as MouseEvent | TouchEvent)}>
                <MenuList>
                  <div className='flex items-center plb-2 pli-5 gap-2' tabIndex={-1}>
                    <CustomAvatar size={40} alt={name} src={avatarUrl}>
                      {name.charAt(0).toUpperCase() || 'U'}
                    </CustomAvatar>
                    <div className='flex items-start flex-col'>
                      <Typography variant='h6'>{name}</Typography>
                      <Typography variant='body2' color='text.disabled'>
                        {email}
                      </Typography>
                    </div>
                  </div>
                  <Divider className='mlb-1' />
                  {canManageRoles ? (
                    <MenuItem className='gap-3' onClick={e => handleDropdownClose(e, '/users/roles')}>
                      <i className='bx-shield' />
                      <Typography color='text.primary'>Roles & Permissions</Typography>
                    </MenuItem>
                  ) : null}
                  <Divider className='mlb-1' />
                  <MenuItem className='gap-3' onClick={handleUserLogout}>
                    <i className='bx-power-off' />
                    <Typography color='text.primary'>Logout</Typography>
                  </MenuItem>
                </MenuList>
              </ClickAwayListener>
            </Paper>
          </Fade>
        )}
      </Popper>
    </>
  )
}

export default UserDropdown
