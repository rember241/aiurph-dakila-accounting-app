import React from 'react'

import Typography from '@mui/material/Typography'
import Grid from '@mui/material/Grid'
import Box from '@mui/material/Box'
import Chip from '@mui/material/Chip'
import Divider from '@mui/material/Divider'
import { Stack } from '@mui/material'

import type { AccountColumn } from '@/features/(protected)/accounting/setup/chart-of-accounts/model/accounts.types'
import CustomTable from '@components/app/CustomTable'

type AccountDetails = {
  account: AccountColumn | null
}

const columns = [
  { field: 'name', headerName: 'Date', width: 100, flex: 1 },
  { field: 'code', headerName: 'Transaction', width: 150, flex: 1 },
  { field: 'reference', headerName: 'Reference', width: 200, flex: 2 },
  { field: 'debit', headerName: 'Debit', width: 200, flex: 2 },
  { field: 'credit', headerName: 'Credit', width: 200, flex: 2 }
]

const fakeAccounts = Array.from({ length: 1000 }, (_, idx) => ({
  id: idx + 1,
  name: `Account ${idx + 1}`,
  code: `ACC${idx + 1}`,
  description: `Description ${idx + 1}`,
  reference: `Reference ${idx + 1}`,
  debit: Math.floor(Math.random() * 1000000),
  credit: Math.floor(Math.random() * 1000000),
  date: new Date().toISOString().split('T')[0]
}))

const rows = fakeAccounts

function AccountDetails({ account }: AccountDetails) {
  return (
    <>
      <Box sx={{ width: '100%' }}>
        <Grid direction={'row'} container spacing={2} alignItems={'center'} sx={{ mb: 3 }}>
          <Typography variant={'body1'}>Status:</Typography>
          <Chip
            size={'small'}
            variant={'tonal'}
            color={account?.status === 'active' ? 'success' : 'error'}
            icon={<i className={'bx-radio-circle-marked w-5 h-5'} />}
            label={account?.status ? `${account?.status.charAt(0).toUpperCase()}${account?.status.slice(1)}` : ''}
          />
        </Grid>
        <Grid direction={'column'} container spacing={4}>
          <Grid direction={'row'} container spacing={2} alignItems={'center'}>
            <Typography variant={'body1'}>Account Code:</Typography>
            <Typography variant={'body1'} fontWeight={'bold'}>
              {account?.code}
            </Typography>
          </Grid>
          <Grid direction={'row'} container spacing={2} alignItems={'center'}>
            <Typography variant={'body1'}>Account Name:</Typography>
            <Typography variant={'body1'} fontWeight={'bold'}>
              {account?.name}
            </Typography>
          </Grid>
          <Grid direction={'row'} container spacing={2} alignItems={'center'}>
            <Typography variant={'body1'}>Account Type:</Typography>
            <Typography variant={'body1'} fontWeight={'bold'}>
              {account?.account_type_name}
            </Typography>
          </Grid>
          <Grid direction={'row'} container spacing={2} alignItems={'center'}>
            <Typography variant={'body1'}>Detail Type:</Typography>
            <Typography variant={'body1'} fontWeight={'bold'}>
              {account?.detail_type_name}
            </Typography>
          </Grid>
          <Grid direction={'row'} container spacing={2} alignItems={'center'}>
            <Typography variant={'body1'}>Description:</Typography>
            <Typography variant={'body1'} fontWeight={'bold'}>
              {account?.description}
            </Typography>
          </Grid>
          <Divider />
          {/*<Grid direction={'row'} container spacing={2} alignItems={'center'}>*/}
          {/*  <Typography variant={'body2'}>Created at:</Typography>*/}
          {/*  <Typography variant={'body2'} fontWeight={'bold'}></Typography>*/}
          {/*</Grid>*/}
          {/*<Grid direction={'row'} container spacing={2} alignItems={'center'}>*/}
          {/*  <Typography variant={'body2'}>Updated at:</Typography>*/}
          {/*  <Typography variant={'body2'} fontWeight={'bold'}></Typography>*/}
          {/*</Grid>*/}
        </Grid>

        <Box
          sx={{
            width: '100%',
            overflowX: 'auto', // scroll only inside this area if needed
            '& *': { boxSizing: 'border-box' }
          }}
        >
          <Box sx={{ minWidth: 700 }}>
            <CustomTable columns={columns} rows={rows} />
          </Box>
        </Box>
      </Box>
    </>
  )
}

export default AccountDetails
