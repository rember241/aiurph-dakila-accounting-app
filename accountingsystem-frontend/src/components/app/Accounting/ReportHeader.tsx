import { useState } from 'react'
import type { MouseEvent } from 'react'

import { Button, Chip, Collapse, Menu, MenuItem, Paper, Skeleton, Stack, Typography } from '@mui/material'
import type { ChipProps } from '@mui/material'
import ListItemIcon from '@mui/material/ListItemIcon'
import ListItemText from '@mui/material/ListItemText'

// Props to pass for this functional component
interface HeaderProps {
  companyName: string
  reportType: string
  dateText: string
  isLoading: boolean
  handleDownload?: (fileType: 'csv' | 'xlsx' | 'pdf') => void
  handlePrint?: () => void
  status?: {
    label: string
    color: ChipProps['color']
  }
}

function ReportHeader({
  companyName,
  reportType,
  dateText,
  isLoading,
  handleDownload,
  handlePrint,
  status
}: HeaderProps) {
  // Dropdown menu states
  const [anchorEl, setAnchorEl] = useState<HTMLElement | null>(null)
  const isDropdownOpen = Boolean(anchorEl)

  // Collapsable menu component state
  const [isCollapsed, setCollapsed] = useState(true)

  // Function to handle opening the dropdown menu
  const handleDropdownOpen = (event: MouseEvent<HTMLButtonElement>) => {
    setAnchorEl(event.currentTarget)
  }

  // Function to close the dropdown menu
  const handleDropdownClose = () => {
    setAnchorEl(null)
    setCollapsed(true)
  }

  return (
    <Paper variant='outlined' sx={{ p: 5, borderRadius: 3 }}>
      <Stack
        direction={{ xs: 'column', sm: 'row' }}
        gap={3}
        alignItems={{ sm: 'center' }}
        justifyContent='space-between'
      >
        {/* Header Text */}
        <Stack gap={0} alignItems='flex-start' justifyContent='center'>
          <Typography variant='h5'>{reportType}</Typography>
          <Typography variant='subtitle2' color='text.secondary'>
            {isLoading ? <Skeleton width='335px' /> : `${companyName} · ${dateText}`}
          </Typography>
        </Stack>

        {/* Actions Buttons */}
        <Stack direction='row' gap={2} alignItems='center'>
          {status && <Chip size='small' label={status.label} color={status.color} variant='tonal' />}
          <Button
            disabled={isLoading}
            variant='outlined'
            color='secondary'
            startIcon={<i className='bx-printer' />}
            endIcon={<i className='bx-caret-down' />}
            onClick={isDropdownOpen ? handleDropdownClose : handleDropdownOpen}
          >
            Print / Export
          </Button>
        </Stack>

        {/* Dropdown Menu */}
        <Menu
          sx={{ mt: 2 }}
          anchorEl={anchorEl}
          open={isDropdownOpen}
          onClose={handleDropdownClose}
          anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
          transformOrigin={{ vertical: 'top', horizontal: 'right' }}
        >
          <MenuItem
            sx={{ width: '350px' }}
            onClick={() => {
              handleDropdownClose()
              setTimeout(() => handlePrint?.(), 0)
            }}
          >
            <ListItemIcon>
              <i className='bx-bxs-printer' />
            </ListItemIcon>
            <ListItemText>Print</ListItemText>
          </MenuItem>

          {handleDownload && (
            <MenuItem sx={{ width: '350px' }} onClick={() => setCollapsed(!isCollapsed)}>
              <ListItemIcon>
                <i className='bx-arrow-to-bottom' />
              </ListItemIcon>
              <ListItemText>Download</ListItemText>
              <ListItemIcon>
                <i className={isCollapsed ? 'bx-chevron-down' : 'bx-chevron-up'} />
              </ListItemIcon>
            </MenuItem>
          )}

          {handleDownload && (
            <Collapse in={!isCollapsed} timeout='auto' unmountOnExit>
              <MenuItem
                onClick={() => {
                  handleDownload('csv')
                  handleDropdownClose()
                }}
              >
                <ListItemText sx={{ pl: 7 }}>CSV File</ListItemText>
                <Typography variant='body2'>.csv</Typography>
              </MenuItem>
              <MenuItem
                onClick={() => {
                  handleDownload('xlsx')
                  handleDropdownClose()
                }}
              >
                <ListItemText sx={{ pl: 7 }}>Excel Spreadsheet</ListItemText>
                <Typography variant='body2'>.xlsx</Typography>
              </MenuItem>
              <MenuItem
                onClick={() => {
                  handleDownload('pdf')
                  handleDropdownClose()
                }}
              >
                <ListItemText sx={{ pl: 7 }}>PDF Document</ListItemText>
                <Typography variant='body2'>.pdf</Typography>
              </MenuItem>
            </Collapse>
          )}
        </Menu>
      </Stack>
    </Paper>
  )
}

export default ReportHeader
